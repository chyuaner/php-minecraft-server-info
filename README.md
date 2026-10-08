後端抓取Minecraft資訊
===

## 簡介
基本上會檢查你指定的mods資料夾裡的所有.jar檔，並輸出成JSON，提供Minecraft自製模組同步腳本、靜態前端頁面API呼叫使用。

因為實測在由PHP正常讀取zip檔內容後Render出結果所需要花的時間，和由PHP僅讀取資料夾內所有檔案的檔頭+已輸出JSON檔並重新解析後Render出來的時間相比，差距滿大的。所以會直接處理已輸出JSON當快取，不使用資料庫做快取來盡可能增進效能。

另外在Nginx配合良好的情況下，可以設定特定資料夾直接跳過PHP執行來達到最佳效能。但是檔案載點還是有留下 index.php 以PHP開steam的方式直接output檔案本體（ `/public/files/mods/index.php`），讓網頁伺服器靜態設定失效的時候還能fallback以PHP執行來替代。

## 系統環境
已測試的作業系統
* Linux 6.14.0-2-rt3-MANJARO
    * PHP 8.4.7 (cli) (built: May  6 2025 14:43:39) (NTS)
* Debian GNU/Linux 12 (bookworm) x86_64
    * PHP 8.2.28 (cli) (built: Mar 13 2025 18:21:38) (NTS)
* Debian GNU/Linux 13 (trixie) x86_64
    * PHP 8.4.11 (cli) (built: Aug  3 2025 07:32:21) (NTS)

### 需要依賴的PHP extensions
* php-zip
* php-gd

### 需要調整的PHP設定

* /etc/php/php.ini (Manjaro)
* /etc/php/8.4/fpm/php.ini

```
extension=gd # 註解解掉，要啟用此功能
extension=zip # 註解解掉，要啟用此功能
max_execution_time = 90 # 允許的執行時間加大
memory_limit = 2048M # 允許的記憶體加大
```

sudo systemctl reload php8.4-fpm.service

## 建置&啟動開發伺服器
```
git clone <url>
cd php-minecraft-server-info
composer install
composer dump-autoload
cp config.default.php config.php
vim config.php # 根據需求修改
php -S 127.0.0.1:8000 -t public
```

### 啟動簡易伺服器
```
php -S 127.0.0.1:8000 -t public
```

<http://localhost:8000>

## 上線部署
### Nginx設定



## Debian 13 上線佈署說明
```
sudo apt install php-fpm composer php-zip php-gd nodejs npm
cd /opt/minecraft/
git clone <url>
cd php-minecraft-server-info
composer install
npm install
cp config.default.php config.php
vim config.php # 根據需求修改

sudo gpasswd -a www-data minecraft
sudo chgrp minecraft -R /opt/minecraft/php-minecraft-server-info
sudo chmod g+s -R /opt/minecraft/php-minecraft-server-info
sudo systemctl restart php8.4-fpm.service 
```

## Webhook自動更新
* sudo apt install webhook
* sudo vim /etc/systemd/system/webhook.service
    ```
    [Unit]
    Description=Webhook server

    [Service]
    Type=exec
    ExecStart=webhook -hooks /etc/webhook/hooks.json -verbose

    # Which user should the webhooks run as?
    User=www-data
    Group=www-data

    [Install]
    WantedBy=multi-user.target
    ```

* sudo systemctl daemon-reload
* sudo mkdir /var/www/.npm
* sudo chown -R 33:33 "/var/www/.npm"
* sudo mkdir /etc/webhook

* sudo vim /etc/webhook/hooks.json
    ```
    [
    {
        "id": "php-minecraft-server-info",
        "execute-command": "/opt/minecraft/webhook/deploy-php-minecraft-server-info.sh",
        "command-working-directory": "/opt/minecraft/php-minecraft-server-info"
    }
    ]
    ```

* vim /opt/minecraft/webhook/deploy-php-minecraft-server-info.sh
    ```
    #!/bin/bash

    set -e
    cd /opt/minecraft/php-minecraft-server-info

    echo "▶ [DEPLOY] Starting deploy at $(date)"

    # 拉取最新程式碼
    export GIT_SSH_COMMAND="ssh -i /opt/minecraft/ssh/id_ed25519 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null"

    git pull origin master

    # 如有 Composer
    composer install --no-dev --optimize-autoloader

    # 權限設定（可選）
    #chown -R www-data:www-data .

    npm install

    # apidoc產出
    npm run doc

    echo "✅ [DEPLOY] Done at $(date)"
    ```

* chmod +x /opt/minecraft/webhook/deploy-php-minecraft-server-info.sh
* sudo git config --system --add safe.directory /opt/minecraft/php-minecraft-server-info
* sudo git config --system --get-all safe.directory
* sudo systemctl start webhook
* sudo systemctl enable webhook
## 模組簡介與圖標同步 (CLI & Cron)

為了避免日常 API 請求每次調用外部 Modrinth 或 CurseForge API 造成網路延遲或 Rate Limit，本專案提供專屬的 CLI 批次維護腳本，可預先向 Modrinth / CurseForge 批次匹配 SHA-1、串流下載高清圖標至本地 `public/static/mod_icons/`，並寫入永久快取 `public/static/mods_metadata_cache.json`。

### 指令用法

> **⚠️ 權限提醒（正式部署環境）**：
> 正式伺服器上 Nginx 與 PHP-FPM 通常由 `www-data` 使用者執行。為避免產生檔案的擁有者錯亂導致 Web 模式無權讀寫，**強烈建議使用 `sudo -u www-data` 執行腳本**。

