<?php

use App\ResponseFormatter;
use McModUtils\Server;
use MinecraftBanner\ServerBanner;
use xPaw\MinecraftPingException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use xPaw\MinecraftQuery;

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
    return $formatter->format($request, $output);
});

$app->get('/query', function (Request $request, Response $response, array $args) {
    $output = [];
    $Query = new MinecraftQuery();
    $Query->Connect( $GLOBALS['config']['minecraft_host'], $GLOBALS['config']['minecraft_qport'] );
    $output = [
        'info' => $Query->GetInfo(),
        'players' => $Query->GetPlayers(),
    ];
    // catch( MinecraftQueryException $e )
    // {
    //     http_response_code(507);
    //     $output = ['error' => $e->getMessage()];
    // }

    $formatter = new ResponseFormatter();
    return $formatter->format($request, $output);

});

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
$app->get('/online_players', function (Request $request, Response $response, array $args) {
    $otype = 'all';
    if (!empty($_REQUEST['otype'])) {
        $otype = $_REQUEST['otype'];
    }

    // 若在網址有指定 /ping/{server}
    $selectorParamName = 'serverId';
    $uri = $_SERVER['REQUEST_URI'];
    $path = parse_url($uri, PHP_URL_PATH);
    $pathFilename = basename($path); // "lalala.jar"
    if (!empty($_REQUEST[$selectorParamName]) || !in_array($pathFilename, ['online-players', 'index', 'index.php'])) {
        if (!empty($_REQUEST[$selectorParamName])) {
            $$selectorParamName = $_REQUEST[$selectorParamName];
        } else {
            $$selectorParamName = $pathFilename;
        }
    }

    // -----------------------------------------------------------------------------

    $server = new Server($serverId);
    switch ($otype) {
        case 'name':
            $output = $server->getPlayersName();
            break;

        default:
            $output = $server->getPlayers();
            break;
    }
    // // catch( MinecraftQueryException $e )
    // // {
    // //     http_response_code(507);
    // //     $output = ['error' => $e->getMessage()];
    // // }

    $formatter = new ResponseFormatter();
    return $formatter->format($request, $output);
});

/**
 * @api {get} /banner/:server 取得伺服器橫幅圖片
 * @apiUse McServers
 * @apiQuery {Boolean} [players=false] 是否顯示玩家名單（例如 `?players=1`）。
 *
 * @apiName GetServerBanner
 * @apiGroup Server
 *
 * @apiDescription
 * 產生一張 Minecraft 伺服器狀態的橫幅圖片。
 * 如果不提供 `:server` 參數，將預設使用主伺服器。
 * 可透過 `players=1` 顯示上線玩家名單。
 *
 * #### 預覽
 * <img src="https://api-minecraft.yuaner.tw/banner/?players=1">
 *
 * @apiSuccess (Success 200) {File} png 返回一張 `image/png` 格式的伺服器狀態圖片。
 *
 * @apiSuccessExample {png} 成功範例:
 *     HTTP/1.1 200 OK
 *     Content-Type: image/png
 *     (二進位圖片資料)
 *
 * @apiExample 使用範例:
 *     https://api-minecraft.yuaner.tw/banner
 *     https://api-minecraft.yuaner.tw/banner?players=1
 *     https://api-minecraft.yuaner.tw/banner/youer1
 *     https://api-minecraft.yuaner.tw/banner/youer1?players=1
 */
$app->get('/banner', function (Request $request, Response $response, array $args) {
    $isShowPlayer = false;
    if (!empty($_REQUEST['players'])) {
        $isShowPlayer = true;
    }

    // 若在網址有指定 /ping/{server}
    $serverId = null;
    $selectorParamName = 'serverId';
    $uri = $_SERVER['REQUEST_URI'];
    $path = parse_url($uri, PHP_URL_PATH);
    $pathFilename = basename($path); // "lalala.jar"
    if (!empty($_REQUEST[$selectorParamName]) || !in_array($pathFilename, ['banner', 'index', 'index.php'])) {
        if (!empty($_REQUEST[$selectorParamName])) {
            $$selectorParamName = $_REQUEST[$selectorParamName];
        } else {
            $$selectorParamName = $pathFilename;
        }

        if (!Server::isExistServerId($serverId)) {
            $serverId = null;
        }
    }

    function outputBanner($title, $subtitle, $player_online, $player_max, $ping) {
        $output_title = ' '.$title;
        $output_ping_string = '';
        if (strlen($output_title) < 30) {
            $output_ping_string = '    '.round($ping, 0).'ms';
        }

        return ServerBanner::server($output_title,
        '  '.$subtitle
        , $player_online, $player_max
            .$output_ping_string
        , NULL, NULL, $ping);
    }

    $server = new Server($serverId);

    try
    {
        $startPing = microtime(true);
        $hostString = $server->getPublicHostString();
        if ($isShowPlayer) {
            $playersStr = implode(', ', $server->getPlayersName());
            if (empty($playersStr)) {
                $playersStr = 'no player';
            }
            $name = 'Online:  '.$playersStr;
        } else {
            $name = $server->getName();
        }

        $onlinePlayersCount = $server->getOnlinePlayersCount();
        $maxPlayersCount = $server->getMaxPlayersCount();

        $endPing = microtime(true);
        $durationPing = ($endPing - $startPing) * 1000;

        //tell the browser that we will send the raw image without HTML
        header('Content-type: image/png');

        $banner = outputBanner($hostString, $name, $onlinePlayersCount, $maxPlayersCount, $durationPing);
        imagepng($banner);
    }
    catch( MinecraftPingException $e )
    {
        http_response_code(507);
        $hostString = $server->getPublicHostString();
        $endPing = microtime(true);
        $durationPing = ($endPing - $startPing) * 1000;

        //tell the browser that we will send the raw image without HTML
        header('Content-type: image/png');

        $banner = outputBanner($hostString, $e->getMessage(), $onlinePlayersCount, $maxPlayersCount, -1);
        imagepng($banner);

    }
});
