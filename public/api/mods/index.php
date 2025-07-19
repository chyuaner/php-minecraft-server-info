<?php

require __DIR__ . '/../../../bootstrap.php';

use McModUtils\Mods;
use McModUtils\Mod;

$enableCache = true;
$type = 'json';

if (!empty($_REQUEST['force'])) {
    $enableCache = false;
}

$modsUtil = new Mods();
$modsUtil->analyzeModsFolder();

// // 測試只輸出hash
// echo $modsUtil->getHashed();
// exit;

// 取得帶入的網址參數
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$pathFilename = basename($path); // "lalala.jar"

// 若在網址有指定 /mods/{slug}
if (!in_array($pathFilename, ['mods', 'index', 'index.php'])) {
    $enableCache = false;
    $modFileName = $pathFilename;
    if (Mods::isFileExist($modFileName)) {
        $mod = new Mod($modFileName);
        $output = [
            "modHash" => $mod->getSha1(),
            "mod" => $mod->output()
        ];
    }
}

// 若沒有帶入單一檔案參數
if (empty($output)) {

    // 快取 $cacheFile
    $cacheFile = (BASE_PATH.$GLOBALS['config']['modsi_cache_rpath']);
    if ($enableCache && file_exists($cacheFile)) {
        $currentHash = $modsUtil->getHashed();
        $cache = json_decode(file_get_contents($cacheFile), true);
        if ($cache['modsHash'] == $currentHash) {
            $output = $cache;
            header('Content-Type: application/json; charset=utf-8');
            $outputRaw = json_encode($output);
            echo $outputRaw;
            exit;
        }
    }

    $modsFileList = $modsUtil->getModNames();
    $modsOutput = [];
    foreach ($modsFileList as $modFileName) {
        $mod = new Mod($modFileName);
        array_push($modsOutput, $mod->output());
    }

    $output = [
        "modsHash" => $modsUtil->getHashed(),
        "mods" => $modsOutput
    ];
}

// 測試輸出純內容
// echo '<pre>';print_r($modsOutput);echo '</pre>';
// exit;

// 若在網址有指定 ?type=csv ， 或是 header content-type有指定的話
if (false) {
    $type = 'csv';
}

header('Content-Type: application/json; charset=utf-8');
$outputRaw = json_encode($output);
echo $outputRaw;

// 寫入快取
if($enableCache && $type = 'json') {
    $cacheFilePath = BASE_PATH.$GLOBALS['config']['modsi_cache_rpath'];
    file_put_contents($cacheFilePath, $outputRaw);
}
