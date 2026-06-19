# Mod 信息 API

Mod Information API 提供了对多种 mod 类型（NeoForge、Forge、Fabric）的 Minecraft 元数据的全面访问，支持自动 mod 同步、启动器集成以及服务器-客户端 mod 管理。这些 API 支持三种不同的 mod 类别：公共 mod、仅客户端 mod 和服务端 mod，并内置了缓存以优化性能，同时为各种消费者应用程序提供灵活的内容协商。

## API 架构

Mod Information API 构建在三层架构之上，分离了路由、业务逻辑和数据访问的职责。`public/mods.php` 中的路由层定义了 RESTful 端点，这些端点将 mod 发现和解析委托给 `McModUtils` 命名空间。`Mods` 类管理文件夹扫描和缓存，而单个 `Mod` 实例负责从 JAR 存档中提取元数据。

系统采用 **mod 类型映射** 策略，其中路由前缀（`mods`、`client-mods`、`server-mods`）对应配置数组中的配置键（`common`、`client`、`server`），从而能够针对每个 mod 类别动态配置路径和下载 URL。

来源：[public/mods.php](public/mods.php#L1-L54), [config.default.php](config.default.php#L13-L37)

## Mod 类型类别

API 支持三种不同的 mod 类型类别，每种类别服务于不同的同步场景：

| Mod 类型   | 路由前缀           | 配置键      | 用例              | 服务端过滤    |
|----------|----------------|----------|-----------------|----------|
| 公共 Mod   | `/mods`        | `common` | 服务器和客户端都需要的 Mod | 忽略服务端前缀  |
| 仅客户端 Mod | `/client-mods` | `client` | 仅在客户端需要的 Mod    | 忽略服务端前缀  |
| 仅服务端 Mod | `/server-mods` | `server` | 应仅在服务器上运行的 Mod  | 仅包含服务端前缀 |



**服务端前缀过滤** 机制使用可配置的前缀（`serveronly_`、`server_`）来区分不应分发给客户端的 mod。这对于性能优化以及防止因服务端依赖项导致客户端崩溃至关重要。

来源：[public/mods.php](public/mods.php#L1-L54), [config.default.php](config.default.php#L9-L10)

## API 端点

### 获取 Mod 列表端点

主要端点检索特定类别中所有 mod 的综合元数据。它支持用于缓存控制和输出格式自定义的查询参数。

**端点模式** ：`GET /:modType`，其中 `:modType` 为 `mods`、`client-mods`、`server-mods` 之一

**查询参数** ：

| 参数           | 类型      | 默认值   | 描述                        |
|--------------|---------|-------|---------------------------|
| `force`      | Boolean | false | 绕过缓存并强制重新解析               |
| `simple-md5` | Boolean | false | 返回简化的 `filename: md5` 对象  |
| `type`       | String  | auto  | 强制使用 `json` 或 `html` 输出格式 |



**完整 JSON 响应结构** ：

JSON

```json { "modsHash": "d9e9ae1ba3b4771ed389518777747fd38b641c25ef7a9a5ff2628e83d57f474d", "updateAt": "2025-07-27T14:52:10+08:00", "mods": [ { "name": "Apothic Attributes", "authors": ["Shadows_of_Fire"], "version": "2.9.0", "filename": "ApothicAttributes-1.21.1-2.9.0.jar", "fileName": "ApothicAttributes-1.21.1-2.9.0.jar", "sha1": "eed5808509eb279fd342cafebadd5b95accb4ef8", "hashes": { "value": "eed5808509eb279fd342cafebadd5b95accb4ef8", "algo": 1 }, "download": "https://mc-api.yuaner.tw/files/mods/ApothicAttributes-1.21.1-2.9.0.jar", "downloadUrl": "https://mc-api.yuaner.tw/files/mods/ApothicAttributes-1.21.1-2.9.0.jar" } ] } ``` 

**modsHash** 字段是所有 mod 文件元数据（相对路径、修改时间、文件大小）的 SHA256 哈希值，使客户端能够在不下载完整列表的情况下检测更改。**simple-md5** 模式提供了一种轻量级格式，非常适合快速完整性检查。

来源：[public/mods.php](public/mods.php#L107-L178), [src/McModUtils/Mod.php](src/McModUtils/Mod.php#L223-L251)

### 单个 Mod 信息端点

通过文件名检索特定 mod 文件的详细元数据。此端点用于在下载前验证 mod 的存在性和元数据。

**端点模式** ：`GET /:modType/:filename`

**参数** ：

| 参数         | 类型   | 描述                                          |
|------------|------|---------------------------------------------|
| `filename` | 路径参数 | JAR 文件名（例如 `ftb-quests-forge-2001.2.0.jar`） |
| `download` | 查询参数 | 设置为 `1` 以触发直接文件下载                           |



**示例请求** ：`GET /mods/ftb-quests-forge-2001.2.0.jar`

此端点返回与列表端点相同的 mod 对象结构，但仅针对单个文件。可选的 `download=1` 参数为那些更倾向于基于 API 的下载而不是静态文件服务的应用程序提供了直接下载机制。

来源：[public/mods.php](public/mods.php#L180-L216), [src/McModUtils/Mod.php](src/McModUtils/Mod.php#L223-L251)

### 下载单个 Mod 文件（PHP 后端）

通过 PHP 后端直接下载特定的 mod 文件。当 Nginx 直接文件服务不可用时，此端点用作后备方案。

**端点模式** ：`GET /:modType/:filename/download`

**响应头** ：

``` Content-Type: application/java-archive Content-Disposition: attachment; filename="ftb-quests-forge-2001.2.0.jar" Content-Length: [file size] X-Served-By: PHP ``` 

这种基于 PHP 的下载方法仅适用于后备场景。对于生产部署，请配置 Nginx 通过 `/files/:modFolder/:file` 路由直接提供 mod 文件，以获得显著更好的性能。PHP 后端会增加处理开销，这在处理大型 mod 文件或高请求量时会变得明显。 

来源：[public/mods.php](public/mods.php#L218-L244), [public/mods.php](public/mods.php#L246-L283)

### 直接文件下载端点

优化的下载端点，应在 Nginx 中配置以进行直接文件服务，而无需 PHP 处理。

**端点模式** ：`GET /files/:modFolder/:filename`

**参数** ：

| 参数          | 类型   | 选项                   | 描述        |
|-------------|------|----------------------|-----------|
| `modFolder` | 路径参数 | `mods`, `clientmods` | Mod 文件夹映射 |
| `filename`  | 路径参数 | 任何 JAR 文件            | 要下载的文件    |



`config.default.php` 中的配置将每个 mod 类型映射到下载 URL 路径。例如，`common` mods 路径（`/opt/minecraft/mc-server/mods`）通过 `/files/mods/` 提供，而 `client` mods 使用 `/files/clientmods/`。

来源：[public/mods.php](public/mods.php#L286-L341), [config.default.php](config.default.php#L13-L23)

### 下载所有 Mod 为 ZIP

生成并下载包含来自特定类别的所有 mod 的 ZIP 存档，并根据文件夹更改进行智能缓存失效。

**端点模式** ：`GET /:modType/zip`

**查询参数** ：

| 参数      | 类型      | 默认值   | 描述                |
|---------|---------|-------|-------------------|
| `force` | Boolean | false | 无论缓存如何，强制重新生成 ZIP |



**响应头** ：

``` Content-Type: application/zip Content-Disposition: attachment; filename="BarianMcMods整合包common-20250727-0906.zip" Content-Length: [zip size] Cache-Control: no-cache, must-revalidate ``` 

**ZIP 生成机制** 将当前文件夹哈希（基于文件路径、时间戳和大小）与存储在 ZIP 文件注释元数据中的哈希进行比较。如果它们不同，则创建一个嵌入当前哈希的新 ZIP。这使得无需文件系统扫描即可进行高效的缓存验证。

来源：[public/mods.php](public/mods.php#L56-L105), [src/McModUtils/Mods.php](src/McModUtils/Mods.php#L68-L134)

## Mod 解析系统

系统使用 **基于优先级的解析器** 从 JAR 存档中自动提取 mod 元数据，该解析器在回退到文件名解析之前会尝试多种 mod 格式标准。

### NeoForge/Forge 解析器

NeoForge 和 Forge mod 都使用位于 `META-INF/neoforge.mods.toml` 或 `META-INF/mods.toml` 的类似 TOML 元数据文件。解析器使用正则表达式模式提取字段：

PHP

``` // displayName 提取 if (preg_match('/displayName\s*=\s*"([^"]+)"/', $tomlRaw, $m)) { $result['name'] = $m[1]; } // version 提取 if (preg_match('/version\s*=\s*"([^"]+)"/', $tomlRaw, $m)) { $result['version'] = $m[1]; } // authors 提取 if (preg_match('/authors\s*=\s*"([^"]+)"/', $tomlRaw, $m)) { $result['authors'] = [trim($m[1])]; } ``` 

### Fabric 解析器

Fabric mod 使用位于 `fabric.mod.json` 的 JSON 元数据文件，其结构简单：

PHP

``` $jsonData = json_decode($raw, true); $result['name'] = $jsonData['name'] ?? ($jsonData['id'] ?? null); $result['version'] = $jsonData['version'] ?? null; $result['authors'] = is_array($jsonData['authors']) ? $jsonData['authors'] : [$jsonData['authors'] ?? []]; ``` 

### 文件名回退解析器

当 mod 元数据文件不可用时，系统使用模式匹配从文件名中提取信息：

PHP

``` // 模式：modname-version-modloader 或 modname-version if (preg_match('/^(.+?)-((?:neoforge|forge|fabric)[\w.\\+\\-]*)$/i', $basename, $m)) { $modName = $m[1]; $version = $m[2]; } elseif (preg_match('/^(.+?)-(\d[\w.\\+\\-]*)$/', $basename, $m)) { $modName = $m[1]; $version = $m[2]; } ``` 

这种回退机制确保即使没有适当元数据的 mod 也能被识别和同步，尽管可靠性低于基于存档的解析。

来源：[src/McModUtils/Mod.php](src/McModUtils/Mod.php#L36-L96), [src/McModUtils/Mod.php](src/McModUtils/Mod.php#L98-L130), [src/McModUtils/Mod.php](src/McModUtils/Mod.php#L132-L154)

## 缓存机制

缓存系统通过存储解析的 mod 数据并避免重复的文件系统操作和存档解析，提供了显著的性能优化。

### 两级缓存策略

### 缓存元数据结构

缓存文件以 JSON 格式存储，具有以下结构：

JSON

```json { "folder_hashed": "d9e9ae1ba3b4771ed389518777747fd38b641c25ef7a9a5ff2628e83d57f474d", "update_at": "2025-07-27T14:52:10+08:00", "mods": "O:21:\"serialized_mod_objects\"" } ``` 

**文件夹哈希** 通过以一致的顺序连接文件元数据来计算：

PHP

``` $hashComponents[] = $relativePath . '|' . $mtime . '|' . $size; sort($hashComponents); $hash = hash('sha256', implode("\n", $hashComponents)); ``` 

这种方法确保对 mods 文件夹的任何更改（添加、删除、修改）都会使缓存失效，而无需手动清除缓存。

### 缓存文件命名

缓存文件使用 **元哈希** 命名，该哈希包含 mods 路径和过滤配置：

PHP

``` $hPath = md5(serialize($this->path)); $hIgnorePrefixs = md5(serialize($this->ignorePrefixs)); $hOnlyPrefixs = md5(serialize($this->onlyPrefixs)); $metaHashed = md5($hPath . '|' . $hIgnorePrefixs . '|' . $hOnlyPrefixs); // 缓存文件：mods-[metaHashed].json ``` 

这种设计确保过滤规则的更改（例如，切换服务端前缀过滤）会自动生成单独的缓存文件，从而防止返回不正确的 mod 列表。

缓存存储在 `public/static/` 中，文件名如 `mods-[hash].json`。对于生产部署，请确保此目录可由 PHP 进程写入，并考虑配置 Nginx 直接提供这些静态文件以获得额外的性能提升。 

来源：[src/McModUtils/Mods.php](src/McModUtils/Mods.php#L9-L31), [src/McModUtils/Mods.php](src/McModUtils/Mods.php#L68-L101), [src/McModUtils/Mods.php](src/McModUtils/Mods.php#L160-L195)

## 响应格式化和内容协商

`ResponseFormatter` 类根据客户端首选项提供智能的内容协商，支持 JSON 和 HTML 输出格式。

### 内容协商逻辑

格式化器通过以下优先级顺序确定输出格式：

  1. **查询参数覆盖** ：`?type=json` 或 `?json=1` 强制 JSON 输出
  2. **Accept 头** ：检查 HTTP Accept 头中的 `application/json`
  3. **默认** ：对于基于浏览器的请求，回退到 HTML 输出



### JSON 输出格式

JSON 响应使用 `JSON_UNESCAPED_UNICODE` 以正确支持国际字符，并包含适当的头：

``` Content-Type: application/json; charset=utf-8 ``` 

### HTML 输出格式

对于浏览器请求，系统生成递归 HTML 列表，以适当的转义显示嵌套数据结构。数组呈现为 `<ul>` 元素，对象显示其类名，URL 变为可点击的链接。

这种 HTML 回退使开发人员能够在浏览器中直接调试 API 响应，而无需额外工具。

来源：[src/App/ResponseFormatter.php](src/App/ResponseFormatter.php#L1-L113), [public/mods.php](public/mods.php#L172-L178)

## 配置

Mod API 通过 `config.default.php` 中的 `mods` 部分进行配置。每种 mod 类型需要三个配置条目：

| 配置键                        | 类型      | 描述            | 示例                              |
|----------------------------|---------|---------------|---------------------------------|
| `path`                     | String  | mods 文件夹的绝对路径 | `/opt/minecraft/mc-server/mods` |
| `dl_urlpath`               | String  | 直接下载的 URL 路径  | `/files/mods/`                  |
| `ignore_serverside_prefix` | Boolean | 过滤掉仅服务端 mod   | `true`                          |
| `only_serverside_prefix`   | Boolean | 仅包含仅服务端 mod   | `false`                         |



全局 `serverside_prefixs` 数组定义了哪些文件名前缀表示服务端 mod：

PHP

``` 'serverside_prefixs' => ['serveronly_', 'server_'] ``` 

以这些前缀开头的文件会自动从客户端 mod 列表中排除，除非通过 `server-mods` 端点特别请求。

来源：[config.default.php](config.default.php#L13-L37), [src/McModUtils/Mods.php](src/McModUtils/Mods.php#L32-L48)

## 使用示例

### 基本 Mod 列表检索

BASH

```bash curl https://mc-api.yuaner.tw/mods?type=json # 强制刷新缓存 curl https://mc-api.yuaner.tw/mods?force=1 # 获取简化的 MD5 格式以进行完整性检查 curl https://mc-api.yuaner.tw/mods?simple-md5=1 ``` 

### 客户端 Mod 同步

BASH

```bash # 仅获取客户端 mod（排除服务端） curl https://mc-api.yuaner.tw/client-mods # 下载完整的客户端 mod 包 curl -O https://mc-api.yuaner.tw/client-mods/zip ``` 

### 单个 Mod 操作

BASH

```bash # 获取 mod 元数据 curl https://mc-api.yuaner.tw/mods/ftb-quests-forge-2001.2.0.jar # 下载特定 mod curl -O https://mc-api.yuaner.tw/files/mods/ftb-quests-forge-2001.2.0.jar # 通过 PHP 后端下载的替代方法 curl -O https://mc-api.yuaner.tw/mods/ftb-quests-forge-2001.2.0.jar/download ``` 

### 与启动器集成

API 响应结构与多种启动器格式兼容：

  * **Prism Launcher** ：使用 `name`、`authors`、`version`、`filename` 字段
  * **CurseForge API** ：使用 `fileName`、`downloadUrl`、`hashes` 结构
  * **ModUpdater** ：使用 `sha1`、`download` 字段



这种兼容性使得可以与流行的 Minecraft 启动器无缝集成，而无需数据转换。

来源：[public/mods.php](public/mods.php#L107-L178), [src/McModUtils/Mod.php](src/McModUtils/Mod.php#L223-L251)

## 后续步骤

要全面了解 mod 解析系统，请参阅 [Mod 解析系统 (NeoForge, Forge, Fabric) ](7-mod-parsing-system-neoforge-forge-fabric.md)。要详细了解缓存机制，请参阅 [缓存机制和缓存失效 ](9-caching-mechanism-and-cache-invalidation.md)。对于服务器状态监控 API，请继续阅读 [服务器状态 API ](12-server-status-apis.md)。
