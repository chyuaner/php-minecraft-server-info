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

            if ($file->isFile() && str_ends_with($file->getFilename(), '.jar')) {
                $relativePath = substr($file->getPathname(), strlen($directory));
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

    public static function isFileExist($fileName) : bool {
        $directory = self::modsPath();
        $modFilePath = join(DIRECTORY_SEPARATOR, [rtrim($GLOBALS['config']['mods_path'], '/'), $fileName]);
        return file_exists($modFilePath);
    }
}
