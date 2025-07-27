<?php
require __DIR__ . '/../../../bootstrap.php';

use McModUtils\Mods;

/**
 * @api {get} /zip/mods 下載全部模組包
 * @apiName DownloadModsZip
 * @apiGroup Mods
 *
 * @apiDescription
 * 下載伺服器所使用的全部 mods 模組壓縮包，格式為 `.zip`。
 * 用於快速同步伺服器端與客戶端模組。
 *
 *
 * @apiSuccess (Success 200) {File} zip 壓縮檔案，`Content-Type: application/zip`
 *
 * @apiSuccessExample {zip} 成功範例:
 *     HTTP/1.1 200 OK
 *     Content-Disposition: attachment; filename="BarianMcMods整合包-20250727-0906.zip"
 *     Content-Type: application/zip
 *     (二進位資料)
 *
 * @apiExample 使用範例:
 *     curl -O https://api-minecraft.yuaner.tw/zip/mods
 */

$modsUtil = new Mods();

$folderHash = $modsUtil->getHashed();
$zipedHash = $modsUtil->getZipComment();

if (!empty($folderHash) && $folderHash !== $zipedHash) {
    $modsUtil->zipFolder();
}

$zipPath = $modsUtil->zipPath();
if (!file_exists($zipPath)) {
    http_response_code(404);
    echo "ZIP 檔案不存在";
    return;
}

$zipMTime = (new DateTime())->setTimestamp(filemtime($zipPath));
$zipMTime->setTimezone(new DateTimeZone('Asia/Taipei'));
$zipFileName = 'BarianMcMods整合包-'.$zipMTime->format("Ymd-Hi").'.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
header('Content-Length: ' . filesize($zipPath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
readfile($zipPath);
exit;
