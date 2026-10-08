<?php
/**
 * 模組元數據與圖標快取同步腳本 (CLI & Cron)
 *
 * 用法範例：
 *   php scripts/sync_mods_metadata.php                  # 常規增量更新（跳過有效快取）
 *   php scripts/sync_mods_metadata.php --force          # 強制全量重新向 Modrinth/CurseForge 查詢與下載圖標
 *   php scripts/sync_mods_metadata.php --ttl=7          # 刷新快取超過 7 天的資料
 *   php scripts/sync_mods_metadata.php --type=common    # 僅同步共同模組
 *   php scripts/sync_mods_metadata.php --quiet          # 靜音模式（適合 Crontab 排程）
 */

require_once __DIR__ . '/../bootstrap.php';

use McModUtils\Mods;
use McModUtils\ModMetadataFetcher;

// 解析 CLI 參數
$options = getopt('', ['force', 'ttl:', 'type:', 'quiet']);

$isForce = isset($options['force']);
$isQuiet = isset($options['quiet']);
$ttlDays = isset($options['ttl']) ? max(1, (int)$options['ttl']) : 14;
$ttlSeconds = $ttlDays * 86400;

$targetTypes = isset($options['type'])
    ? array_map('trim', explode(',', $options['type']))
    : array_keys($GLOBALS['config']['mods'] ?? ['common' => []]);

$startTime = microtime(true);

if (!$isQuiet) {
    echo "====================================================\n";
    echo "🚀 開始執行模組元數據與圖標同步任務\n";
    echo "時間: " . date('Y-m-d H:i:s') . "\n";
    echo "模式: " . ($isForce ? "強制全量刷新" : "增量更新 (TTL: {$ttlDays} 天)") . "\n";
    echo "目標分類: " . implode(', ', $targetTypes) . "\n";
    echo "====================================================\n\n";
}

$allMods = [];
$totalScanned = 0;

foreach ($targetTypes as $typeKey) {
    if (empty($GLOBALS['config']['mods'][$typeKey])) {
        if (!$isQuiet) {
            echo "⚠️  未在 config.php 中找到分類 [{$typeKey}]，略過。\n";
        }
        continue;
    }

    $modConfig = $GLOBALS['config']['mods'][$typeKey];
    $modsPath = $modConfig['path'] ?? null;

    if (!$modsPath || !file_exists($modsPath)) {
        if (!$isQuiet) {
            echo "⚠️  分類 [{$typeKey}] 指定的路徑不存在: {$modsPath}，略過。\n";
        }
        continue;
    }

    if (!$isQuiet) {
        echo "🔍 掃描分類 [{$typeKey}] ({$modsPath})...\n";
    }

    $modsUtil = new Mods();
    $modsUtil->setModsPath($modsPath);
    $modsUtil->setIsIgnoreServerside($modConfig['ignore_serverside_prefix'] ?? false);
    $modsUtil->setIsOnlyServerside($modConfig['only_serverside_prefix'] ?? false);
    $modsUtil->analyzeModsFolder();

    // 取得該分類下的所有模組
    $mods = $modsUtil->getMods(force: $isForce, enableCache: true);
    $count = count($mods);
    $totalScanned += $count;

    if (!$isQuiet) {
        echo "   找到 {$count} 個模組檔案。\n";
    }

    foreach ($mods as $m) {
        $allMods[$m->getSha1()] = $m;
    }
}

$uniqueMods = array_values($allMods);
$uniqueCount = count($uniqueMods);

if (!$isQuiet) {
    echo "\n📦 去重後共計 {$uniqueCount} 款獨立模組，開始比對元數據與下載圖標...\n";
}

// 執行批次注入與下載快取
ModMetadataFetcher::enrichMods($uniqueMods, force: $isForce, ttl: $ttlSeconds);

// 讀取最終快取統計資料
$cache = ModMetadataFetcher::loadCache();
$modrinthCount = 0;
$curseforgeCount = 0;
$customCount = 0;
$iconCount = 0;

foreach ($uniqueMods as $mod) {
    $sha1 = $mod->getSha1();
    $meta = $cache[$sha1] ?? [];
    $src = $meta['source'] ?? 'server';

    if ($src === 'modrinth') {
        $modrinthCount++;
    } elseif ($src === 'curseforge') {
        $curseforgeCount++;
    } else {
        $customCount++;
    }

    if (!empty($meta['logo']['local_icon']) || !empty($meta['logo']['local_thumb'])) {
        $iconCount++;
    }
}

$elapsed = round(microtime(true) - $startTime, 2);

if (!$isQuiet) {
    echo "\n====================================================\n";
    echo "✅ 同步任務完成！(耗時: {$elapsed} 秒)\n";
    echo "📊 統計報告:\n";
    echo "   - 掃描模組總數: {$totalScanned}\n";
    echo "   - 獨立模組總數: {$uniqueCount}\n";
    echo "   - Modrinth 官方匹配: {$modrinthCount}\n";
    echo "   - CurseForge 匹配: {$curseforgeCount}\n";
    echo "   - 伺服器自製/未收錄模組: {$customCount}\n";
    echo "   - 成功獲取/快取圖標數: {$iconCount}\n";
    echo "====================================================\n";
}

exit(0);
