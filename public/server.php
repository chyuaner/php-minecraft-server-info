<?php

use App\ResponseFormatter;
use McModUtils\Server;
use xPaw\MinecraftPingException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


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
 * @apiErrorExample {json} 伺服器連接異常
 *     HTTP/1.1 500
 *     {
 *         "error":"Failed to connect or create a socket: 111 (Connection refused)"
 *     }
 *
 * @apiExample 使用範例:
 *     https://api-minecraft.yuaner.tw/mods
 *     https://api-minecraft.yuaner.tw/mods/?type=json
 *     https://api-minecraft.yuaner.tw/mods/automodpack-mc1.21.1-neoforge-4.0.0-beta38.jar?type=json
 */
$app->get('/ping', function (Request $request, Response $response, array $args) {

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
    }

    // -----------------------------------------------------------------------------

    $server = new Server($serverId);

    $output = $server->outputPing();
    // catch( MinecraftPingException $e )
    // {
    //     http_response_code(507);
    //     $output = ['error' => $e->getMessage()];
    // }

    $formatter = new ResponseFormatter();

    // 普通 route
    return $formatter->format($request, $output);
});
