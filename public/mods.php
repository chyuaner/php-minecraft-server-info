<?php

use App\ResponseFormatter;
use McModUtils\Mod;
use McModUtils\Mods;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * @api {get} /mods 取得模組列表
 * @apiGroup Mods
 * @apiName getAllMods
 * @apiQuery {string="json","html"} [type=json] 指定要輸出的格式
 * @apiQuery {Boolean} [force=false] 不使用快取，強制刷新。
 * @apiHeader {String="text/html","application/json"} [Accept=application/json] 由Header控制要輸出的格式。若有在網址帶入 `type=json` 參數，則以網址參數為主
 *
 * @apiSuccessExample {json} JSON輸出
 *     HTTP/1.1 200 OK
 *     {
 *         "modsHash": "d9e9ae1ba3b4771ed389518777747fd38b641c25ef7a9a5ff2628e83d57f474d",
 *         "updateAt": "2025-07-27T14:52:10+08:00",
 *         "mods": [
 *             {
 *                 "name": "Apothic Attributes",
 *                 "authors": [
 *                     "Shadows_of_Fire"
 *                 ],
 *                 "version": "2.9.0",
 *                 "filename": "ApothicAttributes-1.21.1-2.9.0.jar",
 *                 "fileName": "ApothicAttributes-1.21.1-2.9.0.jar",
 *                 "sha1": "eed5808509eb279fd342cafebadd5b95accb4ef8",
 *                 "hashes": {
 *                     "value": "eed5808509eb279fd342cafebadd5b95accb4ef8",
 *                     "algo": 1
 *                 },
 *                 "download": "https:\/\/api-minecraft.yuaner.tw\/files\/mods\/ApothicAttributes-1.21.1-2.9.0.jar",
 *                 "downloadUrl": "https:\/\/api-minecraft.yuaner.tw\/files\/mods\/ApothicAttributes-1.21.1-2.9.0.jar"
 *             }
 *         ]
 *     }
 *
 * @apiSuccessExample {html} HTML輸出
 *     HTTP/1.1 200 OK
 *     <ul>
 *         <li>
 *             <a href="https://api-minecraft.yuaner.tw/files/mods/-damage-optimization-1.0.0%2B1.21.3.jar">傷害優化 Damage Optimization</a> [1.0.0+1.21.3] by Array (-damage-optimization-1.0.0+1.21.3.jar)
 *         </li>
 *     </ul>
 *
 * @apiExample 使用範例:
 *     https://api-minecraft.yuaner.tw/mods
 *     https://api-minecraft.yuaner.tw/mods/?type=json
 *     https://api-minecraft.yuaner.tw/mods/automodpack-mc1.21.1-neoforge-4.0.0-beta38.jar?type=json
 */
$app->get('/mods', function (Request $request, Response $response, array $args) {
    $queryParams = $request->getQueryParams();
    $formatter = new ResponseFormatter();
    $enableCache = $formatter->isJson($request);


    if (!empty($queryParams['force'])) {
        $enableCache = false;
    }

    // ------------------------------------------------------------------------

    $modsUtil = new Mods();
    $modsUtil->analyzeModsFolder();
    $formatter = new ResponseFormatter();

    // 若有啟用快取，就從快取抓
    if ($enableCache && $formatter->isJson($request)) {

        // 快取 $cacheFile
        $cacheFile = (BASE_PATH.'/public/static/mods.json');
        if (file_exists($cacheFile)) {

            // 檢查該資料夾有無被變動過
            $currentHash = $modsUtil->getHashed();
            $cache = json_decode(file_get_contents($cacheFile), true);
            if ($cache['modsHash'] == $currentHash) {

                $output = $cache;
                header('Content-Type: application/json; charset=utf-8');
                $outputRaw = json_encode($output);
                echo $outputRaw;
                exit;
            }
        }
    }

    $modsFileList = $modsUtil->getModNames();

    $modsOutput = [];
    foreach ($modsFileList as $modFileName) {
        $mod = new Mod($modFileName);
        array_push($modsOutput, $mod->output());
    }

    $now = new DateTime('now');
    $now->setTimezone(new DateTimeZone('Asia/Taipei'));

    $output = [
        "modsHash" => $modsUtil->getHashed(),
        "updateAt" => $now->format(DateTime::ATOM),
        "mods" => $modsOutput
    ];

    // 寫入快取
    $cacheFilePath = BASE_PATH.'/public/static/mods.json';
    $outputRaw = json_encode($output);
    file_put_contents($cacheFilePath, $outputRaw);

    // 輸出
    if (!$formatter->isJson($request)) {
        echo '<ul>';
        foreach ($modsFileList as $modFileName) {
            $mod = new Mod($modFileName);
            echo '<li>';
            echo $mod->outputHtml();
            echo '</li>';
        }
        echo '</ul>';
        exit;
    }
    return $formatter->format($request, $output);
});

