<?php
// 要同步的

// http://localhost:8000/mods (old)
// http://localhost:8000/mods/:file (old)
// http://localhost:8000/mods/:file/download
// http://localhost:8000/mods/zip
// [mods = common-mods]

// http://localhost:8000/client-mods
// http://localhost:8000/client-mods/:file
// http://localhost:8000/client-mods/:file/download
// http://localhost:8000/client-mods/zip

// ---
// 參考用的

// http://localhost:8000/server-mods
// http://localhost:8000/server-mods/:file
// http://localhost:8000/server-mods/:file/download
// http://localhost:8000/server-mods/zip

// http://localhost:8000/all-mods
// http://localhost:8000/all-mods/:file
// http://localhost:8000/all-mods/:file/download
// http://localhost:8000/all-mods/zip

/**
 * @apiDefine McModTypes
 * @apiParam {String="mods","client-mods","server-mods"} [modType]
 */

use App\ResponseFormatter;
use McModUtils\Mod;
use McModUtils\Mods;
use McModUtils\Zip;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$routerConfigMap = [
    'mods' => 'common',
    'client-mods' => 'client',
    'server-mods' => 'server',
];

foreach ($routerConfigMap as $modType => $modConfigKey) {

    $app->group("/$modType", function (RouteCollectorProxy $group) use ($modConfigKey) {

        /**
         * @api {get} /:modType/zip 下載全部模組包
         * @apiName DownloadModsZip
         * @apiGroup Mods
         * @apiUse McModTypes
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
        $group->get('/zip', function (Request $request, Response $response, array $args) use ($modConfigKey) {

            $zip_path = BASE_PATH.'/public/static/mods-'.$modConfigKey.'.zip';
            $isForce = $request->getQueryParams()['force'] ?? false;


            // 拉出模組清單
            $config = $GLOBALS['config']['mods'];
            $baseModsPath = $config[$modConfigKey]['path'];
            $modsUtil = new Mods();
            $modsUtil->setModsPath($baseModsPath);
            $modsUtil->setIsIgnoreServerside($config[$modConfigKey]['ignore_serverside_prefix']);
            $modsUtil->setIsOnlyServerside($config[$modConfigKey]['only_serverside_prefix']);
            $modsUtil->analyzeModsFolder();

            $zip = new Zip($zip_path);

            $folderHash = $modsUtil->getHashed();
            $zipedHash = $zip->getZipComment();

            // 若壓縮檔寫在註解內的校驗碼不一致
            if ($isForce || (!empty($folderHash) && $folderHash !== $zipedHash)) {

                $filePaths = $modsUtil->getModPaths();
                $zip->zipFolder($baseModsPath, $filePaths, $folderHash);
            }

            if (!file_exists($zip_path)) {
                http_response_code(404);
                echo "ZIP 檔案不存在";
                return;
            }

            $zipMTime = (new DateTime())->setTimestamp(filemtime($zip_path));
            $zipMTime->setTimezone(new DateTimeZone('Asia/Taipei'));
            $zipFileName = 'BarianMcMods整合包'.$modConfigKey.'-'.$zipMTime->format("Ymd-Hi").'.zip';
            $encodedFileName = rawurlencode($zipFileName);

            header('Content-Type: application/zip');
            header("Content-Disposition: attachment; filename=\"$zipFileName\"; filename*=UTF-8''$encodedFileName");
            header('Content-Length: ' . filesize($zip_path));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            readfile($zip_path);
            exit;
        });


        /**
         * @api {get} /:modType 取得模組列表
         * @apiGroup Mods
         * @apiName getAllMods
         * @apiUse McModTypes
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
        $group->get('', function (Request $request, Response $response, array $args) use ($modConfigKey) {
            $queryParams = $request->getQueryParams();

            // 拉出模組清單
            $config = $GLOBALS['config']['mods'];
            $baseModsPath = $config[$modConfigKey]['path'];
            $modsUtil = new Mods();
            $modsUtil->setModsPath($baseModsPath);
            $modsUtil->setIsIgnoreServerside($config[$modConfigKey]['ignore_serverside_prefix']);
            $modsUtil->setIsOnlyServerside($config[$modConfigKey]['only_serverside_prefix']);
            $modsUtil->analyzeModsFolder();
            $modsFileList = $modsUtil->getModPaths();

            $modsOutput = [];
            if (!empty($queryParams['barian'])) {
                foreach ($modsFileList as $modFileName) {
                    $mod = new Mod($modFileName);
                    $modsOutput[$mod->getFileName()] = $mod->getMd5();
                }
                $output = $modsOutput;
            }
            else {
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

            }
            $formatter = new ResponseFormatter();
            return $formatter->format($request, $output);
        });

        // 共用下載邏輯 closure（在 group 內定義一次）
        $sendDownload = function (Request $request, string $modFilePath) {
            if (!file_exists($modFilePath)) {
                throw new \Slim\Exception\HttpNotFoundException($request);
            }

            header('Content-Type: application/java-archive');
            header('Content-Disposition: attachment; filename="' . basename($modFilePath) . '"');
            header('Content-Length: ' . filesize($modFilePath));

            readfile($modFilePath);
            exit;
        };

        /**
         * @api {get} /:modType/:file 取得單一檔案模組資訊
         * @apiName getmod
         * @apiUse McModTypes
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
        $group->get('/{filename}', function (Request $request, Response $response, array $args) use ($modConfigKey, $sendDownload) {
            $config = $GLOBALS['config']['mods'];
            $baseModsPath = $config[$modConfigKey]['path'];
            $modFileName = $args['filename'];
            $modFilePath = join(DIRECTORY_SEPARATOR, [rtrim($baseModsPath, '/'), $modFileName]);

            // 若帶 ?download=1 則走下載流程
            $queryParams = $request->getQueryParams();
            if (!empty($queryParams['download']) && (string)$queryParams['download'] === '1') {
                $sendDownload($request, $modFilePath);
            }

            $mod = new Mod($modFilePath);
            $formatter = new ResponseFormatter();
            return $formatter->format($request, $mod->output());
        });

        /**
         * @api {get} /:modType/:file/download 下載單一模組檔案
         * @apiGroup Mods
         * @apiName DownloadFile
         * @apiUse McModTypes
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
        $group->get('/{filename}/download', function (Request $request, Response $response, array $args) use ($modConfigKey, $sendDownload) {
            $config = $GLOBALS['config']['mods'];
            $baseModsPath = $config[$modConfigKey]['path'];
            $modFileName = $args['filename'];
            $modFilePath = join(DIRECTORY_SEPARATOR, [rtrim($baseModsPath, '/'), $modFileName]);

            $sendDownload($request, $modFilePath);
        });

    });
}


