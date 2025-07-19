<?php
namespace McModUtils;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

final class Mods
{
    public static function modsPath() : string {
        return $GLOBALS['config']['mods_path'];
    }

    private $hashed;
    private $modNames;

    // public function doModNames() : array {
    //     // 從設定檔取得mods資料夾路徑
    //     $directory = self::modsPath();

    //     // scandir寫法：取得所有mods檔名
    //     // $files = scandir($directory);
    //     // $files = array_diff(scandir($directory), array('..', '.'));

    //     // glob寫法：取得所有mods檔名
    //     $filePaths = glob($directory . '/*.jar');;
    //     $files = array_map('basename', $filePaths);

    //     $this->modNames = $files;
    //     return $files;
    // }

    public function analyzeModsFolder() {
        // 從設定檔取得mods資料夾路徑
        $directory = self::modsPath();

        $files = [];
        $hashComponents = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.jar')) {
                $relativePath = substr($file->getPathname(), strlen($dir));
                $mtime = $file->getMTime();
                $size = $file->getSize();

                $files[] = basename($file->getPathname()); // 存檔名
                $hashComponents[] = $relativePath . '|' . $mtime . '|' . $size;
            }
        }

        sort($hashComponents);
        sort($files);

        $hash = hash('sha256', implode("\n", $hashComponents));

        $this->hashed = $hash;
        $this->modNames = $files;
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
        return $this->modNames;
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
