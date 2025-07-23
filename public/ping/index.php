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

$output = [];

try
{
    $Query = new MinecraftPing( $GLOBALS['config']['minecraft_host'], $GLOBALS['config']['minecraft_port'] );
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
