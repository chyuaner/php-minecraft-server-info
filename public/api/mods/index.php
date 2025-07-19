<?php

require __DIR__ . '/../../../bootstrap.php';

use McModUtils\Mods;
use McModUtils\Mod;

// 若在網址有指定 /mods/{slug}
if (false) {
    return '';
}

// echo Mods::modsPath();
// echo 'hashed: '.Mods::hash();
// echo '<pre>';print_r(Mods::mods());echo '</pre>';

$modsUtil = new Mods();
$modsUtil->analyzeModsFolder();
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

// echo '<pre>';print_r($output);echo '</pre>';

$type = 'json';
// 若在網址有指定 ?type=csv ， 或是 header content-type有指定的話
if (false) {
    $type = 'csv';
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($output);
