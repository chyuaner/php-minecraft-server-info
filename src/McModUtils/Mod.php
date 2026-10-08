<?php
namespace McModUtils;

class Mod {
    protected $modFileName;
    protected $modFilePath;

    protected $name = '';
    protected $version = '';
    protected $authors = [];
    protected $md5 = '';
    protected $sha1 = '';

    protected $modId = '';
    protected $description = '';
    protected $logoFile = '';
    protected $displayURL = '';
    protected $fileLength = null;
    protected $fileDate = null;
    protected $extra = [];

    private function parseFileInput(string $raw) : string {

        // 若輸入的只有單檔檔名
        if (basename($raw) === $raw) {
            return join(DIRECTORY_SEPARATOR, [rtrim($GLOBALS['config']['mods_path'], '/'), $raw]);
        }
        // 其他情況，直接當作絕對路徑
        else {
            return $raw;
        }
    }

    public function __construct(string $fileName) {
        $this->modFileName = basename($fileName);
        $this->modFilePath = $this->parseFileInput($fileName);
        if (!file_exists($this->modFilePath)) {
            throw new \Exception("Mod file not found: $this->modFilePath");
        }
    }

    public function isFileExist() : bool {
        return file_exists($this->modFilePath);
    }

    private function isPlaceholder(?string $val): bool {
        if ($val === null || $val === '') {
            return true;
        }
        $trimmed = trim($val);
        return str_starts_with($trimmed, '${') && str_ends_with($trimmed, '}');
    }

    public function parse(): bool {
        $isSuccess = false;

        if (file_exists($this->modFilePath)) {
            $this->fileLength = filesize($this->modFilePath);
            $this->fileDate = (new \DateTime())->setTimestamp(filemtime($this->modFilePath))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        }

        $zip = new \ZipArchive();
        if ($zip->open($this->modFilePath) === true) {
            $manifestRaw = $zip->getFromName('META-INF/MANIFEST.MF');
            $manifestData = ($manifestRaw !== false) ? $this->parseManifest($manifestRaw) : [];

            // 1. NeoForge (優先)
            $neoforgeTomlRaw = $zip->getFromName('META-INF/neoforge.mods.toml');
            if ($neoforgeTomlRaw !== false) {
                $parseResult = $this->parseNeoforgeToml($neoforgeTomlRaw);
                $isSuccess = $this->applyParseResult($parseResult) || $isSuccess;
            }

            // 2. Forge (舊)
            $forgeTomlRaw = $zip->getFromName('META-INF/mods.toml');
            if ($forgeTomlRaw !== false) {
                // Forge與NeoForge幾乎相同，可沿用同一個解析器
                $parseResult = $this->parseNeoforgeToml($forgeTomlRaw);
                $isSuccess = $this->applyParseResult($parseResult) || $isSuccess;
            }

            // 3. Fabric
            $fabricJsonRaw = $zip->getFromName('fabric.mod.json');
            if ($fabricJsonRaw !== false) {
                $parseResult = $this->parseFabricJson($fabricJsonRaw);
                $isSuccess = $this->applyParseResult($parseResult) || $isSuccess;
            }

            // 4. Quilt
            $quiltJsonRaw = $zip->getFromName('quilt.mod.json');
            if ($quiltJsonRaw !== false) {
                $parseResult = $this->parseFabricJson($quiltJsonRaw);
                $isSuccess = $this->applyParseResult($parseResult) || $isSuccess;
            }

            // 5. 若根目錄未發現模組定義，探測 Jar-in-Jar (JiJ / META-INF/jarjar)
            if (empty($this->modId) || empty($this->name)) {
                $this->detectJarInJar($zip);
            }

            // 6. 應用 MANIFEST.MF 資訊 (補全版號或作者)
            if (!empty($manifestData)) {
                if (empty($this->version) || $this->isPlaceholder($this->version)) {
                    if (!empty($manifestData['version']) && !$this->isPlaceholder($manifestData['version'])) {
                        $this->version = $manifestData['version'];
                        $isSuccess = true;
                    }
                }
                if (empty($this->name) || $this->isPlaceholder($this->name)) {
                    if (!empty($manifestData['name']) && !$this->isPlaceholder($manifestData['name'])) {
                        $this->name = $manifestData['name'];
                        $isSuccess = true;
                    }
                }
                if (empty($this->authors) || (isset($this->authors[0]) && $this->isPlaceholder($this->authors[0]))) {
                    if (!empty($manifestData['authors'])) {
                        $this->authors = $manifestData['authors'];
                        $isSuccess = true;
                    }
                }
            }

            $zip->close();
        }

        // fallback: 檔名解析 (補足名稱或版號)
        if (empty($this->name) || $this->isPlaceholder($this->name) || empty($this->version) || $this->isPlaceholder($this->version)) {
            $parseResult = $this->parseFilename($this->getFileName());
            $isSuccess = $this->applyParseResult($parseResult) || $isSuccess;
        }

        return $isSuccess;
    }

