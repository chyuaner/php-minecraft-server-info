<?php

require __DIR__ . '/../../bootstrap.php';

use McModUtils\Mods;
use McModUtils\Mod;

$enableCache = true;
$type = 'json';
$modFileName = null;

// 如果有包含 text/html，就當作瀏覽器
if ($_REQUEST['type'] == 'html' || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html')) {
    $type = 'html';
    $enableCache = false; // 快取只針對JSON使用，所以非JSON就直接關閉快取
}
if ($_REQUEST['type'] == 'json') {
    $type = 'json';
    $enableCache = true;
}

if (!empty($_REQUEST['force'])) {
    $enableCache = false;
}

// 若在網址有指定 /mods/{slug}
// 取得帶入的網址參數
$selectorParamName = 'file';
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

$modsUtil = new Mods();
$modsUtil->analyzeModsFolder();

// 若在網址有指定 /mods/{slug}
if (!empty($modFileName) && Mods::isFileExist($modFileName)) {
    $mod = new Mod($modFileName);

    switch ($type) {
        case 'html':
            $outputRaw = $mod->outputHtml();
            echo '<ul><li>'.$outputRaw.'</li></ul>';
            break;

        case 'json':
        default:
            $output = [
                "modHash" => $mod->getSha1(),
                "mod" => $mod->output()
            ];

            // 輸出
            header('Content-Type: application/json; charset=utf-8');
            $outputRaw = json_encode($output);
            echo $outputRaw;
            break;
    }

    exit;
}
// 若沒有帶入單一檔案參數
else {

    // 若有啟用快取，就從快取抓
    if ($enableCache && $type=='json') {

        // 快取 $cacheFile
        $cacheFile = (BASE_PATH.'/public/static/mods.json');
        if (file_exists($cacheFile)) {

            // 檢查該資料夾有無被變動過
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
    }


    $modsFileList = $modsUtil->getModNames();

    if ($type == 'html') {
        echo '<ul>';
        foreach ($modsFileList as $modFileName) {
            $mod = new Mod($modFileName);
            echo '<li>';
            echo $mod->outputHtml();
            echo '</li>';
        }
        echo '</ul>';
    }
    else {
        $modsOutput = [];
        foreach ($modsFileList as $modFileName) {
            $mod = new Mod($modFileName);
            array_push($modsOutput, $mod->output());
        }

        $now = new DateTime('now');
        $now->setTimezone(new DateTimeZone('Asia/Taipei'));

        $output = [
            "modsHash" => $modsUtil->getHashed(),
            "updateAt" => $now->format(DateTime::ATOM),
            "mods" => $modsOutput
        ];

        header('Content-Type: application/json; charset=utf-8');
        $outputRaw = json_encode($output);
        echo $outputRaw;
    }

}

// 寫入快取
if($type = 'json') {
    $cacheFilePath = BASE_PATH.'/public/static/mods.json';
    file_put_contents($cacheFilePath, $outputRaw);
}
