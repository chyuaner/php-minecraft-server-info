<?php
namespace McModUtils;

final class Mods
{
    public static function modsPath() : string {
        return $GLOBALS['config']['mods_path'];
    }

    public static function mods() : array {
        // 從設定檔取得mods資料夾路徑
        $directory    = self::modsPath();

        // scandir寫法：取得所有mods檔名
        // $files = scandir($directory);
        // $files = array_diff(scandir($directory), array('..', '.'));

        // glob寫法：取得所有mods檔名
        $filePaths = glob($directory . '/*.jar');;
        $files = array_map('basename', $filePaths);

        return $files;
    }
}
