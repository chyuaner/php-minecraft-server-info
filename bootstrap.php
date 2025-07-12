<?php
// 定義根目錄常數
define('BASE_PATH', __DIR__);

// Autoload PSR-4
require BASE_PATH . '/vendor/autoload.php';

// 載入 config 陣列並存進全域變數（或用 Singleton 類包裝）
$default_config = require BASE_PATH . '/config.default.php';
$local_config   = file_exists(BASE_PATH . '/config.php') ? require BASE_PATH . '/config.php' : [];
$GLOBALS['config'] = array_merge($default_config, $local_config);

