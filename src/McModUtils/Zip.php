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
}
