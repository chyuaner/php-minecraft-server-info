官網與遊戲連線網址: <https://mc.yuaner.tw/>

## 幾個重要網址

* <https://mc-api.yuaner.tw/mods>
* <https://mc-api.yuaner.tw/client-mods>
* <https://mc-api.yuaner.tw/ofolder>
以上全部都可以在網址後面加 ?json=1 強制以JSON輸出，如果不輸入也會由瀏覽器傳的Header自動判斷。然後現在無論是網頁版還是JSON都有快取了

還有 <https://mc-api.yuaner.tw/mods?simple-md5=1&json=1> 是配合Barian的客戶端先做一個相容版本，也適用在 mods, client-mods, server-mods。

### 下載壓縮檔要用
* <https://mc-api.yuaner.tw/mods/zip>

（client-mods, server-mods 依此類推）

還有 <https://mc-api.yuaner.tw/ofolder/zip>

ofolder已經涵蓋所有的檔案，包含config, tacz, defaultconfig......這些，如果覺得太肥大的話，我有設計
* <https://mc-api.yuaner.tw/ofolder/defaultconfigs> 可以只拉特定的資料夾
* <https://mc-api.yuaner.tw/ofolder/defaultconfigs/zip> 只針對這個資料夾的打包下載功能也可以用！

## 靜態檔案資源
* <https://mc-api.yuaner.tw/docs/>
* https://mc-api.yuaner.tw/files/{資料夾名}
開頭都是走Nginx直連檔案下載，相較於 miniserve。

## 至於其他附加功能
* <https://mc-api.yuaner.tw/ping>
* <https://mc-api.yuaner.tw/banner>