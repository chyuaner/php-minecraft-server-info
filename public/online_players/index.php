<?php
require __DIR__ . '/../../bootstrap.php';

use McModUtils\Server;
use xPaw\MinecraftPingException;

/**
 * @api {get} /online-players/:server 取得Minecraft上線玩家名單
 * @apiName OnlinePlayers
 * @apiGroup Server
 * @apiUse McServers
 * @apiQuery {string="name"} [otype=name] 要輸出完整資訊，還是只想輸出名字
 * @apiQuery {string="json","html"} [type=json] 指定要輸出的格式
 * @apiHeader {String="text/html","application/json"} [Accept=application/json] 由Header控制要輸出的格式。若有在網址帶入 `type=json` 參數，則以網址參數為主
 *
 * @apiSuccessExample {json} JSON輸出完整資訊
 *     HTTP/1.1 200 OK
 *     [
 *         {
 *             "id": "<uuid>",
 *             "name": "chyuaner"
 *         }
 *     ]
 *
 * @apiSuccessExample {json} otype=name 的JSON輸出
 *     HTTP/1.1 200 OK
 *     ["Barianyyy0517", "chyuaner"]
 *
 * @apiErrorExample {json} 伺服器連接異常
 *     HTTP/1.1 500
 *     {
 *         "error":"Failed to connect or create a socket: 111 (Connection refused)"
 *     }
 */

// 如果有包含 text/html，就當作瀏覽器
if (!empty($_REQUEST['type']) && $_REQUEST['type'] == 'html' || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html')) {
    $type = 'html';
}
if (!empty($_REQUEST['type']) && $_REQUEST['type'] == 'json') {
    $type = 'json';
}

$otype = 'all';
if (!empty($_REQUEST['otype'])) {
    $otype = $_REQUEST['otype'];
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
    switch ($otype) {
        case 'name':
            $output = $server->getPlayersName();
            break;

        default:
            $output = $server->getPlayers();
            break;
    }
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
