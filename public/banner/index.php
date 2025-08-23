<?php
require __DIR__ . '/../../bootstrap.php';

use McModUtils\Server;
use \MinecraftBanner\ServerBanner;
use xPaw\MinecraftPingException;

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
