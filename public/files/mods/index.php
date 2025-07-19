<?php

use McModUtils\Mods;

require __DIR__ . '/../../../bootstrap.php';

header('X-Served-By: PHP-Mods-Stream');

$modFile = basename($_SERVER['REQUEST_URI']);  // e.g. curios.jar
$modPath = Mods::parseFileInput($modFile);

if (!preg_match('/\.jar$/', $modFile)) {
    http_response_code(400);
    echo "Invalid request.";
    exit;
}

if (!Mods::isFileExist($modFile)) {
    http_response_code(404);
    echo "File not found.";
    exit;
}

// Optionally log download count here...

header('Content-Type: application/java-archive');
header('Content-Disposition: attachment; filename="' . basename($modPath) . '"');
header('Content-Length: ' . filesize($modPath));

readfile($modPath);
exit;
