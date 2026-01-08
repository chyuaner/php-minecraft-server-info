<?php
/**
 * 此檔案由 scripts/generate-apidoc.php 自動產生
 * 請勿手動修改
 */

// 嘗試從環境變數讀取伺服器清單，例如 MC_SERVERS=forge1,auth2
$serversStr = getenv('MC_SERVERS');

$hasList = false;
if (!$serversStr) {
    // 如果沒有環境變數，從 config.php 讀取
    $configFile = __DIR__ . '/../config.php';
    if (file_exists($configFile)) {
        $config = include $configFile;
        if (isset($config['minecraft_servers'])) {
            $servers = array_keys($config['minecraft_servers']);
            $serversStr = implode(',', $servers);
            $hasList = true; // 有讀到設定檔（即使是空陣列）
        }
    }
} else {
    $hasList = true; // 有讀到環境變數
}

// 只有在完全找不到設定（不是空陣列，而是沒這個變數也沒檔案）時才使用預設值
if (!$hasList) {
    $serversStr = 'forge1,auth2';
}

$paramType = (!empty($serversStr)) ? "{String=$serversStr}" : "{String}";

$content = "<?php\n"
         . "/**\n"
         . " * @apiDefine McServers\n"
         . " * @apiParam $paramType [server] 選填，伺服器名稱。未填則使用預設伺服器。\n"
         . " */\n";


$targetFile = __DIR__ . '/../public/_apidoc_defines.php';
file_put_contents($targetFile, $content);

echo "Generated apidoc definitions with servers: $serversStr\n";
