<?php
namespace McModUtils;

use DateTime;
use DateTimeZone;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class ResourcePackUtils
{
    const CACHE_PATH = BASE_PATH.'/public/static/';

    private $path;
    private $hashed;
    private $items = []; // List of zip files or directories

    public function __construct(string $path = null) {
        if ($path) {
            $this->path = $path;
        } elseif (!empty($GLOBALS['config']['resourcepacks']['path'])) {
            $this->path = $GLOBALS['config']['resourcepacks']['path'];
        }
    }

    public function setPath(string $path) {
        $this->path = $path;
        $this->hashed = null;
        $this->items = [];
    }

    public function getPath() {
        return $this->path;
    }

    public function getMetaHashed() : string {
        return md5($this->path);
    }

    public function getHashed() : string {
        if (!isset($this->hashed)) {
            $this->analyze();
        }
        return $this->hashed ?? '';
    }

    public function getItems() : array {
        if (empty($this->items)) {
            $this->analyze();
        }
        return $this->items;
    }

    // Keep getFiles for backward compatibility with previous version of this class if needed
    public function getFiles() : array {
        return $this->getItems();
    }

    public function analyze($force = false) {
        if (empty($this->path) || !is_dir($this->path)) {
            return;
        }

        // Try load from cache
        if (!$force && $this->loadCache()) {
           return;
        }

        $items = [];
        $hashComponents = [];

        $iterator = new \DirectoryIterator($this->path);
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDot()) continue;
            
            $isZip = $fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'zip';
            $isRPDir = $fileInfo->isDir() && file_exists($fileInfo->getPathname() . '/pack.mcmeta');

            if ($isZip || $isRPDir) {
                $items[] = $fileInfo->getPathname();
                // For directories, mtime is not very reliable recursively, but it's a start
                // Using folder mtime + 'dir' tag
                $hashComponents[] = $fileInfo->getFilename() . '|' . $fileInfo->getMTime() . '|' . ($isZip ? $fileInfo->getSize() : 'dir');
            }
        }

        // Sort items alphabetically to ensure consistent merge order
        sort($items);
        sort($hashComponents);

        $this->items = $items;
        $this->hashed = hash('sha256', implode("\n", $hashComponents));

        $this->saveCache();
    }

    private function getCacheFilePath(): string {
        return self::CACHE_PATH.'/resourcepacks-'.$this->getMetaHashed().'.json';
    }

    public function loadCache(): bool {
        $cacheFilePath = $this->getCacheFilePath();
        if (file_exists($cacheFilePath)) {
            $data = json_decode(file_get_contents($cacheFilePath), true);
            if ($data) {
                $this->items = $data['items'] ?? $data['files'] ?? [];
                $this->hashed = $data['hashed'];
                return true;
            }
        }
        return false;
    }

    public function saveCache(): bool {
        $data = [
            'path' => $this->path,
            'hashed' => $this->hashed,
            'update_at' => (new DateTime('now', new DateTimeZone('Asia/Taipei')))->format(DateTime::ATOM),
            'items' => $this->items
        ];
        return file_put_contents($this->getCacheFilePath(), json_encode($data)) !== false;
    }

    /**
     * Merge all detected items into a single zip at the destination path.
     * 
     * @param string $destinationZipPath
     * @return bool
     */
    public function mergeTo(string $destinationZipPath): bool {
        $items = $this->getItems();
        if (empty($items)) {
            return false;
        }

        // Create a temporary directory
        $tempDir = sys_get_temp_dir() . '/rp_merge_' . uniqid();
        if (!mkdir($tempDir, 0777, true)) {
            return false;
        }

        // Process all items in order (later overwrites earlier)
        foreach ($items as $itemPath) {
            if (is_dir($itemPath)) {
                $this->copyDir($itemPath, $tempDir);
            } else {
                $zip = new Zip($itemPath);
                $zip->unzip($tempDir);
            }
        }

        // Create the final zip
        $zipUtil = new Zip($destinationZipPath);
        
        // Scan the temp directory to get all files to zip
        $filesToZip = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $filesToZip[] = $file->getPathname();
            }
        }

        // Use the Zip utility to pack them
        $result = $zipUtil->zipFolder($tempDir, $filesToZip, $this->getHashed());

        // Cleanup temp directory
        $this->deleteDir($tempDir);

        return $result;
    }

    private function copyDir($src, $dst) {
        if (!is_dir($src)) return;
        @mkdir($dst, 0777, true);
        $dir = opendir($src);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if ( is_dir($src . '/' . $file) ) {
                    $this->copyDir($src . '/' . $file, $dst . '/' . $file);
                }
                else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    private function deleteDir($dirPath) {
        if (!is_dir($dirPath)) {
            return;
        }
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }
        $files = glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->deleteDir($file);
            } else {
                unlink($file);
            }
        }
        rmdir($dirPath);
    }
}
