<?php

use App\ResponseFormatter;
use McModUtils\Mod;
use McModUtils\Mods;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// $app->group('/api', function ($group) {

//     $group->get('/status', function (Request $request, Response $response, $args) {
//         $data = ['status' => 'ok'];
//         $response->getBody()->write(json_encode($data));
//         return $response->withHeader('Content-Type', 'application/json');
//     });

//     $group->get('/time', function (Request $request, Response $response, $args) {
//         $data = ['time' => date('Y-m-d H:i:s')];
//         $response->getBody()->write(json_encode($data));
//         return $response->withHeader('Content-Type', 'application/json');
//     });

// });

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

$app->get('/mods', function (Request $request, Response $response, array $args) {
    $enableCache = true;
    $type = 'json';
    $modFileName = null;

    $formatter = new ResponseFormatter();
    if ($formatter->isJson($request)) {
        $enableCache = true;
    }

    if (!empty($_REQUEST['force'])) {
        $enableCache = false;
    }

    $selectorParamName = 'file';
    $uri = $_SERVER['REQUEST_URI'];
    $path = parse_url($uri, PHP_URL_PATH);
    $pathFilename = basename($path); // "lalala.jar"
    if (!empty($_REQUEST[$selectorParamName]) || !in_array($pathFilename, ['mods', ':file', 'index', 'index.php'])) {
        if (!empty($_REQUEST[$selectorParamName])) {
            $$selectorParamName = $_REQUEST[$selectorParamName];
        } else {
            $$selectorParamName = $pathFilename;
        }

        $enableCache = false;
        $modFileName = $$selectorParamName;
    }

    // ------------------------------------------------------------------------

    $modsUtil = new Mods();
    $modsUtil->analyzeModsFolder();

    if (!empty($modFileName) && Mods::isFileExist($modFileName)) {
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
    }
    else {
        // 若有啟用快取，就從快取抓
        $formatter = new ResponseFormatter();

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

        // 普通 route
        return $formatter->format($request, $output);
    }
});
