<?php
require __DIR__ . '/../bootstrap.php';

use McModUtils\Mods;


// echo Mods::modsPath();
echo '<pre>';print_r(Mods::mods());echo '</pre>';

// $all_files = glob('{.[!.],}*', GLOB_BRACE);
// echo '<pre>';print_r($all_files);echo '</pre>';
