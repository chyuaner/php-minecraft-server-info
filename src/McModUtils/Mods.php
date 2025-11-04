<?php
namespace McModUtils;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use SplFileInfo;
use ZipArchive;

final class Mods
{
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

        $ignoredDirs = ['.connector', '.index', '.git', 'logs', 'cache'];
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

    public function getMods() {
        // $this->analyzeModsFolder();
        $modsFileList = $this->getModPaths();
        $mods = [];
        foreach ($modsFileList as $modFileName) {
            $mod = new Mod($modFileName);
            $mods[] = $mod;
        }
        return $mods;
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