/**
 * @api {get} /mods/:file 取得單一檔案模組資訊
 * @apiName getmod
 * @apiParam {String} file 伺服器上的Mod檔案名稱
 * @apiQuery {string="json","html"} [type=json] 指定要輸出的格式
 * @apiHeader {String="text/html","application/json"} [Accept=application/json] 由Header控制要輸出的格式。若有在網址帶入 `type=json` 參數，則以網址參數為主
 *
 * @apiGroup Mods
 *
 *
 * @apiExample 使用範例:
 *     https://api-minecraft.yuaner.tw/mods/automodpack-mc1.21.1-neoforge-4.0.0-beta38.jar
 *     https://api-minecraft.yuaner.tw/mods/automodpack-mc1.21.1-neoforge-4.0.0-beta38.jar?type=json
 *     https://api-minecraft.yuaner.tw/mods/automodpack-mc1.21.1-neoforge-4.0.0-beta38.jar?type=json&force=1
 */

$app->get('/mods/{filename}', function (Request $request, Response $response, array $args) {
    $formatter = new ResponseFormatter();
    $modFileName = $args['filename'];

    // ------------------------------------------------------------------------

    $modsUtil = new Mods();
    $modsUtil->analyzeModsFolder();

    $mod = new Mod($modFileName);
    if (!$formatter->isJson($request)) {
        $outputRaw = $mod->outputHtml();
        echo '<ul><li>'.$outputRaw.'</li></ul>';
        exit;
    }

    $output = [
        "modHash" => $mod->getSha1(),
        "mod" => $mod->output()
    ];
    $formatter = new ResponseFormatter();

    // 普通 route
    return $formatter->format($request, $output);
});

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
$app->get('/files/mods/{filename}', function (Request $request, Response $response, array $args) {
    header('X-Served-By: PHP-Mods-Stream');

    // $modFile = basename(urldecode($_SERVER['REQUEST_URI']));// e.g. curios.jar
    $basePath = '/files/mods/';
    $requestPath = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

    // 取得 mods 資料夾下的相對路徑
    $modFile = substr($requestPath, strlen($basePath));
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

});


/**
 * @api {get} /zip/mods 下載全部模組包
 * @apiName DownloadModsZip
 * @apiGroup Mods
 *
 * @apiDescription
 * 下載伺服器所使用的全部 mods 模組壓縮包，格式為 `.zip`。
 * 用於快速同步伺服器端與客戶端模組。
 *
 * @apiSampleRequest off
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
$app->get('/zip/mods', function (Request $request, Response $response, array $args) {
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
    $encodedFileName = rawurlencode($zipFileName);

    header('Content-Type: application/zip');
    header("Content-Disposition: attachment; filename=\"$zipFileName\"; filename*=UTF-8''$encodedFileName");
    header('Content-Length: ' . filesize($zipPath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    readfile($zipPath);
    exit;
});

