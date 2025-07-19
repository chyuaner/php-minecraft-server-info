<?php
namespace McModUtils;

class Mod {
    protected $modFileName;
    protected $modFilePath;

    public function parseFileName(string $fileName) {
        // TODO: 如果是帶入完整路徑
        if (false) {
            return $fileName;
        }
        // 如果只帶檔案名稱
        else {
            return join(DIRECTORY_SEPARATOR, [rtrim($GLOBALS['config']['mods_path'], '/'), $fileName]);
        }
    }

    public function __construct(string $fileName) {
        $this->modFileName = $fileName;
        $this->modFilePath = $this->parseFileName($fileName);
    }

    public function parse() : array {
        return [];
    }

    // TODO: 待修
    private function parseJar(string $jarFile): ?array {
        $zip = new ZipArchive();
        if ($zip->open($jarFile) === true) {
            $filename = basename($jarFile);
            $mod = [
                'filename' => $filename,
                'name' => null,
                'version' => null,
                'authors' => [],
                'url' => null,
            ];

            // Fabric
            $jsonIndex = $zip->locateName('fabric.mod.json');
            if ($jsonIndex !== false) {
                $jsonData = json_decode($zip->getFromIndex($jsonIndex), true);
                $mod['name'] = $jsonData['name'] ?? ($jsonData['id'] ?? $filename);
                $mod['version'] = $jsonData['version'] ?? null;
                $mod['authors'] = is_array($jsonData['authors']) ? $jsonData['authors'] : [$jsonData['authors'] ?? ''];
            }

            // Forge/NeoForge
            $tomlIndex = $zip->locateName('META-INF/mods.toml');
            if ($tomlIndex !== false) {
                $tomlRaw = $zip->getFromIndex($tomlIndex);
                if (preg_match('/displayName\s*=\s*"([^"]+)"/', $tomlRaw, $m)) {
                    $mod['name'] = $m[1];
                }
                if (preg_match('/version\s*=\s*"([^"]+)"/', $tomlRaw, $m)) {
                    $mod['version'] = $m[1];
                }
                if (preg_match('/authors\s*=\s*"([^"]+)"/', $tomlRaw, $m)) {
                    $mod['authors'] = [trim($m[1])];
                }
            }

            // 試著推測 URL（如你有個內建映射資料庫可加強）
            $mod['url'] = $this->guessUrl($mod['name'], $filename);

            $zip->close();
            return $mod;
        }
        return null;
    }

    private function parseNeoforgeToml() : bool {
        // 有找到 /META-INF/neoforge.mods.toml 並解析成功
        if (false) {
            return true;
        } else {
            return false;
        }
    }

    public function fetchExtra() : bool {
        return false;
    }

    public function getCacheExtra() : array {
        return [];
    }

    public function saveCacheExtra() : bool {
        return false;
    }

    public function getName() : string {
        // 有找到 /META-INF/neoforge.mods.toml mods/version 並解析成功
        if (false) {
            # code...
        }
        // 直接從檔名拆字
        elseif (false) {
            # code...
        }
        // 無資訊，直接輸出檔名
        else {
            return $this->modFileName;
        }
    }

    public function getVersion() : string {
        // 有找到 /META-INF/neoforge.mods.toml mods/version 並解析成功
        if (false) {
            # code...
        }
        // 直接從檔名拆字
        elseif (false) {
            # code...
        }
        // 無資訊，輸出空字串
        else {
            return '';
        }
    }

    public function getAuthors() : array {
        // 有找到 /META-INF/neoforge.mods.toml mods/version 並解析成功
        if (false) {
            # code...
        }
        // 直接從檔名拆字
        elseif (false) {
            # code...
        }
        // 無資訊，輸出空陣列
        else {
            return [];
        }
    }

    public function getFileName() : string {
        return $this->modFileName;
    }

    public function getSha1(): string
    {
        return sha1_file($this->modFilePath);
    }

    function getDownloadUrl() : string {
        return rtrim($GLOBALS['config']['base_url'], '/'). '/files/mods/'. urlencode($this->modFileName);
    }

    function getWebsiteUrl() : string {
        return '';
    }

    public function outputBasic() : array {
        return [
            "name" => $this->getName(),
            "sha1" => $this->getSha1(),
            "fileName" => $this->getFileName(),
            // "filePath" => $this->modFilePath,
            "downloadUrl" => $this->getDownloadUrl(), // CurseForge API
        ];
    }

    public function output() : array {
        return [
            "name" => "journey-into-the-light", // Prism Launcher
            "authors" => [ // Prism Launcher
                "Sinytra, FabricMC"
            ],
            "version" => "0.115.6+2.1.1+1.21.1", // Prism Launcher
            "filename" => "journey-into-the-light-1.3.2.jar", // Prism Launcher
            "fileName" => "journey-into-the-light-1.3.2.jar", // CurseForge API
            "sha1" => "abc123...", // ModUpdater
            "hashes" => [ // CurseForge API
                "value" => "abc123...",
                "algo" => 1
            ],
            "url" => "https://www.curseforge.com/projects/889079", // Prism Launcher
            "download" => "https://media.forgecdn.net/files/1234/567/journey-into-the-light-1.3.2.jar", // ModUpdater
            "downloadUrl" => "https://media.forgecdn.net/files/1234/567/journey-into-the-light-1.3.2.jar", // CurseForge API
            "websiteUrl" => "https://www.curseforge.com/minecraft/mc-mods/journey-into-the-light", // CurseForge API
            "fileDate" => "2019-08-24T14:15:22Z", // CurseForge API
            "fileLength" => 0, // CurseForge API
        ];
    }

}
