<?php
require __DIR__ . '/../../bootstrap.php';

use McModUtils\Server;
use \MinecraftBanner\ServerBanner;
use xPaw\MinecraftPingException;

$isShowPlayer = false;
if (!empty($_REQUEST['players'])) {
    $isShowPlayer = true;
}

// 若在網址有指定 /ping/{server}
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
        $playersStr = $server->getPlayersName();
        if (empty($playersStr)) {
            $playersStr = '__';
        }
        $name = 'login >  '.implode(', ', $playersStr);;
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
    http_response_code(500);
    $hostString = $server->getPublicHostString();
    $endPing = microtime(true);
    $durationPing = ($endPing - $startPing) * 1000;

    //tell the browser that we will send the raw image without HTML
    header('Content-type: image/png');

    $banner = outputBanner($hostString, $e->getMessage(), $onlinePlayersCount, $maxPlayersCount, -1);
    imagepng($banner);

}
