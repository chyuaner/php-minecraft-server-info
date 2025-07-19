<?php

require __DIR__ . '/../../../bootstrap.php';

use McModUtils\Mods;

$type = 'json';
// 若在網址有指定 ?type=csv ， 或是 header content-type有指定的話
if (false) {
    $type = 'csv';
}

// 若在網址有指定 /mods/{slug}
if (false) {
    return '';
}

// echo Mods::modsPath();
echo 'hashed: '.Mods::hash();
echo '<pre>';print_r(Mods::mods());echo '</pre>';
