<?php
namespace McModUtils;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use SplFileInfo;
use ZipArchive;

final class Mods
{
    private const ZIP_PATH = BASE_PATH.'/public/static/mods.zip';

    public static function modsPath() : string {
        return $GLOBALS['config']['mods_path'];
    }

    // 這個模組資料夾的資訊
    private $path;
    private $zip_path;
    private $ignorePrefixs = ['hide_'];
    private $onlyPrefixs = null;
    private $hashed;

    // 每個模組的陣列
    private $modsPathNameMap = [];

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
        $modFilePath = join(DIRECTORY_SEPARATOR, [rtrim($directory, '/'), $fileName]);
        return file_exists($modFilePath);
    }

    public static function parseFileInput(string $raw) : string {
        // TODO: 如果是帶入完整路徑
        if (false) {
            return $raw;
        }
        // 如果只帶檔案名稱
        else {
            return join(DIRECTORY_SEPARATOR, [rtrim(self::modsPath(), '/'), $raw]);
        }
    }

    public static function zipPath() : string {
        return self::ZIP_PATH;
    }

    public function getZipComment(string $zipPath = self::ZIP_PATH): ?string {
        if (!file_exists($zipPath)) return null;
        $zip = new ZipArchive();
        if ($zip->open($zipPath) === true) {
            $comment = $zip->getArchiveComment();
            $zip->close();
            return $comment;
        }
        return null;
    }

    public function zipFolder(string|null $source = null, string $zipPath = self::ZIP_PATH, string|null $comment = null): bool {
        if (empty($source)) { $source = $this->getModsPath(); }
        if (empty($comment)) { $comment = $this->getHashed(); }

        $zip = new ZipArchive();
        if (!$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            return false;
        }

        $zip->setArchiveComment($comment);

        $source = realpath($source);
        $sourceLen = strlen($source) + 1;

        $files = $this->getModPaths();

        foreach ($files as $filePath) {
            $file = new SplFileInfo($filePath);
            $relativePath = substr($filePath, $sourceLen);

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } elseif ($file->isFile()) {
                $zip->addFile($filePath, $relativePath);
            }
        }

        return $zip->close();
    }

}
