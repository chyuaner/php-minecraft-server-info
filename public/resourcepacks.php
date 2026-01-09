<?php

use App\ResponseFormatter;
use McModUtils\ResourcePackUtils;
use McModUtils\Zip;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/resourcepacks', function (RouteCollectorProxy $group) {

    /**
     * @api {get} /resourcepacks/zip 下載合併後的資源包
     * @apiName DownloadResourcePackZip
     * @apiGroup ResourcePacks
     * @apiQuery {Boolean} [force=false] 不使用快取，強制重新壓縮。
     *
     * @apiDescription
     * 下載伺服器指定的 Resource Packs 資料夾內所有 ZIP 合併後的資源包。
     * 適用於 server.properties 的 resource-pack 設定 (僅支援單一連結)。
     * 合併順序依檔案名稱排序 (A-Z)，後者覆蓋前者。
     *
     * @apiSampleRequest off
     * @apiSuccess (Success 200) {File} zip 壓縮檔案，`Content-Type: application/zip`
     *
     * @apiSuccessExample {zip} 成功範例:
     *     HTTP/1.1 200 OK
     *     Content-Disposition: attachment; filename="ServerResourcePack-20250727-0906.zip"
     *     Content-Type: application/zip
     *     (二進位資料)
     *
     * @apiExample 使用範例:
     *     curl -O https://mc-api.yuaner.tw/resourcepacks/zip
     */
    $group->get('/zip', function (Request $request, Response $response, array $args) {
        $isForce = $request->getQueryParams()['force'] ?? false;
        
        // 檢查設定是否存在
        if (empty($GLOBALS['config']['resourcepacks']['path'])) {
            $response->getBody()->write("Config 'resourcepacks.path' is not set.");
            return $response->withStatus(500);
        }

        $rpUtils = new ResourcePackUtils();
        $rpUtils->analyze();
        
        // 計算 Hash
        $folderHash = $rpUtils->getHashed();
        if (empty($folderHash)) {
             $response->getBody()->write("No zip files found in resource packs directory.");
             return $response->withStatus(404);
        }

        $zip_path = BASE_PATH.'/public/static/resourcepacks-'.$folderHash.'.zip';
        $zip = new Zip($zip_path);
        $zipedHash = $zip->getZipComment();

        // Check if rebuild is needed
        if ($isForce || $folderHash !== $zipedHash || !file_exists($zip_path)) {
            // Merge logic
            // Increase time limit for large merges
            set_time_limit(0); 
            $success = $rpUtils->mergeTo($zip_path);
            if (!$success) {
                $response->getBody()->write("Failed to merge resource packs.");
                return $response->withStatus(500);
            }
        }

        if (!file_exists($zip_path)) {
            $response->getBody()->write("ZIP file creation failed.");
            return $response->withStatus(500);
        }

        $zipMTime = (new DateTime())->setTimestamp(filemtime($zip_path));
        $zipMTime->setTimezone(new DateTimeZone('Asia/Taipei'));
        $zipFileName = 'ServerResourcePack-'.$zipMTime->format("Ymd-Hi").'.zip';
        $encodedFileName = rawurlencode($zipFileName);

        header('Content-Type: application/zip');
        header("Content-Disposition: attachment; filename=\"$zipFileName\"; filename*=UTF-8''$encodedFileName");
        header('Content-Length: ' . filesize($zip_path));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('X-Served-By: PHP');
        readfile($zip_path);
        exit;
    });

    /**
     * @api {get} /resourcepacks/sha1 取得合併後資源包的 SHA1 值
     * @apiName GetResourcePackSha1
     * @apiGroup ResourcePacks
     * @apiDescription
     * 取得合併後資源包的 SHA1 雜湊值。
     * 用於填充 server.properties 中的 resource-pack-sha1 欄位。
     *
     * @apiSuccessExample {String} 成功範例:
     *     HTTP/1.1 200 OK
     *     43e191ba879bb774b42e3b030659e4be...
     *
     * @apiExample 使用範例:
     *     curl https://mc-api.yuaner.tw/resourcepacks/sha1
     */
    $group->get('/sha1', function (Request $request, Response $response, array $args) {
        if (empty($GLOBALS['config']['resourcepacks']['path'])) {
            $response->getBody()->write("Config 'resourcepacks.path' is not set.");
            return $response->withStatus(500);
        }

        $rpUtils = new ResourcePackUtils();
        $rpUtils->analyze();
        $folderHash = $rpUtils->getHashed();
        if (empty($folderHash)) {
            $response->getBody()->write("No items found.");
            return $response->withStatus(404);
        }

        $zip_path = BASE_PATH.'/public/static/resourcepacks-'.$folderHash.'.zip';
        $zip = new Zip($zip_path);
        $zipedHash = $zip->getZipComment();

        // 如果檔案不存在或雜湊不符，重新產生
        if ($folderHash !== $zipedHash || !file_exists($zip_path)) {
            set_time_limit(0);
            $rpUtils->mergeTo($zip_path);
        }

        if (file_exists($zip_path)) {
            $sha1 = sha1_file($zip_path);
            $response->getBody()->write($sha1);
            return $response->withHeader('Content-Type', 'text/plain');
        }

        $response->getBody()->write("Failed to calculate SHA1.");
        return $response->withStatus(500);
    });

});
