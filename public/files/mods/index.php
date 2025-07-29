<?php

use McModUtils\Mods;

require __DIR__ . '/../../../bootstrap.php';

/**
 * @api {get} /files/mods/:file 下載單一模組檔案
 * @apiGroup Mods
 * @apiName DownloadFile
 * @apiParam {String} file 伺服器上的Mod檔案名稱
 *
 * @apiSampleRequest off
 * @apiSuccess (Success 200) {File} jar 檔案，`Content-Type: application/java-archive`
 *
 * @apiSuccessExample {jar} 成功範例:
 *     HTTP/1.1 200 OK
 *     Content-Type: application/java-archive
 *     Content-Disposition: attachment; filename="automodpack-mc1.21.1-neoforge-4.0.0-beta38.jar"
 *     (二進位資料)
 *
 * @apiExample 使用範例:
 *     https://api-minecraft.yuaner.tw/files/mods/automodpack-mc1.21.1-neoforge-4.0.0-beta38.jar
 */

header('X-Served-By: PHP-Mods-Stream');

$modFile = basename(urldecode($_SERVER['REQUEST_URI']));// e.g. curios.jar
$modPath = Mods::parseFileInput($modFile);

if (!preg_match('/\.jar$/', $modFile)) {
    http_response_code(400);
    echo "Invalid request.";
    exit;
}

if (!Mods::isFileExist($modFile)) {
    http_response_code(404);
    echo "File not found.";
    exit;
}

// Optionally log download count here...

header('Content-Type: application/java-archive');
header('Content-Disposition: attachment; filename="' . basename($modPath) . '"');
header('Content-Length: ' . filesize($modPath));

readfile($modPath);
exit;
