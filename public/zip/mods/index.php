<?php
require __DIR__ . '/../../../bootstrap.php';

use McModUtils\Mods;

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
$zipFileName = 'McMods-'.$zipMTime->format("Ymd-Hi").'.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
header('Content-Length: ' . filesize($zipPath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
readfile($zipPath);
exit;
