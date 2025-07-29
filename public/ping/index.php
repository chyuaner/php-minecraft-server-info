<?php
require __DIR__ . '/../../bootstrap.php';

use McModUtils\Server;
use xPaw\MinecraftPingException;

/**
 * @api {get} /ping/:server 取得Minecraft伺服器狀態
 * @apiName Ping
 * @apiGroup Server
 * @apiUse McServers
 * @apiQuery {string="json","html"} [type=json] 指定要輸出的格式
 * @apiHeader {String="text/html","application/json"} [Accept=application/json] 由Header控制要輸出的格式。若有在網址帶入 `type=json` 參數，則以網址參數為主
 *
 * @apiSuccessExample {json} JSON輸出
 *     HTTP/1.1 200 OK
 *     {
 *         "description": "A Minecraft Server",
 *         "players": {
 *             "max": 20,
 *             "online": 2,
 *             "sample": [
 *                 {
 *                     "id": "330ec9fb-cbb3-3ac5-b19e-6678ebde0b18",
 *                     "name": "Barianyyy0517"
 *                 },
 *                 {
 *                     "id": "58dba7b3-3a27-384f-9145-21fac550cde6",
 *                     "name": "chyuaner"
 *                 }
 *             ]
 *         },
 *         "version": {
 *             "name": "Youer 1.21.1",
 *             "protocol": 767
 *         }
 *     }
 *
 * @apiErrorExample {json} JSON輸出
 *     HTTP/1.1 500 Not Found
 *     {
 *         "error":"Failed to connect or create a socket: 111 (Connection refused)"
 *     }
 */
 *
 * @apiExample 使用範例:
 *     https://api-minecraft.yuaner.tw/mods
 *     https://api-minecraft.yuaner.tw/mods/?type=json
 *     https://api-minecraft.yuaner.tw/mods/automodpack-mc1.21.1-neoforge-4.0.0-beta38.jar?type=json
 */

// 如果有包含 text/html，就當作瀏覽器
if (!empty($_REQUEST['type']) && $_REQUEST['type'] == 'html' || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html')) {
    $type = 'html';
    $enableCache = false; // 快取只針對JSON使用，所以非JSON就直接關閉快取
}
if (!empty($_REQUEST['type']) && $_REQUEST['type'] == 'json') {
    $type = 'json';
    $enableCache = true;
}

// 若在網址有指定 /ping/{server}
$selectorParamName = 'serverId';
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$pathFilename = basename($path); // "lalala.jar"
if (!empty($_REQUEST[$selectorParamName]) || !in_array($pathFilename, ['ping', 'index', 'index.php'])) {
    if (!empty($_REQUEST[$selectorParamName])) {
        $$selectorParamName = $_REQUEST[$selectorParamName];
    } else {
        $$selectorParamName = $pathFilename;
    }

    $enableCache = false;
    $modFileName = $$selectorParamName;
}

// -----------------------------------------------------------------------------

$server = new Server($serverId);

try
{
    $output = $server->outputPing();
}
catch( MinecraftPingException $e )
{
    http_response_code(500);
    $output = ['error' => $e->getMessage()];

}
finally
{
    switch ($type) {
        case 'html':
            echo '<pre>';
            print_r( $output );
            echo '</pre>';
            break;

        default:
        case 'json':
            header('Content-Type: application/json; charset=utf-8');
            $outputRaw = json_encode($output);
            echo $outputRaw;
            break;
    }
}


