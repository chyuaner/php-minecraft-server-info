<?php
require __DIR__ . '/../../bootstrap.php';

use McModUtils\Server;
use xPaw\MinecraftPingException;

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


