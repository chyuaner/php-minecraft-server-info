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

    public function parse(): bool {
        $isSuccess = false;

        $zip = new \ZipArchive();
        if ($zip->open($this->modFilePath) === true) {

            // NeoForge
            $neoforgeTomlRaw = $zip->getFromName('META-INF/neoforge.mods.toml');
            if ($neoforgeTomlRaw !== false) {
                $parseResult = $this->parseNeoforgeToml($neoforgeTomlRaw);
                $isSuccess = $this->applyParseResult($parseResult) || $isSuccess;
            }

            // Forge (舊)
            $forgeTomlRaw = $zip->getFromName('META-INF/mods.toml');
            if ($forgeTomlRaw !== false) {
                // Forge與NeoForge幾乎相同，可沿用同一個解析器
                $parseResult = $this->parseNeoforgeToml($forgeTomlRaw);
                $isSuccess = $this->applyParseResult($parseResult) || $isSuccess;
            }

            // Fabric
            $fabricJsonRaw = $zip->getFromName('fabric.mod.json');
            if ($fabricJsonRaw !== false) {
                $parseResult = $this->parseFabricJson($fabricJsonRaw);
                $isSuccess = $this->applyParseResult($parseResult) || $isSuccess;
            }

            $zip->close();
        }

        // fallback: 檔名解析
        if (empty($this->name) || empty($this->version)) {
            $parseResult = $this->parseFilename($this->getFileName());
            $isSuccess = $this->applyParseResult($parseResult) || $isSuccess;
        }

        return $isSuccess;
    }

    private function applyParseResult(array $parseResult): bool {
        $changed = false;
        if (!empty($parseResult['name'])) {
            $this->name = $parseResult['name'];
            $changed = true;
        }
        if (!empty($parseResult['version'])) {
            $this->version = $parseResult['version'];
            $changed = true;
        }
        if (!empty($parseResult['authors'])) {
            $this->authors = $parseResult['authors'];
            $changed = true;
        }
        return $changed;
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
        $modFilePath = $this->modFilePath;

        if (!empty($GLOBALS['config']['mods_path'])
            && str_contains($modFilePath, $GLOBALS['config']['mods_path'])) {
            return $GLOBALS['config']['mods_path'];
        }
        elseif (!empty($GLOBALS['config']['mods'])
            && is_array($GLOBALS['config']['mods'])) {
                foreach ($GLOBALS['config']['mods'] as $modGroup) {
                    if (!empty($modGroup['path']
                    && str_contains($modFilePath, $modGroup['path']))) {
                        return $modGroup['path'];
                    }
            }
        }
        return '';
    }

    public function getConfigModsKey() : string {
        $modFilePath = $this->modFilePath;

        if (!empty($GLOBALS['config']['mods'])
            && is_array($GLOBALS['config']['mods'])) {
                foreach ($GLOBALS['config']['mods'] as $modConfigKey => $modGroup) {
                    if (!empty($modGroup['path'] && str_contains($modFilePath, $modGroup['path']))) {
                        return $modConfigKey;
                    }
            }
        }
        return '';
    }

    public function getDownloadUrl() : string {

        $originFullPath = $this->modFilePath;
        $basePath = $this->getBasePath();
        $relativePath = substr($originFullPath, strlen(realpath($basePath)) + 1);

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

    public function outputHtml() : string {
        $itemHtml = '
        <a href="'.$this->getDownloadUrl().'">'.$this->getName().'</a>
        ['.$this->getVersion().']
        by '.$this->getAuthors().'
        ('.$this->getFileName().')
        ';
        return $itemHtml;
    }

}
