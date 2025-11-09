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
 * @apiParam {String="mods","client-mods","server-mods"} modType="mods"
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

// 共用下載邏輯 closure（在 group 內定義一次）
$sendDownload = function (Request $request, string $modFilePath) {
    if (!file_exists($modFilePath)) {
        throw new \Slim\Exception\HttpNotFoundException($request);
    }

    header('Content-Type: application/java-archive');
    header('Content-Disposition: attachment; filename="' . basename($modFilePath) . '"');
    header('Content-Length: ' . filesize($modFilePath));
    header('X-Served-By: PHP');

    readfile($modFilePath);
    exit;
};

foreach ($routerConfigMap as $modType => $modConfigKey) {

    $app->group("/$modType", function (RouteCollectorProxy $group) use ($modConfigKey, $sendDownload) {

        /**
         * @api {get} /:modType/zip 下載全部模組包
         * @apiName DownloadModsZip
         * @apiGroup Mods
         * @apiUse McModTypes
         * @apiQuery {Boolean} [force=false] 不使用快取，強制重新壓縮。
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
         *     curl -O https://mc-api.yuaner.tw/mods/zip
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
            header('X-Served-By: PHP');
            readfile($zip_path);
            exit;
        });


        /**
         * @api {get} /:modType 取得模組列表
         * @apiGroup Mods
         * @apiName getAllMods
         * @apiUse McModTypes
         * @apiUse ResponseFormatter
         * @apiQuery {Boolean} [force=false] 不使用快取，強制刷新。
         * @apiQuery {Boolean} [simple-md5=false] 以精簡版 filename: md5 形式輸出。
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
         *                 "download": "https://mc-api.yuaner.tw/files/mods/ApothicAttributes-1.21.1-2.9.0.jar",
         *                 "downloadUrl": "https://mc-api.yuaner.tw/files/mods/ApothicAttributes-1.21.1-2.9.0.jar"
         *             }
         *         ]
         *     }
         *
         * @apiSuccessExample {json} simple-md5=1 精簡版輸出
         *     HTTP/1.1 200 OK
         *     {
         *         "4mod_sets-forge-1.4.8+sha.5712396+1.20.1.jar": "a9312e369434ca703b582ce0de4d612a",
         *         "3kotlinforforge-4.10.0-all.jar": "66104f85db917822d99311bcb6c71f97",
         *         "embeddium-0.3.31+mc1.20.1.jar": "1dfb2ee49ce9ad5d484ff3eea0d628b7",
         *         "1catalogue-forge-1.20.1-1.8.0.jar": "524efc6bbcd6da51e86cbf3183587330",
         *         "2chloride-FORGE-mc1.20.1-v1.7.2.jar": "a50f626acc0ade9c250df4cd16ec960d",
         *         "mekalus-mc1.20.1-1.7.0.3.jar": "437bbc5f2661a59eb6ab701dcfe3def9",
         *         "yet-another-config-lib-forge-3.2.2+1.20.jar": "e72feb2f4859acdb40d252c07b24688a"
         *     }
         *
         * @apiExample 使用範例:
         *     https://mc-api.yuaner.tw/mods
         *     https://mc-api.yuaner.tw/mods/?type=json
         *     https://mc-api.yuaner.tw/mods/?simple-md5&type=json
         */
        $group->get('', function (Request $request, Response $response, array $args) use ($modConfigKey) {
            $queryParams = $request->getQueryParams();
            $isForce = !empty($queryParams['force']) && in_array(strtolower($queryParams['force']), ['1', 'true', 'yes'], true);

            // 拉出模組清單
            $config = $GLOBALS['config']['mods'];
            $baseModsPath = $config[$modConfigKey]['path'];
            $modsUtil = new Mods();
            $modsUtil->setModsPath($baseModsPath);
            $modsUtil->setIsIgnoreServerside($config[$modConfigKey]['ignore_serverside_prefix']);
            $modsUtil->setIsOnlyServerside($config[$modConfigKey]['only_serverside_prefix']);
            $modsUtil->analyzeModsFolder();
            $mods = $modsUtil->getMods(force: $isForce, enableCache: true);
            $cacheUpdateAt = $modsUtil->getCacheUpdateTime();

            if (!empty($queryParams['simple-md5'])) {
                $modsOutput = [];
                foreach ($mods as $mod) {
                    $modsOutput[$mod->getFileName()] = $mod->getMd5();
                }

                $output = $modsOutput;
            }
            else {
                $modsOutput = array_map(function ($mod) {
                    return $mod->output();
                }, $mods);

                if ($cacheUpdateAt !== null) {
                    $cacheUpdateAt->setTimezone(new DateTimeZone('Asia/Taipei'));
                }
                else {
                    $cacheUpdateAt = new DateTime('now');
                    $cacheUpdateAt->setTimezone(new DateTimeZone('Asia/Taipei'));
                }

                $output = [
                    "modsHash" => $modsUtil->getHashed(),
                    "updateAt" => $cacheUpdateAt->format(DateTime::ATOM),
                    "mods" => $modsOutput
                ];

            }
            $formatter = new ResponseFormatter();
            return $formatter->format($request, $output);
        });

        /**
         * @api {get} /:modType/:file 取得單一檔案模組資訊
         * @apiName getmod
         * @apiUse McModTypes
         * @apiUse ResponseFormatter
         * @apiParam {String} file 伺服器上的Mod檔案名稱
         * @apiParam {Boolean} [download=false] 等同直接輸出下載單一模組檔案本體
         *
         * @apiGroup Mods
         *
         *
         * @apiExample 使用範例:
         *     https://mc-api.yuaner.tw/mods/ftb-quests-forge-2001.2.0.jar
         *     https://mc-api.yuaner.tw/mods/ftb-quests-forge-2001.2.0.jar?type=json
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
         * @api {get} /:modType/:file/download 下載單一模組檔案（經由PHP）
         * @apiDeprecated 此API會經過PHP後端程式處理，效能會略低於上述提供的Nginx直連下載，僅提供Fallback使用
         * @apiGroup Mods
         * @apiName DownloadFilePhp
         * @apiUse McModTypes
         * @apiParam {String} file 伺服器上的Mod檔案名稱
         *
         * @apiSampleRequest off
         * @apiSuccess (Success 200) {File} file 檔案，`Content-Type: application/java-archive`
         *
         * @apiSuccessExample {jar} 成功範例:
         *     HTTP/1.1 200 OK
         *     Content-Type: application/java-archive
         *     Content-Disposition: attachment; filename="ftb-quests-forge-2001.2.0.jar"
         *     (二進位資料)
         *
         * @apiExample 使用範例:
         *     https://mc-api.yuaner.tw/mods/ftb-quests-forge-2001.2.0.jar/download
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

// 讓設定在config的dl_urlpath也能直接透過此後端生效（基本上還是要設定Nginx繞過本後端程式，此程式只是提供fallback機制）
// http://localhost:8000/files/mods/L_Enders_Cataclysm-3.16.jar
$registeredDlPaths = [];
foreach ($routerConfigMap as $modType => $modConfigKey) {
    $dlPath = $GLOBALS['config']['mods'][$modConfigKey]['dl_urlpath'];

    // 若已註冊過相同的 dl_urlpath 就跳過（若要遇到第一個就完全停止註冊，改成 break）
    if (in_array($dlPath, $registeredDlPaths, true)) {
        continue;
    }
    $registeredDlPaths[] = $dlPath;

    /**
     * @api {get} /files/:modFolder/:file 下載單一模組檔案
     * @apiGroup Mods
     * @apiName DownloadFile
     * @apiParam {String="mods","clientmods"} modFolder
     * @apiParam {String} file 伺服器上的Mod檔案名稱
     *
     * @apiSampleRequest off
     * @apiSuccess (Success 200) {File} file 檔案，`Content-Type: application/java-archive`
     *
     * @apiSuccessExample {jar} 成功範例:
     *     HTTP/1.1 200 OK
     *     Content-Type: application/java-archive
     *     Content-Disposition: attachment; filename="ftb-quests-forge-2001.2.0.jar"
     *     (二進位資料)
     *
     * @apiExample 使用範例:
     *     https://mc-api.yuaner.tw/files/mods/ftb-quests-forge-2001.2.0.jar
     */
    $app->get($dlPath.'{filename}', function (Request $request, Response $response, array $args) use ($modConfigKey, $sendDownload) {
        $config = $GLOBALS['config']['mods'];
        $baseModsPath = $config[$modConfigKey]['path'];
        $modFileName = $args['filename'];
        $modFilePath = join(DIRECTORY_SEPARATOR, [rtrim($baseModsPath, '/'), $modFileName]);

        $sendDownload($request, $modFilePath);
    });
}

