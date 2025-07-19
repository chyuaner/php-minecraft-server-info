<?php
namespace McModUtils;

class Mod {
    protected $modFilePath;

    public function parseFileName(string $fileName) {
        // TODO: 如果是帶入完整路徑
        if (false) {
            return $fileName;
        }
        // 如果只帶檔案名稱
        else {
            return rtrim($GLOBALS['config']['mods_path'], '/').$fileName;
        }
    }

    public function __construct(string $fileName) {
        $this->modFilePath = $this->parseFileName($fileName);
    }

    public function parse() : array {
        return [];
    }

    public function fetchExtra() : bool {
        return false;
    }

    public function getCacheExtra() : array {
        return [];
    }

    public function saveCacheExtra() : bool {
        return false;
    }

}
