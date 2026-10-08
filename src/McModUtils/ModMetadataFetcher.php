<?php
namespace McModUtils;

class ModMetadataFetcher
{
    const CACHE_FILE = BASE_PATH . '/public/static/mods_metadata_cache.json';
    const ICONS_DIR = BASE_PATH . '/public/static/mod_icons';
    const DEFAULT_TTL = 1209600; // 14 天 (14 * 86400 秒)

    public static function getCacheFilePath() : string {
        return self::CACHE_FILE;
    }

    public static function getIconsDir() : string {
        return self::ICONS_DIR;
    }

    public static function loadCache() : array {
        $file = self::getCacheFilePath();
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $json = json_decode($content, true);
            if (is_array($json)) {
                return $json;
            }
        }
        return [];
    }

    public static function saveCache(array $cache) : bool {
        $file = self::getCacheFilePath();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return file_put_contents($file, json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false;
    }

    /**
     * 批次為傳入的 Mod 陣列注入並補全外部元數據（簡介、官方 CDN 載點、圖標快取）
     *
     * @param Mod[] $mods
     * @param bool $force 強制忽略快取
     * @param int $ttl 快取有效秒數
     */
    public static function enrichMods(array &$mods, bool $force = false, int $ttl = self::DEFAULT_TTL) : void {
        if (empty($mods)) {
            return;
        }

        $cache = self::loadCache();
        $now = time();
        $sha1ToModMap = [];
        $missingSha1s = [];

        // 確保圖標儲存目錄存在
        $iconsDir = self::getIconsDir();
        if (!is_dir($iconsDir)) {
            @mkdir($iconsDir, 0755, true);
        }

        foreach ($mods as $mod) {
            $sha1 = $mod->getSha1();
            $sha1ToModMap[$sha1] = $mod;

            // 確保本地 JAR 內嵌圖標有解壓出來作為保底縮圖
            $localThumb = $mod->extractLocalIcon($iconsDir);

            $isCached = isset($cache[$sha1]);
            $isExpired = $isCached && (empty($cache[$sha1]['cached_at']) || ($now - (int)$cache[$sha1]['cached_at']) > $ttl);

            // 若已有快取但圖標檔案在本地遺失，則需要重新補充圖標
            $needsIconDownload = false;
            if ($isCached && !empty($cache[$sha1]['logo']['modrinth_url']) && empty($cache[$sha1]['logo']['local_icon'])) {
                $needsIconDownload = true;
            }

            if ($force || !$isCached || $isExpired || $needsIconDownload) {
                $missingSha1s[] = $sha1;
            } else {
                // 快取有效，直接賦值給 Mod
                $mod->setExtra($cache[$sha1]);
            }
        }

        // 若沒有需要更新的模組，直接結束
        if (empty($missingSha1s)) {
            return;
        }

        $missingSha1s = array_values(array_unique($missingSha1s));

        // 1. 透過 Modrinth 批次查詢（每批次最多 100 筆）
        $modrinthBatches = array_chunk($missingSha1s, 100);
        $modrinthResults = [];

        foreach ($modrinthBatches as $batchSha1s) {
            $batchResult = self::queryModrinthVersionFiles($batchSha1s);
            if (!empty($batchResult)) {
                $modrinthResults = array_merge($modrinthResults, $batchResult);
            }
        }

        // 收集所有命中的 project_id 進行批次專案詳情查詢
        $projectIds = [];
        foreach ($modrinthResults as $hash => $versionInfo) {
            if (!empty($versionInfo['project_id'])) {
                $projectIds[] = $versionInfo['project_id'];
            }
        }
        $projectIds = array_values(array_unique($projectIds));
        $modrinthProjects = [];
        if (!empty($projectIds)) {
            $projectBatches = array_chunk($projectIds, 100);
            foreach ($projectBatches as $pBatch) {
                $pResults = self::queryModrinthProjects($pBatch);
                if (!empty($pResults)) {
                    $modrinthProjects = array_merge($modrinthProjects, $pResults);
                }
            }
        }

        // 檢查是否有設定 CurseForge API Key
        $curseforgeApiKey = $GLOBALS['config']['curseforge_api_key'] ?? null;

        // 2. 彙整結果並寫入快取
        foreach ($missingSha1s as $sha1) {
            $mod = $sha1ToModMap[$sha1] ?? null;
            if (!$mod) {
                continue;
            }

            $modrinthVersion = $modrinthResults[$sha1] ?? null;
            $modrinthProject = null;
            if ($modrinthVersion && !empty($modrinthVersion['project_id'])) {
                $modrinthProject = $modrinthProjects[$modrinthVersion['project_id']] ?? null;
            }

            $source = 'server';
            $isCustom = true;
            $summary = $mod->getDescription();
            $description = $mod->getDescription();
            $remoteDownloadUrl = null;
            $remoteIconUrl = null;
            $modrinthUrl = null;
            $curseforgeUrl = null;
            $sourceUrl = null;
            $issuesUrl = null;
            $wikiUrl = null;
            $websiteUrl = $mod->getDisplayURL();

            if ($modrinthVersion && $modrinthProject) {
                $source = 'modrinth';
                $isCustom = false;
                $summary = !empty($modrinthProject['description']) ? trim($modrinthProject['description']) : $mod->getDescription();
                $description = $summary;
                $remoteIconUrl = $modrinthProject['icon_url'] ?? null;
                $modrinthUrl = !empty($modrinthProject['slug']) ? 'https://modrinth.com/mod/' . $modrinthProject['slug'] : null;
                $websiteUrl = $modrinthUrl;
                $sourceUrl = $modrinthProject['source_url'] ?? null;
                $issuesUrl = $modrinthProject['issues_url'] ?? null;
                $wikiUrl = $modrinthProject['wiki_url'] ?? null;

                // 提取 Modrinth 官方 CDN 下載網址
                if (!empty($modrinthVersion['files']) && is_array($modrinthVersion['files'])) {
                    foreach ($modrinthVersion['files'] as $f) {
                        if (!empty($f['hashes']['sha1']) && strtolower($f['hashes']['sha1']) === strtolower($sha1)) {
                            $remoteDownloadUrl = $f['url'] ?? null;
                            break;
                        }
                    }
                    if (!$remoteDownloadUrl && !empty($modrinthVersion['files'][0]['url'])) {
                        $remoteDownloadUrl = $modrinthVersion['files'][0]['url'];
                    }
                }
            } elseif (!empty($curseforgeApiKey)) {
                // 若 Modrinth 未命中，嘗試查詢 CurseForge
                $cfResult = self::queryCurseforgeMod($mod, $curseforgeApiKey);
                if ($cfResult) {
                    $source = 'curseforge';
                    $isCustom = false;
                    $summary = $cfResult['summary'] ?? $mod->getDescription();
                    $description = $summary;
                    $remoteIconUrl = $cfResult['icon_url'] ?? null;
                    $curseforgeUrl = $cfResult['website_url'] ?? null;
                    $websiteUrl = $curseforgeUrl ?: $websiteUrl;
                    $remoteDownloadUrl = $cfResult['download_url'] ?? null;
                    $sourceUrl = $cfResult['source_url'] ?? null;
                    $issuesUrl = $cfResult['issues_url'] ?? null;
                    $wikiUrl = $cfResult['wiki_url'] ?? null;
                }
            }

            // 下載遠端圖標至本地伺服器
            $localIconFile = null;
            if (!empty($remoteIconUrl)) {
                $localIconFile = self::downloadAndSaveIcon($remoteIconUrl, $sha1);
            }

            // 取得本地解壓的縮圖檔案名稱
            $localThumbFile = $sha1 . '_thumb.png';
            if (!file_exists($iconsDir . '/' . $localThumbFile)) {
                $localThumbFile = $mod->extractLocalIcon($iconsDir);
            }

            $entry = [
                'cached_at' => $now,
                'source' => $source,
                'is_custom' => $isCustom,
                'summary' => $summary,
                'description' => $description,
                'download_url' => $remoteDownloadUrl,
                'logo' => [
                    'local_icon' => $localIconFile,
                    'local_thumb' => $localThumbFile,
                    'modrinth_url' => ($source === 'modrinth') ? $remoteIconUrl : null,
                    'curseforge_url' => ($source === 'curseforge') ? $remoteIconUrl : null,
                ],
                'links' => [
                    'website_url' => $websiteUrl,
                    'modrinth_url' => $modrinthUrl,
                    'curseforge_url' => $curseforgeUrl,
                    'source_url' => $sourceUrl,
                    'issues_url' => $issuesUrl,
                    'wiki_url' => $wikiUrl,
                ]
            ];

            $cache[$sha1] = $entry;
            $mod->setExtra($entry);
        }

        self::saveCache($cache);
    }

    /**
     * 向 Modrinth 批次查詢 Version Files
     */
    protected static function queryModrinthVersionFiles(array $sha1List) : array {
        if (empty($sha1List)) {
            return [];
        }

        $url = 'https://api.modrinth.com/v2/version_files';
        $payload = json_encode([
            'hashes' => array_values($sha1List),
            'algorithm' => 'sha1'
        ]);

        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n" .
                            "User-Agent: chyuaner/php-minecraft-server-info (contact: chyuaner@gmail.com)\r\n",
                'content' => $payload,
                'timeout' => 15,
                'ignore_errors' => true
            ]
        ];

        $context = stream_context_create($opts);
        $res = @file_get_contents($url, false, $context);
        if ($res === false) {
            return [];
        }

        $data = json_decode($res, true);
        return is_array($data) ? $data : [];
    }

    /**
     * 向 Modrinth 批次查詢 Projects 詳情
     */
    protected static function queryModrinthProjects(array $projectIds) : array {
        if (empty($projectIds)) {
            return [];
        }

        $url = 'https://api.modrinth.com/v2/projects?ids=' . rawurlencode(json_encode(array_values($projectIds)));

        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: chyuaner/php-minecraft-server-info (contact: chyuaner@gmail.com)\r\n",
                'timeout' => 15,
                'ignore_errors' => true
            ]
        ];

        $context = stream_context_create($opts);
        $res = @file_get_contents($url, false, $context);
        if ($res === false) {
            return [];
        }

        $list = json_decode($res, true);
        if (!is_array($list)) {
            return [];
        }

        $map = [];
        foreach ($list as $item) {
            if (!empty($item['id'])) {
                $map[$item['id']] = $item;
            }
        }
        return $map;
    }

    /**
     * 查詢 CurseForge 模組資訊
     */
    protected static function queryCurseforgeMod(Mod $mod, string $apiKey) : ?array {
        $name = $mod->getName();
        if (empty($name)) {
            return null;
        }

        $url = 'https://api.curseforge.com/v1/mods/search?gameId=432&searchFilter=' . rawurlencode($name);
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => "x-api-key: {$apiKey}\r\n" .
                            "User-Agent: chyuaner/php-minecraft-server-info\r\n",
                'timeout' => 10,
                'ignore_errors' => true
            ]
        ];

        $context = stream_context_create($opts);
        $res = @file_get_contents($url, false, $context);
        if ($res === false) {
            return null;
        }

        $json = json_decode($res, true);
        if (empty($json['data']) || !is_array($json['data'])) {
            return null;
        }

        $item = $json['data'][0];
        return [
            'summary' => $item['summary'] ?? null,
            'icon_url' => $item['logo']['url'] ?? null,
            'website_url' => $item['links']['websiteUrl'] ?? null,
            'source_url' => $item['links']['sourceUrl'] ?? null,
            'issues_url' => $item['links']['issuesUrl'] ?? null,
            'wiki_url' => $item['links']['wikiUrl'] ?? null,
            'download_url' => $item['links']['downloadUrl'] ?? null,
        ];
    }

    /**
     * 下載遠端圖標並儲存至伺服器本地
     */
    public static function downloadAndSaveIcon(string $remoteUrl, string $sha1) : ?string {
        $iconsDir = self::getIconsDir();
        if (!is_dir($iconsDir)) {
            @mkdir($iconsDir, 0755, true);
        }

        // 解析副檔名
        $path = parse_url($remoteUrl, PHP_URL_PATH);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['webp', 'png', 'jpg', 'jpeg', 'svg', 'gif'])) {
            $ext = 'webp';
        }

        $fileName = $sha1 . '.' . $ext;
        $targetFile = $iconsDir . '/' . $fileName;

        if (file_exists($targetFile) && filesize($targetFile) > 0) {
            return $fileName;
        }

        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: chyuaner/php-minecraft-server-info\r\n",
                'timeout' => 8,
                'ignore_errors' => true
            ]
        ];

        $context = stream_context_create($opts);
        $content = @file_get_contents($remoteUrl, false, $context);
        if ($content !== false && strlen($content) > 0) {
            file_put_contents($targetFile, $content);
            return $fileName;
        }

        return null;
    }
}
