<?php
namespace McModUtils;

use DateTime;
use DateTimeZone;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class Folder
{
    const CACHE_PATH = BASE_PATH.'/public/static/';
    private $basePath;
    private $hashed;

    /**
     * 存取實際檔案用
     *
     * @var \SplFileInfo[]
     */
    protected array $files = [];

    /**
     * 檔案資訊快取
     *
     * @var array
     */
    protected $fileInfos = [];

    public function __construct($basePath) {
        $this->basePath = rtrim($basePath, '/');
    }

    public function getBasePath() {
        return $this->basePath;
    }

    public function setBasePath($path) {
        $this->basePath = rtrim($path, '/');
    }

    public function reset() {
        $this->files = [];
        $this->fileInfos = [];
    }

    public function getMetaHashed() : string {
        $hPath = md5($this->basePath);

        $hashed = $hPath;
        return $hashed;
    }

    protected function getHashed() : ?string {
        return $this->hashed;
    }

    private function getCacheFilePath(): string {
        return self::CACHE_PATH.'/folder-'.$this->getMetaHashed().'.json';
    }

    /**
     * 從快取檔讀出
     */
    public function loadCache(): bool {
        $cacheFilePath = $this->getCacheFilePath();

        if (file_exists($cacheFilePath)) {
            $raw = file_get_contents($cacheFilePath);
            if ($raw === false) {
                return false;
            }
            $data = json_decode($raw, true);
            if ($data === null) {
                return false;
            }

            if (!empty($data['fileInfos'])) {
                // 套用快取內容
                $this->fileInfos = $data['fileInfos'];
                $this->hashed = $data['folder_hashed'] ?? null;
                $this->check();
                return true;
            }
        }

        return false;
    }

    /**
     * 掃描資料夾並更新 $this->files 與 $this->fileInfos
     *
     * 行為要點：
     * - 若 $force 為 true，則會對每個檔案重新抓取需要的欄位（包含 md5/sha1）
     * - 若不是 force，會比對快取中的 mtime/size；相同則重用快取（僅補齊被要求但快取缺少的 md5/sha1）
     * - 掃描結束後會移除已刪除的快取項目
     * - 若 $enableCache 為 true，會呼叫 saveCache() 更新快取檔
     *
     * @param bool $fetchMeta 是否抓 mtime/size
     * @param bool $fetchMd5 是否抓 md5
     * @param bool $fetchSha1 是否抓 sha1
     * @param bool $force 強制重新計算（忽略快取）
     * @param bool $enableCache 掃描完成後是否寫回快取檔
     * @return array $this->files
     */
    public function fetchFilesRecursively($fetchMd5 = false, $fetchSha1 = false, $force = false, $enableCache = true) {
        // 嘗試從快取讀取
        if ($enableCache && !$force) {
            $this->loadCache();
        }
        $fetchMeta = true;

        $directory = $this->basePath;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $scanned = [];
        $hashComponents = [];

        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;

            $path = $file->getPathname();
            $scanned[$path] = true;

            // 更新或加入 SplFileInfo 物件（以路徑為 key）
            $this->files[$path] = $file;

            // 先取得目前 meta（若需要），避免多次 stat
            $currMtime = $file->getMTime();
            $currSize  = $file->getSize();
            $hashComponents[] = $path . '|' . $currMtime . '|' . $currSize;

            $needRefresh = $force || !isset($this->fileInfos[$path]);

            if (!$needRefresh && $fetchMeta && isset($this->fileInfos[$path]['mtime'], $this->fileInfos[$path]['size'])) {
                // 若快取存在且 meta 欄位可比較，檢查是否有變更
                if ($this->fileInfos[$path]['mtime'] !== $currMtime || $this->fileInfos[$path]['size'] !== $currSize) {
                    $needRefresh = true;
                }
            } elseif (!$needRefresh && $fetchMeta && (!isset($this->fileInfos[$path]['mtime']) || !isset($this->fileInfos[$path]['size']))) {
                // 快取沒有 meta，但 caller 要求 meta -> 需要刷新以取得 meta
                $needRefresh = true;
            }

            if (!$needRefresh) {
                // 可以重用快取：但若要求 md5/sha1 且快取缺少相應欄位，僅補齊缺的欄位
                $cached = $this->fileInfos[$path];
                $filled = false;
                if ($fetchMd5 && empty($cached['md5'])) {
                    $this->fileInfos[$path]['md5'] = md5_file($path);
                    $filled = true;
                }
                if ($fetchSha1 && empty($cached['sha1'])) {
                    $this->fileInfos[$path]['sha1'] = sha1_file($path);
                    $filled = true;
                }
                // 如 caller 要求 meta，但快取內沒有，填回現有 meta（避免重複 stat）
                if ($fetchMeta && (empty($cached['mtime']) || empty($cached['size']))) {
                    $this->fileInfos[$path]['mtime'] = $currMtime;
                    $this->fileInfos[$path]['size']  = $currSize;
                    $filled = true;
                }
                // 如果沒有任何補齊動作，直接跳過
                if (!$filled) {
                    continue;
                }
                // 若補齊過就已更新 fileInfos，繼續下一個檔案
                continue;
            }

            // 需要重新取得資訊（either force / 缺快取 / meta 不同）
            $this->fileInfos[$path] = $this->fetchFile($file, fetchMeta:$fetchMeta, fetchMd5:$fetchMd5, fetchSha1:$fetchSha1);
        }

        $hash = hash('sha256', implode("\n", $hashComponents));
        $this->hashed = $hash;


        // 移除已刪除（掃描不到）的快取項目
        $removed = array_diff_key($this->fileInfos, $scanned);
        if (!empty($removed)) {
            foreach (array_keys($removed) as $p) {
                unset($this->fileInfos[$p]);
            }
        }
        // 同步移除 $this->files 中不存在的路徑
        $removedFiles = array_diff_key($this->files, $scanned);
        if (!empty($removedFiles)) {
            foreach (array_keys($removedFiles) as $p) {
                unset($this->files[$p]);
            }
        }

        // 若啟用快取，將新的 fileInfos 寫回快取檔
        if ($enableCache) {
            $this->saveCache();
        }

        return $this->files;
    }

    /**
     * 儲存目前的 fileInfos 到 JSON 檔（可當作快取檔）
     */
    public function saveCache(): bool {
        if (!empty($this->fileInfos)) {
            $now = new DateTime('now');
            $now->setTimezone(new DateTimeZone('Asia/Taipei'));
            $data = [
                'basePath' => $this->basePath,
                'folder_hashed' => $this->getHashed(),
                'update_at' => $now->format(DateTime::ATOM),
                'fileInfos' => $this->fileInfos
            ];

            $cacheOutputRaw = json_encode($data);
            if ($cacheOutputRaw === false) return false;

            $cacheFilePath = $this->getCacheFilePath();
            file_put_contents($cacheFilePath, $cacheOutputRaw);
            return true;
        }
        return false;
    }

    private function check($fetchMeta = true, $fetchMd5 = false, $fetchSha1 = false) {
        // 若 files 空但有 fileInfos，建立 SplFileInfo（只建立缺的）
        if (empty($this->files) && !empty($this->fileInfos)) {
            foreach (array_keys($this->fileInfos) as $path) {
                if (!isset($this->files[$path])) {
                    $this->files[$path] = new \SplFileInfo($path);
                }
            }
            return true;
        }

        // 若 fileInfos 缺但有 files，僅為缺少的 files 產生資訊（避免重複計算）
        if (empty($this->fileInfos) && !empty($this->files)) {
            foreach ($this->files as $path => $file) {
                if (!isset($this->fileInfos[$path])) {
                    $this->fileInfos[$path] = $this->fetchFile($file, fetchMeta:$fetchMeta, fetchMd5:$fetchMd5, fetchSha1:$fetchSha1);
                }
            }
            return true;
        }

        return false;
    }

    protected function getDownloadSubPath() : string {
        if (!empty($GLOBALS['config']['other_folders'][$this->basePath])) {
            return trim($GLOBALS['config']['other_folders'][$this->basePath], '/\\');
        }
        return '';
    }

    private function fetchFile(SplFileInfo $file, $fetchMeta = false, $fetchMd5 = false, $fetchSha1 = false) {
        $theRawPath = $file->getPathname();

        // 將絕對路徑轉為相對於 $this->basePath 的相對路徑（若不在 basePath 之下則回傳去頭的路徑）
        $base = rtrim($this->basePath, '/\\');
        if ($base !== '' && strpos($theRawPath, $base) === 0) {
            $theSubPath = ltrim(substr($theRawPath, strlen($base)), '/\\');
        } else {
            // fallback：移除左側斜線，保留相對形式
            $theSubPath = ltrim($theRawPath, '/\\');
        }

        $thePath = basename($this->basePath).'/'.$theSubPath;
        $downloadUrl = $GLOBALS['config']['base_url'].'/'.$this->getDownloadSubPath().'/'.$theSubPath;
        $fileInfo = [
            'filename' => basename($theRawPath),
            'fileName' => basename($theRawPath),
            'path' => $thePath,
            'download' => $downloadUrl,
            'downloadUrl' => $downloadUrl,
        ];

        if ($fetchMeta) {
            $fileInfo['mtime'] = $file->getMTime();
            $fileInfo['size']  = $file->getSize();
        }

        if ($fetchMd5) {
            $fileInfo['md5'] = md5_file($theRawPath);
        }

        if ($fetchSha1) {
            $fileInfo['sha1'] = sha1_file($theRawPath);
        }

        return $fileInfo;
    }

    public function getFileInfos() {
        return $this->fileInfos;
    }
}