    private function detectJarInJar(\ZipArchive $zip): void {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_starts_with($name, 'META-INF/jarjar/') && str_ends_with($name, '.jar')) {
                $innerData = $zip->getFromIndex($i);
                if ($innerData !== false) {
                    $tmp = tempnam(sys_get_temp_dir(), 'jij_');
                    file_put_contents($tmp, $innerData);
                    $innerZip = new \ZipArchive();
                    if ($innerZip->open($tmp) === true) {
                        $neo = $innerZip->getFromName('META-INF/neoforge.mods.toml');
                        if ($neo !== false) {
                            $res = $this->parseNeoforgeToml($neo);
                            $this->applyParseResult($res);
                        } else {
                            $forge = $innerZip->getFromName('META-INF/mods.toml');
                            if ($forge !== false) {
                                $res = $this->parseNeoforgeToml($forge);
                                $this->applyParseResult($res);
                            }
                        }
                        $innerZip->close();
                    }
                    @unlink($tmp);
                    if (!empty($this->name) && !empty($this->modId) && !$this->isPlaceholder($this->name)) {
                        break;
                    }
                }
            }
        }
    }

    private function parseManifest(string $raw): array {
        $result = [];
        $lines = preg_split("/\r\n|\n|\r/", $raw);
        $manifest = [];
        $currentKey = null;
        foreach ($lines as $line) {
            if (str_starts_with($line, " ") && $currentKey !== null) {
                $manifest[$currentKey] .= trim($line);
            } elseif (str_contains($line, ":")) {
                [$key, $val] = explode(":", $line, 2);
                $currentKey = trim($key);
                $manifest[$currentKey] = trim($val);
            }
        }

        if (!empty($manifest['Implementation-Version']) && !$this->isPlaceholder($manifest['Implementation-Version'])) {
            $result['version'] = trim($manifest['Implementation-Version']);
        }
        if (!empty($manifest['Implementation-Title']) && !$this->isPlaceholder($manifest['Implementation-Title'])) {
            $result['name'] = trim($manifest['Implementation-Title']);
        } elseif (!empty($manifest['Specification-Title']) && !$this->isPlaceholder($manifest['Specification-Title'])) {
            $result['name'] = trim($manifest['Specification-Title']);
        }
        if (!empty($manifest['Implementation-Vendor']) && !$this->isPlaceholder($manifest['Implementation-Vendor'])) {
            $result['authors'] = [trim($manifest['Implementation-Vendor'])];
        } elseif (!empty($manifest['Specification-Vendor']) && !$this->isPlaceholder($manifest['Specification-Vendor'])) {
            $result['authors'] = [trim($manifest['Specification-Vendor'])];
        }

        return $result;
    }

    private function applyParseResult(array $parseResult): bool {
        $changed = false;
        if (!empty($parseResult['name']) && !$this->isPlaceholder($parseResult['name'])) {
            if (empty($this->name) || $this->isPlaceholder($this->name)) {
                $this->name = $parseResult['name'];
                $changed = true;
            }
        }
        if (!empty($parseResult['version']) && !$this->isPlaceholder($parseResult['version'])) {
            if (empty($this->version) || $this->isPlaceholder($this->version)) {
                $this->version = $parseResult['version'];
                $changed = true;
            }
        }
        if (!empty($parseResult['modId']) && !$this->isPlaceholder($parseResult['modId'])) {
            if (empty($this->modId) || $this->isPlaceholder($this->modId)) {
                $this->modId = $parseResult['modId'];
                $changed = true;
            }
        }
        if (!empty($parseResult['description']) && !$this->isPlaceholder($parseResult['description'])) {
            if (empty($this->description) || $this->isPlaceholder($this->description)) {
                $this->description = $parseResult['description'];
                $changed = true;
            }
        }
        if (!empty($parseResult['logoFile']) && !$this->isPlaceholder($parseResult['logoFile'])) {
            if (empty($this->logoFile) || $this->isPlaceholder($this->logoFile)) {
                $this->logoFile = $parseResult['logoFile'];
                $changed = true;
            }
        }
        if (!empty($parseResult['displayURL']) && !$this->isPlaceholder($parseResult['displayURL'])) {
            if (empty($this->displayURL) || $this->isPlaceholder($this->displayURL)) {
                $this->displayURL = $parseResult['displayURL'];
                $changed = true;
            }
        }
        if (!empty($parseResult['authors'])) {
            $rawAuthors = $parseResult['authors'];
            $authors = [];
            if (is_array($rawAuthors)) {
                foreach ($rawAuthors as $item) {
                    if (is_string($item) && !$this->isPlaceholder($item)) {
                        $parts = array_map('trim', explode(',', $item));
                        foreach ($parts as $p) {
                            if (!$this->isPlaceholder($p) && $p !== '') {
                                $authors[] = $p;
                            }
                        }
                    }
                }
            } elseif (is_string($rawAuthors) && !$this->isPlaceholder($rawAuthors)) {
                $parts = array_map('trim', explode(',', $rawAuthors));
                foreach ($parts as $p) {
                    if (!$this->isPlaceholder($p) && $p !== '') {
                        $authors[] = $p;
                    }
                }
            }
            if (!empty($authors) && (empty($this->authors) || (isset($this->authors[0]) && $this->isPlaceholder($this->authors[0])))) {
                $this->authors = $authors;
                $changed = true;
            }
        }
        return $changed;
    }

    private function parseFabricJson($raw) : array {
        $result = [];
        $jsonData = json_decode($raw, true);
        if (is_array($jsonData)) {
            $result['name'] = $jsonData['name'] ?? ($jsonData['id'] ?? null);
            $result['modId'] = $jsonData['id'] ?? null;
            $result['version'] = $jsonData['version'] ?? null;
            $result['authors'] = is_array($jsonData['authors'] ?? null) ? $jsonData['authors'] : [$jsonData['authors'] ?? []];
            if (!empty($jsonData['description'])) {
                $result['description'] = is_string($jsonData['description']) ? trim($jsonData['description']) : '';
            }
            if (!empty($jsonData['icon'])) {
                $result['logoFile'] = is_string($jsonData['icon']) ? trim($jsonData['icon']) : '';
            }
            if (!empty($jsonData['contact']['homepage'])) {
                $result['displayURL'] = trim($jsonData['contact']['homepage']);
            } elseif (!empty($jsonData['contact']['sources'])) {
                $result['displayURL'] = trim($jsonData['contact']['sources']);
            }
        }
        return $result;
    }

    private function parseNeoforgeToml($raw) : array {
        $result = [];
        if (!empty($raw)) {
            $tomlRaw = $raw;
            if (preg_match('/(?:displayName|name)\s*=\s*["\']([^"\']+)["\']/', $tomlRaw, $m)) {
                $result['name'] = trim($m[1]);
            }
            if (preg_match('/version\s*=\s*["\']([^"\']+)["\']/', $tomlRaw, $m)) {
                $result['version'] = trim($m[1]);
            }
            if (preg_match('/authors\s*=\s*["\']([^"\']+)["\']/', $tomlRaw, $m)) {
                $result['authors'] = [trim($m[1])];
            } elseif (preg_match('/authors\s*=\s*\[([^\]]+)\]/', $tomlRaw, $m)) {
                preg_match_all('/["\']([^"\']+)["\']/', $m[1], $authorMatches);
                if (!empty($authorMatches[1])) {
                    $result['authors'] = $authorMatches[1];
                }
            }
            if (preg_match('/modId\s*=\s*["\']([^"\']+)["\']/', $tomlRaw, $m)) {
                $result['modId'] = trim($m[1]);
            }
            if (preg_match('/description\s*=\s*(?:(?:\'\'\'|""")(.*?)(?:\'\'\'|""")|"([^"]*)"|\'([^\']*)\')/s', $tomlRaw, $m)) {
                $desc = $m[1] ?? ($m[2] ?? ($m[3] ?? ''));
                $result['description'] = trim($desc);
            }
            if (preg_match('/logoFile\s*=\s*["\']([^"\']+)["\']/', $tomlRaw, $m)) {
                $result['logoFile'] = trim($m[1]);
            }
            if (preg_match('/displayURL\s*=\s*["\']([^"\']+)["\']/', $tomlRaw, $m)) {
                $result['displayURL'] = trim($m[1]);
            }
        }
        return $result;
    }

    private function parseFilename(string $filename): array {
        $result = [];

        $basename = basename($filename, '.jar');
        // 去除 [前綴標籤] 及緊鄰的 +, -, _, 空格等符號
        $basename = preg_replace('/^\[[^\]]+\]\s*[\+\-_.]*\s*/u', '', $basename);

        $modName = $basename;
        $version = null;

        // 去除末尾的 loader/環境後綴 (例如 -forge, -neoforge, -fabric, -mc1.21.1)
        if (preg_match('/^(.+?)[-_\s]+((?:neoforge|forge|fabric|quilt)[\w.\+\-]*)$/i', $basename, $m)) {
            $modName = $m[1];
            $version = $m[2];
        } elseif (preg_match('/^(.+?)[-_\s]+v?(\d[\w.\+\-]*)$/i', $basename, $m)) {
            $modName = $m[1];
            $version = $m[2];
        }

        // 清理 modName 首尾符號
        $modName = trim($modName, "+-_ ");

        // 若 modName 結尾仍有 loader/MC 版本標籤，例如 "appleskin-neoforge-mc1.21" -> "appleskin"
        if (preg_match('/^(.+?)[-_\s]+(?:neoforge|forge|fabric|quilt|mc\d[\w.]*)/i', $modName, $sub)) {
            $modName = trim($sub[1], "+-_ ");
        }

        if (!empty($modName)) {
            $result['name'] = $modName;
        }
        if (!empty($version)) {
            $result['version'] = $version;
        }

        return $result;
    }

    public function getModId() : string {
        if (empty($this->modId) || $this->isPlaceholder($this->modId)) {
            $this->parse();
        }
        return $this->isPlaceholder($this->modId) ? '' : (string) $this->modId;
    }

    public function getDescription() : string {
        if (empty($this->description) || $this->isPlaceholder($this->description)) {
            $this->parse();
        }
        return $this->isPlaceholder($this->description) ? '' : (string) $this->description;
    }

    public function getLogoFile() : string {
        if (empty($this->logoFile)) {
            $this->parse();
        }
        return $this->logoFile;
    }

    public function getDisplayURL() : string {
        if (empty($this->displayURL)) {
            $this->parse();
        }
        return $this->displayURL;
    }

    public function getFileLength() : ?int {
        if ($this->fileLength === null) {
            $this->parse();
        }
        return $this->fileLength;
    }

    public function getFileDate() : ?string {
        if ($this->fileDate === null) {
            $this->parse();
        }
        return $this->fileDate;
    }

    public function setExtra(array $extra) : void {
        $this->extra = $extra;
    }

    public function getExtra() : array {
        return $this->extra;
    }

    public function fetchExtra() : bool {
        return false;
    }

    public function getCacheExtra() : array {
        return $this->extra;
    }

    public function saveCacheExtra() : bool {
        return false;
    }

    public function extractLocalIcon(string $targetDir): ?string {
        if (!file_exists($this->modFilePath)) {
            return null;
        }
        $sha1 = $this->getSha1();
        $iconFileName = $sha1 . '_thumb.png';
        $iconFilePath = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $iconFileName;

        if (file_exists($iconFilePath) && filesize($iconFilePath) > 0) {
            return $iconFileName;
        }

        $zip = new \ZipArchive();
        if ($zip->open($this->modFilePath) !== true) {
            return null;
        }

        $logoFileCandidates = [];
        $logoFile = $this->getLogoFile();
        $modId = $this->getModId();

        if (!empty($logoFile)) {
            $logoFileCandidates[] = $logoFile;
            $logoFileCandidates[] = ltrim($logoFile, '/');
            if (!empty($modId)) {
                $logoFileCandidates[] = 'assets/' . $modId . '/' . ltrim($logoFile, '/');
            }
        }
        // Fallback default candidate paths
        if (!empty($modId)) {
            $logoFileCandidates[] = 'assets/' . $modId . '/icon.png';
            $logoFileCandidates[] = 'assets/' . $modId . '/logo.png';
            $logoFileCandidates[] = 'assets/' . $modId . '/textures/gui/icon.png';
        }
        $logoFileCandidates[] = 'icon.png';
        $logoFileCandidates[] = 'logo.png';

        $iconData = false;
        foreach ($logoFileCandidates as $candidate) {
            $data = $zip->getFromName($candidate);
            if ($data !== false && strlen($data) > 0) {
                $iconData = $data;
                break;
            }
        }

        if ($iconData === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (str_starts_with($name, 'META-INF/jarjar/') && str_ends_with($name, '.jar')) {
                    $innerData = $zip->getFromIndex($i);
                    if ($innerData !== false) {
                        $tmp = tempnam(sys_get_temp_dir(), 'jij_icon_');
                        file_put_contents($tmp, $innerData);
                        $innerZip = new \ZipArchive();
                        if ($innerZip->open($tmp) === true) {
                            foreach ($logoFileCandidates as $candidate) {
                                $data = $innerZip->getFromName($candidate);
                                if ($data !== false && strlen($data) > 0) {
                                    $iconData = $data;
                                    break;
                                }
                            }
                            $innerZip->close();
                        }
                        @unlink($tmp);
                        if ($iconData !== false) {
                            break;
                        }
                    }
                }
            }
        }
        $zip->close();

        if ($iconData !== false) {
            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0775, true);
                @chmod($targetDir, 0775);
            }
            file_put_contents($iconFilePath, $iconData);
            @chmod($iconFilePath, 0666);
            return $iconFileName;
        }

        return null;
    }

    public function getName() : string {
        if (empty($this->name) || $this->isPlaceholder($this->name)) {
            $this->parse();
        }
        if ((empty($this->name) || $this->isPlaceholder($this->name)) && !empty($this->extra['display_name'])) {
            return (string) $this->extra['display_name'];
        }
        return $this->isPlaceholder($this->name) ? $this->getFileName() : (string) $this->name;
    }

    public function getVersion() : string {
        if (empty($this->version) || $this->isPlaceholder($this->version)) {
            $this->parse();
        }
        if ((empty($this->version) || $this->isPlaceholder($this->version)) && !empty($this->extra['version_number'])) {
            return (string) $this->extra['version_number'];
        }
        return $this->isPlaceholder($this->version) ? '' : (string) $this->version;
    }

    public function getAuthors() : array {
        if (empty($this->authors)) {
            $this->parse();
        }
        return $this->authors;
    }

    public function getFileName() : string {
        return $this->modFileName;
    }

    public function getSha1(): string
    {
        if (empty($this->sha1)) {
            $this->sha1 = sha1_file($this->modFilePath);
        }
        return $this->sha1;
    }

    public function getMd5(): string
    {
        if (empty($this->md5)) {
            $this->md5 = md5_file($this->modFilePath);
        }
        return $this->md5;
    }

    public function getBasePath() : string {
        $modFilePath = realpath($this->modFilePath) ?: $this->modFilePath;

        if (!empty($GLOBALS['config']['mods_path'])) {
            $configPath = realpath($GLOBALS['config']['mods_path']) ?: $GLOBALS['config']['mods_path'];
            if (str_contains($modFilePath, $configPath)) {
                return $GLOBALS['config']['mods_path'];
            }
        }

        if (!empty($GLOBALS['config']['mods'])
            && is_array($GLOBALS['config']['mods'])) {
                foreach ($GLOBALS['config']['mods'] as $modGroup) {
                    if (!empty($modGroup['path'])) {
                        $configPath = realpath($modGroup['path']) ?: $modGroup['path'];
                        if (str_contains($modFilePath, $configPath)) {
                            return $modGroup['path'];
                        }
                    }
            }
        }
        return '';
    }

    public function getConfigModsKey() : string {
        $modFilePath = realpath($this->modFilePath) ?: $this->modFilePath;

        if (!empty($GLOBALS['config']['mods'])
            && is_array($GLOBALS['config']['mods'])) {
                foreach ($GLOBALS['config']['mods'] as $modConfigKey => $modGroup) {
                    if (!empty($modGroup['path'])) {
                        $configPath = realpath($modGroup['path']) ?: $modGroup['path'];
                        if (str_contains($modFilePath, $configPath)) {
                            return $modConfigKey;
                        }
                    }
            }
        }
        return '';
    }

    public function getDownloadUrl() : string {

        $originFullPath = realpath($this->modFilePath) ?: $this->modFilePath;
        $basePath = realpath($this->getBasePath()) ?: $this->getBasePath();
        $relativePath = substr($originFullPath, strlen($basePath) + 1);

        $parts = explode('/', $relativePath);
        $encodedParts = array_map('rawurlencode', $parts); // rawurlencode 對於 URL path 更適合
        $encodedPath = implode('/', $encodedParts);

        if (!empty($GLOBALS['config']['mods_path'])
            && $basePath == $GLOBALS['config']['mods_path']) {

            $url = rtrim($GLOBALS['config']['base_url'], '/'). '/files/mods/'. $encodedPath;
            return $url;
            // return rtrim($GLOBALS['config']['base_url'], '/'). '/files/mods/'. urlencode($this->getFileName());
        }

        $configKey = $this->getConfigModsKey();
        if (!empty($GLOBALS['config']['mods'][$configKey])) {
            $url = rtrim($GLOBALS['config']['base_url'], '/'). rtrim($GLOBALS['config']['mods'][$configKey]['dl_urlpath'], '/'). '/'. $encodedPath;
            return $url;
        }

        return '';
    }

    function getWebsiteUrl() : string {
        return $this->displayURL ?: '';
    }

    public function outputBasic() : array {
        return [
            "name" => $this->getName(),
            "sha1" => $this->getSha1(),
            "fileName" => $this->getFileName(),
            "downloadUrl" => $this->getDownloadUrl(),
            "version" => $this->getVersion(),
            "authors" => $this->getAuthors(),
        ];
    }

    public function output() : array {
        $serverDownloadUrl = $this->getDownloadUrl();
        $sha1 = $this->getSha1();
        $md5 = $this->getMd5();
        $extra = $this->extra;

        $summary = !empty($extra['summary']) ? $extra['summary'] : $this->getDescription();
        $description = !empty($extra['description']) ? $extra['description'] : $this->getDescription();

        $source = $extra['source'] ?? 'server';
        $isCustomMod = !empty($extra['is_custom']) || ($source === 'server');

        // 智慧載點分流：若第三方 CDN 存在且非客製模組則優先導向 CDN，否則使用伺服器載點
        $remoteDownloadUrl = $extra['download_url'] ?? null;
        $primaryDownloadUrl = (!empty($remoteDownloadUrl) && !$isCustomMod) ? $remoteDownloadUrl : $serverDownloadUrl;

        $modrinthDownloadUrl = ($source === 'modrinth' && !empty($remoteDownloadUrl)) ? $remoteDownloadUrl : null;
        $curseForgeDownloadUrl = ($source === 'curseforge' && !empty($remoteDownloadUrl)) ? $remoteDownloadUrl : null;

        // 圖標網址建構
        $baseUrl = rtrim($GLOBALS['config']['base_url'] ?? '', '/');
        $localIconFile = $extra['logo']['local_icon'] ?? null;
        $localThumbFile = $extra['logo']['local_thumb'] ?? ($sha1 . '_thumb.png');

        $localIconUrl = !empty($localIconFile) ? $baseUrl . '/static/mod_icons/' . $localIconFile : null;
        $localThumbUrl = !empty($localThumbFile) && file_exists(ModMetadataFetcher::getIconsDir() . '/' . $localThumbFile)
            ? $baseUrl . '/static/mod_icons/' . $localThumbFile
            : null;

        // 若無高清圖則使用縮圖
        $effectiveLocalIconUrl = $localIconUrl ?: $localThumbUrl;

        $modrinthIconUrl = $extra['logo']['modrinth_url'] ?? null;
        $curseForgeIconUrl = $extra['logo']['curseforge_url'] ?? null;

        $websiteUrl = $extra['links']['website_url'] ?? ($this->displayURL ?: '');
        $modrinthUrl = $extra['links']['modrinth_url'] ?? null;
        $curseforgeUrl = $extra['links']['curseforge_url'] ?? null;
        $sourceUrl = $extra['links']['source_url'] ?? null;
        $issuesUrl = $extra['links']['issues_url'] ?? null;
        $wikiUrl = $extra['links']['wiki_url'] ?? null;

        return [
            // CurseForge API 對齊欄位
            "displayName" => $this->getName(),
            "fileName" => $this->getFileName(),
            "fileDate" => $this->getFileDate(),
            "fileLength" => $this->getFileLength(),
            "downloadUrl" => $primaryDownloadUrl,
            "hashes" => [
                [
                    "value" => $sha1,
                    "algo" => 1 // SHA1
                ],
                [
                    "value" => $md5,
                    "algo" => 2 // MD5
                ]
            ],
            "summary" => $summary,
            "description" => $description,
            "logo" => [
                "url" => $effectiveLocalIconUrl,
                "thumbnailUrl" => $localThumbUrl,
                "modrinthUrl" => $modrinthIconUrl,
                "curseForgeUrl" => $curseForgeIconUrl,
            ],
            "links" => [
                "websiteUrl" => $websiteUrl,
                "downloadUrl" => $primaryDownloadUrl,
                "serverDownloadUrl" => $serverDownloadUrl,
                "curseforgeUrl" => $curseforgeUrl,
                "modrinthUrl" => $modrinthUrl,
                "sourceUrl" => $sourceUrl,
                "issuesUrl" => $issuesUrl,
                "wikiUrl" => $wikiUrl,
            ],

            // 智慧分流與自製模組識別
            "serverDownloadUrl" => $serverDownloadUrl,
            "modrinthDownloadUrl" => $modrinthDownloadUrl,
            "curseForgeDownloadUrl" => $curseForgeDownloadUrl,
            "downloadSource" => $source,
            "isCustomMod" => $isCustomMod,
            "modId" => $this->getModId(),

            // 舊版與 Prism Launcher / ModUpdater 100% 向後相容欄位
            "name" => $this->getName(),
            "authors" => $this->getAuthors(),
            "version" => $this->getVersion(),
            "filename" => $this->getFileName(),
            "sha1" => $sha1,
            "download" => $primaryDownloadUrl,
            "websiteUrl" => $websiteUrl,
            "iconUrl" => $effectiveLocalIconUrl,
        ];
    }

    public function outputHtml() : string {
        $itemHtml = '
        <a href="'.$this->getDownloadUrl().'">'.$this->getName().'</a>
        ['.$this->getVersion().']
        by '.implode(', ', $this->getAuthors()).'
        ('.$this->getFileName().')
        ';
        return $itemHtml;
    }

}
