<?php
require __DIR__ . '/../../bootstrap.php';

use McModUtils\Server;
use \MinecraftBanner\ServerBanner;

$startPing = microtime(true);
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

$server = new Server($server);
$hostString = $server->getHostString();
$name = $server->getName();
$onlinePlayersCount = $server->getOnlinePlayersCount();
$maxPlayersCount = $server->getMaxPlayersCount();

$endPing = microtime(true);
$durationPing = ($endPing - $startPing) * 1000;

//tell the browser that we will send the raw image without HTML
header('Content-type: image/png');

$banner = ServerBanner::server('  '.$hostString, '  '.$name, $onlinePlayersCount, $maxPlayersCount.'    '.round($durationPing, 0).'ms', NULL, NULL, $durationPing);
imagepng($banner);
