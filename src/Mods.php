<?php
namespace McModUtils;

final class Mods
{
    public static function modsPath() : string {
        return $GLOBALS['config']['mods_path'];
    }

    private $hashed;
    private $modNames;

    public function doHash() : string {
        return $this->hashed;
    }

    public function doModNames() : array {
        // 從設定檔取得mods資料夾路徑
        $directory = self::modsPath();

        // scandir寫法：取得所有mods檔名
        // $files = scandir($directory);
        // $files = array_diff(scandir($directory), array('..', '.'));

        // glob寫法：取得所有mods檔名
        $filePaths = glob($directory . '/*.jar');;
        $files = array_map('basename', $filePaths);

        $this->modNames = $files;
        return $files;
    }

    public function getModNames() : array {
        if (!isset($this->modNames)) {
            return $this->doModNames();
        }
        return $this->modNames;
    }

    public static function hash() : string {
        return '';
    }

    public static function mods() : array {
        $obj = new self();
        return $obj->getModNames();
    }
}
