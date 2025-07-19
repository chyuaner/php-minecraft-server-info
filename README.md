Minecraft Mods 模組清單後端
===

基本上會檢查你指定的mods資料夾裡的所有.jar檔，並輸出成JSON，提供Minecraft自製模組同步腳本、靜態前端頁面API呼叫使用。


## 建置&啟動開發伺服器

### 啟動簡易伺服器
```
php -S 127.0.0.1:8000 -t public
```

## 上線部署
### Nginx設定

## 後端輸出草稿
### 網址規劃
* GET https://your.server/api/mods : 模組清單（最重要）
* GET https://your.server/api/mods?type=html : 以HTML格式輸出模組清單（可能先略過）
* GET https://your.server/api/mods?force=1 : 強制從上游更新模組資訊
* GET https://your.server/api/mods/{slug} : 單一模組完整資訊（可能先略過）
* GET https://your.server/files/mod/*.jar : 模組檔案本體載點（設定Nginx直連檔案本體，跳過PHP）
* GET https://your.server/zip/mods : 模組打包下載
* GET https://your.server/zip/mods?force=1 : 模組打包下載，強制重新打包zip

### 預計輸出API
```json
{
    "modsHash": "abc123...",
    "mods": [
    {
        "name": "journey-into-the-light", // Prism Launcher
        "authors": [ // Prism Launcher
            "Sinytra, FabricMC"
        ],
        "version": "0.115.6+2.1.1+1.21.1", // Prism Launcher
        "filename": "journey-into-the-light-1.3.2.jar", // Prism Launcher
        "fileName": "journey-into-the-light-1.3.2.jar", // CurseForge API
        "sha1": "abc123...", // ModUpdater
        "hashes": [ // CurseForge API
            {
            "value": "abc123...",
            "algo": 1
            }
        ],
        "url": "https://www.curseforge.com/projects/889079", // Prism Launcher
        "download": "https://media.forgecdn.net/files/1234/567/journey-into-the-light-1.3.2.jar", // ModUpdater
        "downloadUrl": "https://media.forgecdn.net/files/1234/567/journey-into-the-light-1.3.2.jar", // CurseForge API
        "websiteUrl": "https://www.curseforge.com/minecraft/mc-mods/journey-into-the-light", // CurseForge API
        "fileDate": "2019-08-24T14:15:22Z", // CurseForge API
        "fileLength": 0, // CurseForge API
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
    "id": 238222, // fileId (本後端應該用不到)
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
        { "algo": 1, "value": "abc123..." }  // SHA1
        { "algo": 2, "value": "abc123..." }  // MD5
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
