# 文件服务 API

文件服务 API 提供对 Minecraft 服务器文件的直接访问，支持通过 HTTP 端点下载模组、配置文件、资源包和其他服务器资源。这些 API 将服务器存储与 Web 界面连接起来，既支持单独文件下载，也支持通过压缩归档进行批量操作。

## 架构概述

文件服务系统采用双层架构运行，将文件发现与交付分离。发现层负责扫描目录、计算文件元数据和哈希值，并缓存结果以提升性能。交付层通过直接下载或生成的 zip 归档来提供文件，利用智能缓存失效机制确保内容新鲜度，避免不必要的重复计算。

该架构通过在持久化缓存文件中维护计算出的元数据，并仅在底层文件更改时重新生成内容，从而确保高效的文件服务。

## 配置系统

文件服务端点的路径源自全局配置，该配置将物理磁盘位置映射到 URL 前缀。这种抽象允许灵活的服务器布局，而无需修改端点代码。

来源：[config.default.php](config.default.php#L21-L34)

PHP

``` 'other_folders' => [ '/opt/minecraft/mc-server/config' => '/files/config/', '/opt/minecraft/mc-server/defaultconfigs' => '/files/defaultconfigs/', '/opt/minecraft/mc-server/kubejs' => '/files/kubejs/', '/opt/minecraft/mc-server/modernfix' => '/files/modernfix/', '/opt/minecraft/mc-server/resourcepacks' => '/files/resourcepacks/', '/opt/minecraft/mc-server/tacz' => '/files/tacz/', '/opt/minecraft/mc-server/tlm_custom_pack' => '/files/tlm_custom_pack/', ] ``` 

| 配置属性         | 类型     | 用途              |
|--------------|--------|-----------------|
| 键            | 物理路径   | 要扫描的绝对文件系统路径    |
| 值            | URL 前缀 | 用于下载的 HTTP 路径前缀 |
| `mods_path`  | 字符串    | 模组目录的基础路径       |
| `dl_urlpath` | 字符串    | 模组下载的 URL 前缀    |



系统会根据这些映射自动路由请求，从而能够在不更改代码的情况下动态添加新文件夹。

## 直接文件下载

### 单文件服务

直接文件下载通过简单的 HTTP GET 请求提供原始文件访问。系统会自动检测 MIME 类型并设置适当的标头以确保浏览器和客户端的兼容性。当请求的是目录而非文件时，端点将返回目录内容的 JSON 列表。

来源：[public/other_files.php](public/other_files.php#L324-L383)

PHP

```php $sendDownload = function (Request $request, string $modFilePath) : Response { // 目录列表回退 if (is_dir($modFilePath)) { $list = []; $it = new \DirectoryIterator($modFilePath); foreach ($it as $item) { if ($item->isDot()) continue; $list[] = [ 'name' => $item->getFilename(), 'is_dir' => $item->isDir(), 'size' => $item->isFile() ? $item->getSize() : null, 'mtime' => $item->getMTime(), ]; } $formatter = new ResponseFormatter(); return $formatter->format($request, $list); } // MIME 检测链 $mime = 'application/octet-stream'; if (function_exists('finfo_open')) { $finfo = finfo_open(FILEINFO_MIME_TYPE); $detected = finfo_file($finfo, $modFilePath); if ($detected !== false) $mime = $detected; finfo_close($finfo); } elseif (function_exists('mime_content_type')) { $detected = @mime_content_type($modFilePath); if ($detected !== false) $mime = $detected; } header('Content-Type: ' . $mime); header('Content-Disposition: attachment; filename="' . basename($modFilePath) . '"'); header('Content-Length: ' . filesize($modFilePath)); header('X-Served-By: PHP'); readfile($modFilePath); }; ``` 

| 端点                    | 方法  | 描述            | 示例 URL                                           |
|-----------------------|-----|---------------|--------------------------------------------------|
| `/files/:folder/*`    | GET | 下载配置文件夹中的任何文件 | `/files/config/ftbquests/quests/data.snbt`       |
| `/files/mods/*`       | GET | 下载模组 JAR 文件   | `/files/mods/ApothicAttributes-1.21.1-2.9.0.jar` |
| `/files/clientmods/*` | GET | 下载客户端专用模组     | `/files/clientmods/iris-mc1.20.1-1.6.9.jar`      |



MIME 检测链优先使用 `finfo_open`（推荐用于准确的类型检测）而非 `mime_content_type`，并提供安全的 `application/octet-stream` 回退机制，确保即使在类型检测失败时文件也始终可下载。 

## 文件夹元数据 API

### 文件信息端点

文件夹元数据端点提供配置目录中所有文件的结构化列表，包括用于完整性验证的可选加密哈希。这些端点支持缓存控制，以平衡新鲜度和性能。

来源：[public/other_files.php](public/other_files.php#L35-L68), [src/McModUtils/Folder.php](src/McModUtils/Folder.php#L61-L132)

PHP

```php $group->get('', function (Request $request, Response $response, array $args) { $isForce = $request->getQueryParams()['force'] ?? false; $directorys = array_keys($GLOBALS['config']['other_folders']); $fileInfos = []; foreach ($directorys as $directory) { $folder = new Folder($directory); $folder->fetchFilesRecursively( fetchMd5: true, fetchSha1: true, force: $isForce, enableCache: true ); $fileInfos += $folder->getFileInfos(); } $filesOutput = array_values($fileInfos); $formatter = new ResponseFormatter(); return $formatter->format($request, $filesOutput); }); ``` 

| 端点                 | 方法  | 查询参数          | 描述                             |
|--------------------|-----|---------------|--------------------------------|
| `/ofolder`         | GET | `force=false` | 列出所有配置文件夹中的所有文件                |
| `/ofolder/:folder` | GET | `force=false` | 列出特定文件夹（如 config、kubejs 等）中的文件 |



**响应结构：**

JSON

``` { "filename": "world_generation.json5", "fileName": "world_generation.json5", "path": "config/biomeswevegone/world_generation.json5", "download": "https://mc-api.yuaner.tw/files/config/biomeswevegone/world_generation.json5", "downloadUrl": "https://mc-api.yuaner.tw/files/config/biomeswevegone/world_generation.json5", "mtime": 1762543534, "size": 2908, "md5": "b9887200d43aa51425e084754b25506a", "sha1": "352932a6ba5b85f3137bf4a5fe52b106ce470d41" } ``` 

### 缓存机制

Folder 类实现了智能缓存，可显著减少磁盘 I/O 和哈希计算开销。缓存文件存储带有文件修改时间和大小 SHA256 哈希值的文件元数据，从而能够在不重新扫描文件系统的情况下检测更改。

来源：[src/McModUtils/Folder.php](src/McModUtils/Folder.php#L55-L80)

PHP

```php public function loadCache(): bool { $cacheFilePath = $this->getCacheFilePath(); if (file_exists($cacheFilePath)) { $raw = file_get_contents($cacheFilePath); $data = json_decode($raw, true); if (!empty($data['fileInfos'])) { $this->fileInfos = $data['fileInfos']; $this->hashed = $data['folder_hashed'] ?? null; $this->check(); return true; } } return false; } ``` 

缓存系统执行增量更新：在扫描目录时，它仅为已更改（通过 mtime/size 比较检测）或新添加的文件重新计算哈希，同时保留未更改文件的缓存数据。 

## 归档下载 API

### ZIP 归档生成

归档端点提供以压缩 ZIP 文件形式进行的批量文件下载，这对于整合包分发和客户端-服务器同步至关重要。系统通过将所有文件的组合哈希存储在 ZIP 归档的注释字段中来实现内容感知缓存，从而能够在内容更改时自动重新生成。

来源：[public/other_files.php](public/other_files.php#L70-L122), [src/McModUtils/Zip.php](src/McModUtils/Zip.php#L52-L115)

PHP

```php $group->get('/zip', function (Request $request, Response $response, array $args) { $isForce = $request->getQueryParams()['force'] ?? false; $directorys = array_keys($GLOBALS['config']['other_folders']); $folderContentHasheds = []; $addFiles = []; foreach ($directorys as $directory) { $folder = new Folder($directory); $folder->fetchFilesRecursively(); $fileInfos = $folder->getFileInfos(); $mAddFiles = array_map(function($fileInfo) { return $fileInfo['path']; }, $fileInfos); $addFiles += $mAddFiles; $folderContentHasheds[] = $folder->getHashed(); } $combined = implode('|', $folderContentHasheds); $folderContentHashed = md5($combined); $zip_path = BASE_PATH.'/public/static/folder-'.$folderContentHashed.'.zip'; $zip = new Zip($zip_path); $zipedHash = $zip->getZipComment(); if ($isForce || (!empty($folderContentHashed) && $folderContentHashed !== $zipedHash)) { $zip->zipRelativePath($addFiles, $folderContentHashed); } header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="'.$zipFileName.'"'); header('Content-Length: ' . filesize($zip_path)); readfile($zip_path); }); ``` 

ZipArchive 实现使用注释字段存储内容哈希，创建了一个无需外部元数据存储的自验证归档系统。

来源：[src/McModUtils/Zip.php](src/McModUtils/Zip.php#L26-L38)

PHP

```php public function getZipComment(): ?string { $zipPath = $this->path; if (!file_exists($zipPath)) return null; $zip = new ZipArchive(); if ($zip->open($zipPath) === true) { $comment = $zip->getArchiveComment(); $zip->close(); return $comment; } return null; } ``` 

| 端点                     | 方法  | 查询参数          | 描述              | 文件名模式                                   |
|------------------------|-----|---------------|-----------------|-----------------------------------------|
| `/ofolder/zip`         | GET | `force=false` | 将所有文件夹下载为单个 ZIP | `BarianMcMods整合包(other-all)-Ymd-Hi.zip` |
| `/ofolder/:folder/zip` | GET | `force=false` | 将单个文件夹下载为 ZIP   | `BarianMcMods整合包(folder)-Ymd-Hi.zip`    |
| `/mods/zip`            | GET | `force=false` | 下载所有通用模组        | `BarianMcMods整合包common-Ymd-Hi.zip`      |
| `/client-mods/zip`     | GET | `force=false` | 下载客户端模组         | `BarianMcMods整合包client-Ymd-Hi.zip`      |



文件名包含台湾时区（Asia/Taipei）的时间戳，以便于识别和版本控制。

## 性能考量

### 哈希计算优化

对于需要加密哈希（MD5、SHA1）的操作，系统采用选择性计算策略。当 `force` 参数为 false 时，未更改的文件将重用缓存的哈希值，而新文件或已修改的文件则会触发新的计算。这种设计避免了每次请求都进行昂贵的哈希操作。

来源：[src/McModUtils/Folder.php](src/McModUtils/Folder.php#L115-L170)

PHP

``` $needRefresh = $force || !isset($this->fileInfos[$path]); if (!$needRefresh && $fetchMeta && isset($this->fileInfos[$path]['mtime'], $this->fileInfos[$path]['size'])) { if ($this->fileInfos[$path]['mtime'] !== $currMtime || $this->fileInfos[$path]['size'] !== $currSize) { $needRefresh = true; } } if (!$needRefresh) { // 重用缓存，仅计算缺失的哈希 $cached = $this->fileInfos[$path]; if ($fetchMd5 && empty($cached['md5'])) { $this->fileInfos[$path]['md5'] = md5_file($path); } if ($fetchSha1 && empty($cached['sha1'])) { $this->fileInfos[$path]['sha1'] = sha1_file($path); } } ``` 

用于 ZIP 失效的组合哈希使用所有文件路径、修改时间和大小（用换行符连接）的 SHA256 值，确保任何文件更改（内容、名称或元数据）都会触发归档重新生成。 

## 后续步骤

若要全面实施文件服务：

  1. **模组信息 API** ([11-mod-information-apis ](11-mod-information-apis.md)) - 探索详细的模组元数据端点，包括针对 Forge、NeoForge 和 Fabric 格式的解析逻辑
  2. **缓存机制** ([9-caching-mechanism-and-cache-invalidation ](9-caching-mechanism-and-cache-invalidation.md)) - 深入了解缓存失效策略和存储格式
  3. **响应格式化** ([14-response-formatting-and-content-negotiation ](14-response-formatting-and-content-negotiation.md)) - 了解如何根据客户端首选项（JSON、XML、纯文本）格式化响应


