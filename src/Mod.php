<?php
namespace McModUtils;

class Mod {
    protected $modFileName;
    protected $modFilePath;

    protected $name = '';
    protected $version = '';
    protected $authors = [];

    private function parseFileInput(string $raw) {
        // TODO: 如果是帶入完整路徑
        if (false) {
            return $raw;
        }
        // 如果只帶檔案名稱
        else {
            return join(DIRECTORY_SEPARATOR, [rtrim($GLOBALS['config']['mods_path'], '/'), $raw]);
        }
    }

    public function __construct(string $fileName) {
        $this->modFileName = basename($fileName);
        $this->modFilePath = $this->parseFileInput($fileName);
        if (!file_exists($this->modFilePath)) {
            throw new \Exception("Mod file not found: $this->modFilePath");
        }
    }

    public function parse() : bool {
        $isSuccess = false;

        // 開啟壓縮檔
        $zip = new \ZipArchive();
        if ($zip->open($this->modFilePath) === true) {

            // META-INF/neoforge.mods.toml
            $neoforgeTomlRaw = $zip->getFromName('META-INF/neoforge.mods.toml');

            if ($neoforgeTomlRaw !== false) {
                $parseResult = $this->parseNeoforgeToml($neoforgeTomlRaw);

                if (!empty($parseResult['name'])) {
                    $this->name = $parseResult['name'];
                    $isSuccess = true;
                }
                if (!empty($parseResult['version'])) {
                    $this->version = $parseResult['version'];
                    $isSuccess = true;
                }
                if (!empty($parseResult['authors'])) {
                    $this->authors = $parseResult['authors'];
                    $isSuccess = true;
                }
            }

            // fabric.mod.json
            $fabricJsonRaw = $zip->getFromName('fabric.mod.json');
            if ($fabricJsonRaw !== false) {
                $parseResult = $this->parseFabricJson($fabricJsonRaw);

                if (!empty($parseResult['name'])) {
                    $this->name = $parseResult['name'];
                    $isSuccess = true;
                }
                if (!empty($parseResult['version'])) {
                    $this->version = $parseResult['version'];
                    $isSuccess = true;
                }
                if (!empty($parseResult['authors'])) {
                    $this->authors = $parseResult['authors'];
                    $isSuccess = true;
                }
            }

            $zip->close();
        }

        // 若沒有相關資訊，就從檔名拆解處理
        if (empty($this->name) || empty($this->version)) {
            $parseResult = $this->parseFilename($this->getFileName());

            if (!empty($parseResult['name'])) {
                $this->name = $parseResult['name'];
                $isSuccess = true;
            }
            if (!empty($parseResult['version'])) {
                $this->version = $parseResult['version'];
                $isSuccess = true;
            }
        }

        return $isSuccess;
    }

    private function parseFabricJson($raw) : array {
        $result = [];
        $jsonData = json_decode($raw, true);
        $result['name'] = $jsonData['name'] ?? ($jsonData['id'] ?? null);
        $result['version'] = $jsonData['version'] ?? null;
        $result['authors'] = is_array($jsonData['authors']) ? $jsonData['authors'] : [$jsonData['authors'] ?? []];
        return $result;
    }

    private function parseNeoforgeToml($raw) : array {
        $result = [];
        // 有找到 /META-INF/neoforge.mods.toml 並解析成功
        if (!empty($raw)) {
            $tomlRaw = $raw;
            if (preg_match('/displayName\s*=\s*"([^"]+)"/', $tomlRaw, $m)) {
                $result['name'] = $m[1];
            }
            if (preg_match('/version\s*=\s*"([^"]+)"/', $tomlRaw, $m)) {
                $result['version'] = $m[1];
            }
            if (preg_match('/authors\s*=\s*"([^"]+)"/', $tomlRaw, $m)) {
                $result['authors'] = [trim($m[1])];
            }
        }
        return $result;
    }

    private function parseFilename(string $filename): array {
        $result = [];

        $basename = basename($filename, '.jar');
        $basename = preg_replace('/^\[[^\]]+\]\s*/u', '', $basename); // 去除前綴標籤

        $modName = $basename;
        $version = null;

        if (preg_match('/^(.+?)-((?:neoforge|forge|fabric)[\w.\+\-]*)$/i', $basename, $m)) {
            $modName = $m[1];
            $version = $m[2];
        } elseif (preg_match('/^(.+?)-(\d[\w.\+\-]*)$/', $basename, $m)) {
            $modName = $m[1];
            $version = $m[2];
        }

        if (!empty($modName)) {
            $result['name'] = $modName;
        }
        if (!empty($version)) {
            $result['version'] = $version;
        }

        return $result;
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
        if (empty($this->name)) {
            $this->parse();
        }
        return $this->name;
    }

    public function getVersion() : string {
        if (empty($this->version)) {
            $this->parse();
        }
        return $this->version;
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
        return sha1_file($this->modFilePath);
    }

    function getDownloadUrl() : string {
        return rtrim($GLOBALS['config']['base_url'], '/'). '/files/mods/'. urlencode($this->getFileName());
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
            "version" => $this->getVersion(),
            "authors" => $this->getAuthors(),
        ];
    }

    public function output() : array {
        return [
            "name" => $this->getName(), // Prism Launcher
            "authors" => $this->getAuthors(),  // Prism Launcher
            "version" => $this->getVersion(),  // Prism Launcher
            "filename" => $this->getFileName(),  // Prism Launcher
            "fileName" => $this->getFileName(),  // CurseForge API
            "sha1" => $this->getSha1(), // ModUpdater
            "hashes" => [ // CurseForge API
                "value" => $this->getSha1(),
                "algo" => 1
            ],
            // "url" => "https://www.curseforge.com/projects/889079", // Prism Launcher
            "download" => $this->getDownloadUrl(), // ModUpdater
            "downloadUrl" => $this->getDownloadUrl(), // CurseForge API
            // "websiteUrl" => "https://www.curseforge.com/minecraft/mc-mods/journey-into-the-light", // CurseForge API
            // "fileDate" => "2019-08-24T14:15:22Z", // CurseForge API
            // "fileLength" => 0, // CurseForge API
        ];
    }

}
