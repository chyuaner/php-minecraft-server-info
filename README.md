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

* vim /opt/minecraft/webhook/deploy-php-minecraft-server-info.sh
* sudo systemctl start webhook
* sudo systemctl enable webhook
* sudo journalctl -u webhook.service -f

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
