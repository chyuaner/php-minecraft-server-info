<?php
namespace McModUtils;

use DateTime;
use DateTimeZone;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

final class Mods
{
    const CACHE_PATH = BASE_PATH.'/public/static/';

    // 這個模組資料夾的資訊
    private $path;
    private $ignorePrefixs = ['hide_'];
    private $onlyPrefixs = null;
    private $hashed;

    // 每個模組的陣列
    private $modsPathNameMap = [];

    public function __construct() {
        if (!empty($GLOBALS['config']['mods_path'])) {
            $this->path = $GLOBALS['config']['mods_path'];
        }
        elseif (!empty($GLOBALS['config']['mods']['common']['path'])) {
            $this->path = $GLOBALS['config']['mods']['common']['path'];
        }
    }

    public function setModsPath($path) {
        $this->path = $path;
        $this->resetCache();
    }

    public function setIsIgnoreServerside($bool) {
        if ($bool) {
            $this->ignorePrefixs = array_values(array_unique(array_merge($this->ignorePrefixs, $GLOBALS['config']['serverside_prefixs'])));
        } else {
            $this->ignorePrefixs = array_diff(array_unique(array_merge($this->ignorePrefixs, $GLOBALS['config']['serverside_prefixs'])));
        }
        $this->resetCache();
    }

    public function setIsOnlyServerside($bool) {
        if ($bool) {
            $this->onlyPrefixs = $GLOBALS['config']['serverside_prefixs'];
        } else {
            $this->onlyPrefixs = null;
        }

        $this->resetCache();
    }

    public function resetCache() {
        $this->hashed = null;
        $this->modsPathNameMap = [];
    }

    protected function getModsPath() {
        return $this->path;
    }

    public function analyzeModsFolder() {
        // 從設定檔取得mods資料夾路徑
        $directory = $this->getModsPath();

        $modsPathNameMap = [];
        $hashComponents = [];

        $ignoredDirs = ['.connector', '.index', '.git', 'logs', 'cache', 'luckperms'];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            foreach ($ignoredDirs as $ignore) {
                if (str_contains($file->getPath(), DIRECTORY_SEPARATOR . $ignore)) {
                    continue 2; // 跳過這筆資料
                }
            }

            $theFilename = $file->getFilename();
            $isProcressFilename = false;
            // 檔案副檔名必須是.jar
            if (str_ends_with($theFilename, '.jar') || str_ends_with($theFilename, '.jar.client')) {
                $onlyPrefixs = $this->onlyPrefixs;
                $isProcressFilename = false;
                if (!empty($onlyPrefixs) && is_array($onlyPrefixs)) {
                    foreach ($onlyPrefixs as $theOPrefix) {
                        if (str_starts_with($theFilename, $theOPrefix)) {
                            $isProcressFilename = true;
                            break;
                        }
                    }
                } else {
                    $ignorePrefixs = $this->ignorePrefixs;
                    $isProcressFilename = true;
                    foreach ($ignorePrefixs as $theIPrefix) {
                        if (str_starts_with($theFilename, $theIPrefix)) {
                            $isProcressFilename = false;
                            break;
                        }
                    }
                }
            }
            else {
                $isProcressFilename = false;
            }

            if ($file->isFile() && $isProcressFilename) {
                $relativePath = substr($file->getPathname(), strlen($directory));
                $mtime = $file->getMTime();
                $size = $file->getSize();

                $basePath = realpath($directory); // 確保是絕對路徑
                $modsPathNameMap[$file->getPathname()] = substr($file->getPathname(), strlen($basePath) + 1);

                $hashComponents[] = $relativePath . '|' . $mtime . '|' . $size;
            }
        }

        sort($hashComponents);

        $hash = hash('sha256', implode("\n", $hashComponents));

        $this->hashed = $hash;
        $this->modsPathNameMap = $modsPathNameMap;
    }

    public function getMetaHashed() : string {
        $hPath = md5(serialize($this->path));
        $hIgnorePrefixs = md5(serialize($this->ignorePrefixs));
        $hOnlyPrefixs = md5(serialize($this->onlyPrefixs));

        $hashed = md5($hPath . '|' . $hIgnorePrefixs . '|' . $hOnlyPrefixs);
        return $hashed;
    }

    public function getHashed() : string {
        if (!isset($this->hashed)) {
            $this->analyzeModsFolder();
        }
        return $this->hashed;
    }

    public function getModNames() : array {
        if (!isset($this->modNames)) {
            $this->analyzeModsFolder();
        }
        return array_values($this->modsPathNameMap);
    }

    public function getModPaths() : array {
        if (!isset($this->modNames)) {
            $this->analyzeModsFolder();
        }
        return array_keys($this->modsPathNameMap);
    }

    public function getMods($force = false, $enableCache = true) : array {
        if ($enableCache) {
            $cacheFilePath = self::CACHE_PATH.'/mods-'.$this->getMetaHashed().'.json';
            $thisHashed = $this->getHashed();

            if (file_exists($cacheFilePath) && !$force) {

                // 檢查該資料夾有無被變動過
                $currentHash = $thisHashed;
                $cache = json_decode(file_get_contents($cacheFilePath), true);
                if ($cache['folder_hashed'] == $currentHash) {
                    // 讀取快取內容
                    $mods = unserialize($cache['mods']);
                    return $mods;
                }
            }
        }

        // 正常抓取內容
        $modsFileList = $this->getModPaths();
        $mods = [];
        foreach ($modsFileList as $modFileName) {
            $mod = new Mod($modFileName);
            if ($enableCache) {
                $mod->parse();
                $mod->getMd5();
                $mod->getSha1();
            }
            $mods[] = $mod;
        }

        // 儲存進快取
        if ($enableCache) {
            $now = new DateTime('now');
            $now->setTimezone(new DateTimeZone('Asia/Taipei'));
            $cacheOutput = [
                'folder_hashed' => $thisHashed,
                'update_at' => $now->format(DateTime::ATOM),
                'mods' => serialize($mods)
            ];
            $cacheOutputRaw = json_encode($cacheOutput);
            file_put_contents($cacheFilePath, $cacheOutputRaw);
        }
        return $mods;
    }

    public function getCacheUpdateTime() : ?DateTime {
        $cacheFilePath = self::CACHE_PATH.'/mods-'.$this->getMetaHashed().'.json';
        if (file_exists($cacheFilePath)) {
            $cacheRaw = file_get_contents($cacheFilePath);
            $cache = json_decode($cacheRaw, true);
            if (!empty($cache['update_at'])) {
                $dt = new DateTime($cache['update_at']);
                return $dt;
            }
        }
        return null;
    }

    public static function hash() : string {
        $obj = new self();
        return $obj->getHashed();
    }

    public static function mods() : array {
        $obj = new self();
        return $obj->getModNames();
    }

}
