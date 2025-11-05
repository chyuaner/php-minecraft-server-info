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

### 需要依賴的PHP extensions
* php-zip
* php-gd

## 建置&啟動開發伺服器
```
git clone <url>
cd php-minecraft-mods-info
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


### apidoc
伺服器需要安裝
```
apt install nodejs npm
sudo chown -R 33:33 "/var/www/.npm"
```


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
