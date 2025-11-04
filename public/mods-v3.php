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

        $group->get('/{filename}/download', function (Request $request, Response $response, array $args) use ($modConfigKey, $sendDownload) {
            $config = $GLOBALS['config']['mods'];
            $baseModsPath = $config[$modConfigKey]['path'];
            $modFileName = $args['filename'];
            $modFilePath = join(DIRECTORY_SEPARATOR, [rtrim($baseModsPath, '/'), $modFileName]);

            $sendDownload($request, $modFilePath);
        });

    });
}


