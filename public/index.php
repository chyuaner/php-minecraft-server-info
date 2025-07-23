<?php
require __DIR__ . '/../bootstrap.php';

// use McModUtils\Mods;


// // echo Mods::modsPath();
// echo '<pre>';print_r(Mods::mods());echo '</pre>';

// // $all_files = glob('{.[!.],}*', GLOB_BRACE);
// // echo '<pre>';print_r($all_files);echo '</pre>';
?>

<!DOCTYPE html>
<html lang="zh-tw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minecraft 橋接遊戲伺服器用的後端</title>
</head>
<body>
    <h1>Minecraft 橋接遊戲伺服器用的後端</h1>
    <h2>已有功能</h2>
    <ul>
        <li>GET /mods</li>
        <li>GET /mods/ApothicAttributes-1.21.1-2.9.0.jar</li>
        <li>GET /files/mods/ApothicAttributes-1.21.1-2.9.0.jar</li>
        <li>GET /ping</li>
    </ul>
</body>
</html>
