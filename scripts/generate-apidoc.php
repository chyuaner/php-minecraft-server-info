<?php
/**
 * 此檔案由 scripts/generate-apidoc.php 自動產生
 * 請勿手動修改
 */

// 嘗試從環境變數讀取伺服器清單，例如 MC_SERVERS=forge1,auth2
$serversStr = getenv('MC_SERVERS');

if (!$serversStr) {
    // 如果沒有環境變數，從 config.php 讀取
    $configFile = __DIR__ . '/../config.php';
    if (file_exists($configFile)) {
        $config = include $configFile;
        if (isset($config['minecraft_servers'])) {
            $servers = array_keys($config['minecraft_servers']);
            $serversStr = implode(',', $servers);
        }
    }
}

// 預設值（如果都找不到）
if (!$serversStr) {
    $serversStr = 'forge1,auth2';
}

$content = "<?php\n"
         . "/**\n"
         . " * @apiDefine McServers\n"
         . " * @apiParam {String=$serversStr} [server] 選填，伺服器名稱。未填則使用預設伺服器。\n"
         . " */\n";

$targetFile = __DIR__ . '/../public/_apidoc_defines.php';
file_put_contents($targetFile, $content);

echo "Generated apidoc definitions with servers: $serversStr\n";
