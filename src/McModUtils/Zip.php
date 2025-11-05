<?php
namespace McModUtils;

use SplFileInfo;
use ZipArchive;

class Zip
{
    private $path;

    public function __construct($zipPath) {
        $this->path = $zipPath;
    }

    public function setZipPath(string $zipPath) : void {
        $this->path = $zipPath;
    }

    public function getZipPath() : string {
        return $this->path;
    }

    public function getZipComment(): ?string {
        $zipPath = $this->path;
        if (!file_exists($zipPath)) return null;
        $zip = new ZipArchive();
        if ($zip->open($zipPath) === true) {
            $comment = $zip->getArchiveComment();
            $zip->close();
            return $comment;
        }
        return null;
    }

    public function zipFolder(string $sourceBasePath, array $filePaths, string|null $comment = null): bool {
        $zipPath = $this->path;

        $zip = new ZipArchive();
        if (!$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            return false;
        }

        $zip->setArchiveComment($comment);

        $source = realpath($sourceBasePath);
        $sourceLen = strlen($source) + 1;

        $files = $filePaths;

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

    public function zipRelativePath(array $mAddFiles, string|null $comment = null): bool {
        $zipPath = $this->path;

        $zip = new ZipArchive();
        if (!$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            return false;
        }

        $zip->setArchiveComment($comment);

        // 紀錄已建立的目錄，避免重複 addEmptyDir
        $addedDirs = [];

        foreach ($mAddFiles as $filePath => $relativePath) {
            // 正規化路徑（相對路徑內不應有前導斜線）
            $rel = ltrim(str_replace('\\', '/', $relativePath), '/');

            // 若目標是資料夾：建立空目錄條目（不遞迴加入內容，若要遞迴請改為遞迴加入）
            if (is_dir($filePath)) {
                if ($rel !== '' && !isset($addedDirs[$rel])) {
                    $zip->addEmptyDir($rel);
                    $addedDirs[$rel] = true;
                }
                continue;
            }

            // 若目標是檔案，先確保父目錄存在於 zip
            if (is_file($filePath)) {
                $dir = dirname($rel);
                if ($dir !== '.' && $dir !== '' && !isset($addedDirs[$dir])) {
                    // 逐層建立父目錄
                    $parts = explode('/', $dir);
                    $acc = '';
                    foreach ($parts as $p) {
                        $acc = ($acc === '') ? $p : $acc . '/' . $p;
                        if (!isset($addedDirs[$acc])) {
                            $zip->addEmptyDir($acc);
                            $addedDirs[$acc] = true;
                        }
                    }
                }

                $zip->addFile($filePath, $rel);
                continue;
            }

            // 檔案或資料夾不存在則跳過
            continue;
        }

        return $zip->close();
    }
}
