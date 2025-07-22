後端抓取Minecraft資訊
===

基本上會檢查你指定的mods資料夾裡的所有.jar檔，並輸出成JSON，提供Minecraft自製模組同步腳本、靜態前端頁面API呼叫使用。

## 簡介
這次需求相對單純，沒有資料庫、會員系統、前端頁面等複雜的中大型電商需求，故本專案將以原生PHP盡量不使用整套Framework的方式開發，盡可能的簡化系統複雜性來換取效能。

另外因為也實測在由PHP正常讀取zip檔內容後Render出結果所需要花的時間，和由PHP僅讀取資料夾內所有檔案的檔頭+已輸出JSON檔並重新解析後Render出來的時間相比，差距滿大的。所以會直接處理已輸出JSON當快取，不使用資料庫做快取來盡可能增進效能。

另外因為本次需求單純，也不使用Router這類的統一入口動態路由技術，而是以零散的php檔放置資料夾結構直接等於網址結構，讓原始的PHP可直接使用。
此外 `/public` 資料夾的PHP執行檔都使用 `index.php` 寫法，盡可能在無須設定rewrite的情況下，讓網址簡寫（不含.php字串）

另外在Nginx配合良好的情況下，可以設定特定資料夾直接跳過PHP執行來達到最佳效能。但是檔案載點還是有留下 index.php 以PHP開steam的方式直接output檔案本體（ `/public/files/mods/index.php`），讓網頁伺服器靜態設定失效的時候還能fallback以PHP執行來替代。


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

## 後端輸出草稿
### 網址規劃
* ✅GET https://your.server/api/mods : 模組清單（最重要）
* GET https://your.server/api/mods?type=html : 以HTML格式輸出模組清單（可能先略過）
* ✅GET https://your.server/api/mods?force=1 : 強制更新模組資訊
* ✅GET https://your.server/api/mods/{filename} : 單一模組完整資訊
* ✅GET https://your.server/files/mod/*.jar : 模組檔案本體載點（設定Nginx直連檔案本體，跳過PHP）

### 預計輸出API
```json
{
    "modsHash": "abc123...",
    "mods": [
    {
        "name": "journey-into-the-light", /* Prism Launcher */
        "authors": [ /* Prism Launcher */
            "Sinytra",
            "FabricMC"
        ],
        "version": "0.115.6+2.1.1+1.21.1", /* Prism Launcher *.
        "filename": "journey-into-the-light-1.3.2.jar", /* Prism Launcher */
        "fileName": "journey-into-the-light-1.3.2.jar", /* CurseForge API */
        "sha1": "abc123...", /* ModUpdater */
        "hashes": [ /* CurseForge API */
            {
            "value": "abc123...",
            "algo": 1
            }
        ],
        "url": "https://www.curseforge.com/projects/889079", /* Prism Launcher */
        "download": "https://media.forgecdn.net/files/1234/567/journey-into-the-light-1.3.2.jar", /* ModUpdater */
        "downloadUrl": "https://media.forgecdn.net/files/1234/567/journey-into-the-light-1.3.2.jar", /* CurseForge API */
        "websiteUrl": "https://www.curseforge.com/minecraft/mc-mods/journey-into-the-light", /* CurseForge API */
        "fileDate": "2019-08-24T14:15:22Z", /* CurseForge API */
        "fileLength": 0, /* CurseForge API */
    }
  ]
}
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
