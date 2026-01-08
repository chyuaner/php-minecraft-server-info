<?php
/**
 * 此檔案由 scripts/generate-apidoc.php 自動產生
 * 請勿手動修改
 */

// --- 處理 McServers ---
$hasServers = false;
$serversStr = getenv('MC_SERVERS');
if (!$serversStr) {
    $configFile = __DIR__ . '/../config.php';
    if (file_exists($configFile)) {
        $config = include $configFile;
        if (isset($config['minecraft_servers'])) {
            $servers = array_keys($config['minecraft_servers']);
            $serversStr = implode(',', $servers);
            $hasServers = true;
        }
    }
} else {
    $hasServers = true;
}

if (!$hasServers) {
    $serversStr = 'forge1,auth2';
}

$serverParamType = (!empty($serversStr)) ? "{String=$serversStr}" : "{String}";

// --- 處理 McFolder ---
$hasFolders = false;
$foldersStr = '';
$defaultFolder = 'config';

if (isset($config['other_folders'])) {
    $folderNames = [];
    foreach ($config['other_folders'] as $diskPath => $dlUrlPath) {
        $norm = trim($dlUrlPath, '/');
        if (strpos($norm, 'files/') === 0) {
            $norm = substr($norm, strlen('files/'));
        }
        $folderNames[] = $norm;
    }
    $foldersStr = implode(',', $folderNames);
    if (!empty($folderNames)) {
        $defaultFolder = $folderNames[0];
    }
    $hasFolders = true;
}

if (!$hasFolders) {
    $foldersStr = 'config,defaultconfigs,kubejs,modernfix,resourcepacks,tacz,tlm_custom_pack';
}

$folderParamType = (!empty($foldersStr)) ? "{String=$foldersStr}" : "{String}";
$folderDefault = (!empty($foldersStr)) ? "=$defaultFolder" : "";

$content = "<?php\n"
         . "/**\n"
         . " * @apiDefine McServers\n"
         . " * @apiParam $serverParamType [server] 選填，伺服器名稱。未填則使用預設伺服器。\n"
         . " */\n\n"
         . "/**\n"
         . " * @apiDefine McFolder\n"
         . " * @apiParam $folderParamType folder$folderDefault 資料夾名稱\n"
         . " */\n";

$targetFile = __DIR__ . '/../public/00_apidoc_defines.php';
file_put_contents($targetFile, $content);

echo "Generated apidoc definitions.\n";
echo "Servers: " . ($serversStr ?: '(none)') . "\n";
echo "Folders: " . ($foldersStr ?: '(none)') . "\n";

