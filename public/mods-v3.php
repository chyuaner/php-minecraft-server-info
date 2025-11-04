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
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$routerConfigMap = [
    'mods' => 'common',
    'client-mods' => 'client',
    'server-mods' => 'server',
];

foreach ($routerConfigMap as $modType => $modConfigKey) {

    $app->group("/$modType", function (RouteCollectorProxy $group) use ($modType, $modConfigKey) {
        $group->get('/zip', function (Request $request, Response $response, array $args) {
            $response->getBody()->write("/zip");
            return $response;
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


        $group->get('/{filename}', function (Request $request, Response $response, array $args) use ($modConfigKey) {
            $config = $GLOBALS['config']['mods'];
            $baseModsPath = $config[$modConfigKey]['path'];
            $modFileName = $args['filename'];
            $modFilePath = join(DIRECTORY_SEPARATOR, [rtrim($baseModsPath, '/'), $modFileName]);

            $mod = new Mod($modFilePath);
            $formatter = new ResponseFormatter();
            return $formatter->format($request, $mod->output());
        });

        $group->get('/{filename}/download', function (Request $request, Response $response, array $args) use ($modConfigKey) {
            $config = $GLOBALS['config']['mods'];
            $baseModsPath = $config[$modConfigKey]['path'];
            $modFileName = $args['filename'];
            $modFilePath = join(DIRECTORY_SEPARATOR, [rtrim($baseModsPath, '/'), $modFileName]);

            if (!file_exists($modFilePath)) {
                // Slim 4，交由 Slim 的 404 處理流程
                throw new \Slim\Exception\HttpNotFoundException($request);
            }

            header('Content-Type: application/java-archive');
            header('Content-Disposition: attachment; filename="' . basename($modFilePath) . '"');
            header('Content-Length: ' . filesize($modFilePath));

            readfile($modFilePath);
            exit;
        });

    });
}


