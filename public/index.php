<?php
require __DIR__ . '/../bootstrap.php';

$defaultApiFile = __DIR__.'/mods/index.php';

// 如果有包含 text/html，就當作瀏覽器
$type = 'json';
if (!empty($_REQUEST['type']) && $_REQUEST['type'] == 'html' || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html')) {
    $type = 'html';
}
if (!empty($_REQUEST['type']) && $_REQUEST['type'] == 'json') {
    $type = 'json';
}

if ($type == 'json') {
    include $defaultApiFile;
    exit;
}

/**
 * @apiDefine McServers
 * @apiParam {String="youer1","youer2"} [server] 選填，伺服器名稱，例如 `youer1`。未填則使用預設伺服器。
 */
?>

<!DOCTYPE html>
<html lang="zh-tw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minecraft 橋接遊戲伺服器用的後端</title>

    <?php
        if ($type=='html' && file_exists('docs/index.html')) {
            echo '<meta http-equiv="refresh" content="0;url=/docs/index.html">';
        }
    ?>
</head>
<body>
    <h1>Minecraft 橋接遊戲伺服器用的後端</h1>

    <h2>伺服器位址</h2>
    <?= $GLOBALS['config']['minecraft_host'] ?>:<?= $GLOBALS['config']['minecraft_port'] ?>

    <h2>已有功能</h2>
    <ul>
        <li>GET /mods</li>
        <li>GET /mods/ApothicAttributes-1.21.1-2.9.0.jar</li>
        <li>GET /files/mods/ApothicAttributes-1.21.1-2.9.0.jar</li>
        <li>GET /zip/mods</li>
        <li>GET /ping</li>
        <li>GET /banner</li>
    </ul>
</body>
</html>