```bash
# 1. 常規增量更新（跳過有效快取，預設 TTL 14 天）
sudo -u www-data php scripts/sync_mods_metadata.php

# 2. 強制全量重新查詢與下載圖標（忽略既有快取）
sudo -u www-data php scripts/sync_mods_metadata.php --force

# 3. 指定快取過期天數（例如刷新快取超過 7 天的模組）
sudo -u www-data php scripts/sync_mods_metadata.php --ttl=7

# 4. 指定特定模組分類
sudo -u www-data php scripts/sync_mods_metadata.php --type=common,client

# 5. 靜音模式（僅在發生錯誤時輸出，適合 Crontab 排程）
sudo -u www-data php scripts/sync_mods_metadata.php --quiet
```

### Crontab 自動排程範例

建議在伺服器設置 Crontab 定期排程（例如每週日清晨 04:00 自動執行一次增量更新），請直接掛入 `www-data` 帳號的專屬排程：

```bash
# 開啟 www-data 的專屬 crontab 編輯
sudo crontab -u www-data -e

# 加入以下排程（刷新超過 14 天的資料）
0 4 * * 0 /usr/bin/php /opt/minecraft/php-minecraft-server-info/scripts/sync_mods_metadata.php --ttl=14 --quiet >> /opt/minecraft/php-minecraft-server-info/cron_sync.log 2>&1
```

> **💡 若已手動執行過且權限錯亂的修復方式**：
> 若先前曾以一般使用者或 root 執行過，可執行以下指令將快取與圖標目錄重新指派回 `www-data`：
> ```bash
> sudo chown -R www-data:www-data public/static/
> sudo chmod -R 775 public/static/
> ```

## 效能測試

### 有無經過PHP後端下載單檔所花費的時間

雖然本專案有規劃下載檔案本體的功能，但是還是有規劃不經由本後端程式直連下載的方式。
而本專案也有設計Fallback機制，當Nginx直連設定失效回來跑到PHP這邊時，仍然可以正常提供檔案本體下載，但是效能會有落差。

以下是針對 OpenLoader-Forge-1.20.1-19.0.4.jar 檔案測試的伺服器回應花費時間測試

#### 經過PHP下載單檔花費時間
以 https://mc-api.yuaner.tw/mods/OpenLoader-Forge-1.20.1-19.0.4.jar/download 進行下載

PS. 此網址結構是為了對齊 /mods 網址結構，並兼顧舊型客戶端相容使用，始終都會經過此PHP後端執行

* 481 ms
* 463 ms
* 482 ms
* 480 ms

#### Nginx直連單檔下載花費時間
以 https://mc-api.yuaner.tw/files/mods/OpenLoader-Forge-1.20.1-19.0.4.jar  ，由Nginx直連進行下載。

PS. Nginx那邊需要額外設定，若沒有外正確設定導致Fallback銜接回此PHP後端，本後端仍然有提供這個Router路由可以正常提供下載，但就會回到上述提及有損耗過的效能。

* 277 ms
* 186 ms
* 189 ms
* 191 ms

## 參考資料
### API JSON Output

#### CurseForge API
GET https://api.curseforge.com/v1/mods/238222

```json
{
  "data": {
    "id": 238222, /* fileId (本後端應該用不到) */
    "name": "Journey Into the Light",
    "slug": "journey-into-the-light",
    "modId": 238222,
    "isAvailable": true,
    "displayName": "string",
    "fileName": "string",
    "releaseType": 1,
    "fileStatus": 1,
    "hashes": [
      {
        "value": "string",
        "algo": 1
      }
    ],
    "fileDate": "2019-08-24T14:15:22Z",
    "fileLength": 0,
    "downloadCount": 0,
    "fileSizeOnDisk": 0,
    "downloadUrl": "string",
    "hashes": [
        { "algo": 1, "value": "abc123..." }  /* SHA1 */
        { "algo": 2, "value": "abc123..." }  /* MD5 */
    ],
    "links": {
        "websiteUrl": "https://www.curseforge.com/minecraft/mc-mods/journey-into-the-light",
        "downloadUrl": "https://media.forgecdn.net/files/1234/567/journey-into-the-light-1.3.2.jar"
    }
  }
}
```

#### Prism Launcher

```json
[
    {
        "authors": [
            "Sinytra, FabricMC"
        ],
        "filename": "forgified-fabric-api-0.115.6+2.1.1+1.21.1.jar",
        "name": "Forgified Fabric API",
        "url": "https://www.curseforge.com/projects/889079",
        "version": "0.115.6+2.1.1+1.21.1"
    },
    {
        "authors": [
            "coderbot, IMS212"
        ],
        "filename": "iris-neoforge-1.8.12+mc1.21.1.jar",
        "name": "Iris",
        "url": "https://modrinth.com/mod/YL57xq9U",
        "version": "1.8.12-snapshot+mc1.21.1-local"
    }
]
```

#### ModUpdater (欠缺維護，只參考就好)
mod_list.json

```json
{
  "mods": [
    {
      "name": "journey-into-the-light",
      "filename": "journey-into-the-light-1.3.2.jar",
      "sha1": "abc123...",
      "download": "https://media.forgecdn.net/files/1234/567/journey-into-the-light-1.3.2.jar"
    }
  ]
}
```
