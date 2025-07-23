<?php
require __DIR__ . '/../../bootstrap.php';

use xPaw\MinecraftPing;
use xPaw\MinecraftPingException;

// 如果有包含 text/html，就當作瀏覽器
if ($_REQUEST['type'] == 'html' || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html')) {
    $type = 'html';
    $enableCache = false; // 快取只針對JSON使用，所以非JSON就直接關閉快取
}
if ($_REQUEST['type'] == 'json') {
    $type = 'json';
    $enableCache = true;
}

// 若在網址有指定 /ping/{server}
$selectorParamName = 'serverId';
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$pathFilename = basename($path); // "lalala.jar"
if (!empty($_REQUEST[$selectorParamName]) || !in_array($pathFilename, ['index', 'index.php'])) {
    if (!empty($_REQUEST[$selectorParamName])) {
        $$selectorParamName = $_REQUEST[$selectorParamName];
    } else {
        $$selectorParamName = $pathFilename;
    }

    $enableCache = false;
    $modFileName = $$selectorParamName;
}

// -----------------------------------------------------------------------------

function getInfo($host, $port, $id=null, $name='', $qport=null) : array {
    try
    {
        $Query = new MinecraftPing( $host, $port );
        $output = $Query->Query();
    }
    catch( MinecraftPingException $e )
    {
        http_response_code(500);
        $output = ['error' => $e->getMessage()];
    }
    finally
    {
        if( $Query )
        {
            $Query->Close();
        }
    }
    return $output;
}

if (!empty($serverId) && array_key_exists($serverId, ($GLOBALS['config']['minecraft_servers']))) {
    $mc_server = $GLOBALS['config']['minecraft_servers'][$serverId];

    $output = getInfo(
        $mc_server['host'],
        $mc_server['port'],
        $serverId,
        $mc_server['name'],
        $mc_server['qport'],
    );
} else {
    $output = getInfo($GLOBALS['config']['minecraft_host'], $port=$GLOBALS['config']['minecraft_port']);
}

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
