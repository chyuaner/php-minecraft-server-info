# Mod 解析系统 (NeoForge, Forge, Fabric)

Mod 解析系统提供了一个全面的解决方案，用于跨多种加载器类型提取和管理 Minecraft 模组元数据。它实施了一个多层解析策略，并具备智能回退机制，能够在从 JAR 归档文件中提取元数据时保持稳健性，同时通过策略性缓存维持高性能。

## 系统架构

解析系统围绕三个核心组件构建，它们协同工作以发现、解析和服务模组元数据。`Mod` 类处理单个文件解析，支持 NeoForge、Forge 和 Fabric 加载器，而 `Mods` 管理模组文件集合，具备文件夹扫描和过滤功能。支持实用工具包括用于递归文件管理的 `Folder` 和用于归档生成的 `Zip`。

该架构展示了清晰的关注点分离：`mods.php` 中的路由逻辑将 URL 路径映射到模组类别，`Mods` 类处理带过滤和缓存的集合级操作，`Mod` 类通过特定于加载器的策略封装了单个解析逻辑。

来源：[public/mods.php](public/mods.php#L1-L200), [src/McModUtils/Mod.php](src/McModUtils/Mod.php#L1-L200), [src/McModUtils/Mods.php](src/McModUtils/Mods.php#L1-L200)

## 多加载器解析策略

该系统实施了一个**基于优先级的解析级联** ，按顺序尝试多种提取方法。这种方法确保了跨不同模组加载平台的最大兼容性，并在结构化元数据不可用时优雅降级。

### 解析器执行流程

解析模组文件时，系统会打开 JAR 归档文件并按顺序尝试每种解析方法：

  1. **NeoForge 解析器** ：搜索 `META-INF/neoforge.mods.toml` 并使用正则表达式匹配提取 `displayName`、`version` 和 `authors` 字段
  2. **Forge 解析器** ：搜索 `META-INF/mods.toml`，由于格式相似性，复用 NeoForge TOML 解析器
  3. **Fabric 解析器** ：搜索 `fabric.mod.json` 并解码 JSON 以提取 `name`（带有 `id` 回退）、`version` 和 `authors`
  4. **文件名解析器** ：作为回退手段，应用正则表达式从文件名本身提取名称和版本



解析逻辑会增量应用结果，这意味着如果 NeoForge 解析器成功提取了名称但未能获取版本，Fabric 解析器可以补充缺失的版本字段。这种累积方法最大化了数据完整性。

来源：[src/McModUtils/Mod.php](src/McModUtils/Mod.php#L50-L100)

### 特定加载器的元数据提取

| 加载器      | 元数据文件                         | 提取方法    | 提取字段                          |
|----------|-------------------------------|---------|-------------------------------|
| NeoForge | `META-INF/neoforge.mods.toml` | 正则表达式匹配 | displayName, version, authors |
| Forge    | `META-INF/mods.toml`          | 正则表达式匹配 | displayName, version, authors |
| Fabric   | `fabric.mod.json`             | JSON 解码 | name/id, version, authors     |
| 回退       | N/A (filename)                | 正则表达式匹配 | name, version                 |



NeoForge 和 Forge 解析器使用相同的正则表达式模式，识别这些加载器格式之间的结构兼容性。Fabric 解析器由于 JSON 结构需要不同的处理，并且支持 `name` 和 `id` 字段用于模组识别。

来源：[src/McModUtils/Mod.php](src/McModUtils/Mod.php#L120-L170)

### 回退文件名解析

当嵌入式元数据不可用时，文件名解析器充当关键的回退机制。它实现了两个旨在捕获常见命名约定的正则表达式模式：

**模式 1** ：`^(.+?)-((?:neoforge\|forge\|fabric)[\w.\+\-]*)$`

  * 捕获模组名称后跟特定于加载器的版本后缀
  * 示例：`ApothicAttributes-1.21.1-2.9.0` → name: `ApothicAttributes`, version: `1.21.1-2.9.0`



**模式 2** ：`^(.+?)-(\d[\w.\+\-]*)$`

  * 捕获模组名称后跟任何以数字开头的版本
  * 针对非标准命名的更宽松回退



解析器还通过在模式匹配之前去除括号表示法的前缀（例如 `[CLIENT]modname.jar`）来处理带前缀的文件名。

来源：[src/McModUtils/Mod.php](src/McModUtils/Mod.php#L172-L200)

文件名解析器在应用版本模式之前，会使用正则表达式去除基于括号的前缀，如 `[CLIENT]` 或 `[SERVER]`。这允许模组在文件名中携带安装上下文，而不会破坏解析逻辑。

## 集合管理和过滤

`Mods` 类提供了复杂的文件夹扫描功能，支持基于前缀的过滤，从而能够精细控制不同模组类别（common、client、server）中显示哪些模组。此过滤机制对于区分客户端模组、服务端模组和共享模组至关重要。

### 基于前缀的过滤系统

配置定义了三种具有不同过滤行为的模组类别：

  * **通用模组 (Common Mods)** ：默认路径，设置 `ignore_serverside_prefix: true`，过滤掉以 `serveronly_` 或 `server_` 开头的文件
  * **客户端模组 (Client Mods)** ：单独的 clientmods 路径，设置 `ignore_serverside_prefix: true`，具有相同的排除行为
  * **服务端模组 (Server Mods)** ：使用与通用模组相同的路径，但设置 `only_serverside_prefix: true`，仅显示以 server 前缀开头的文件



来源：[config.default.php](config.default.php#L1-L67), [public/mods.php](public/mods.php#L130-L200)

### 文件夹分析算法

扫描过程实现了一个递归目录迭代器，并带有智能过滤：

  1. **目录结构** ：使用带 `SKIP_DOTS` 的 `RecursiveDirectoryIterator` 遍历嵌套的模组文件夹
  2. **文件扩展名过滤** ：仅处理 `.jar` 和 `.jar.client` 文件
  3. **前缀过滤** ：根据配置应用 `ignorePrefixs` 或 `onlyPrefixs` 数组
  4. **目录排除** ：跳过 `.connector`、`.index`、`.git`、`logs` 和 `cache` 目录
  5. **哈希生成** ：从连接的 `relativePath|mtime|size` 创建 SHA-256 哈希以进行缓存验证



来源：[src/McModUtils/Mods.php](src/McModUtils/Mods.php#L40-L100)

### 基于哈希的缓存失效

缓存系统使用两级哈希以获得最佳性能：

  * **元哈希** ：路径、ignorePrefixs 和 onlyPrefixs 配置的 MD5 哈希 - 确定缓存文件身份
  * **内容哈希** ：所有文件元数据（路径、mtime、大小）的 SHA-256 哈希 - 确定缓存内容是否有效



请求模组时，系统检查是否存在与元哈希匹配的缓存文件。如果找到，它会将存储的内容哈希与当前文件夹哈希进行比较。不匹配会触发完全重新扫描和缓存重新生成。

来源：[src/McModUtils/Mods.php](src/McModUtils/Mods.php#L100-L140)

## 输出格式和响应结构

系统生成多种输出格式，以支持各种启动器和模组管理器集成。`output()` 方法生成全面的元数据，其字段匹配 CurseForge API、Prism Launcher 和 ModUpdater 约定。

### 标准输出格式

标准 JSON 响应包含以下结构：

JSON

```json { "modsHash": "d9e9ae1ba3b4771ed389518777747fd38b641c25ef7a9a5ff2628e83d57f474d", "updateAt": "2025-07-27T14:52:10+08:00", "mods": [ { "name": "Apothic Attributes", "authors": ["Shadows_of_Fire"], "version": "2.9.0", "filename": "ApothicAttributes-1.21.1-2.9.0.jar", "fileName": "ApothicAttributes-1.21.1-2.9.0.jar", "sha1": "eed5808509eb279fd342cafebadd5b95accb4ef8", "hashes": { "value": "eed5808509eb279fd342cafebadd5b95accb4ef8", "algo": 1 }, "download": "https://mc-api.yuaner.tw/files/mods/ApothicAttributes-1.21.1-2.9.0.jar", "downloadUrl": "https://mc-api.yuaner.tw/files/mods/ApothicAttributes-1.21.1-2.9.0.jar" } ] } ``` 

响应包含 `filename` 和 `fileName` 字段，以兼容不同启动器的期望。同样，`download` 和 `downloadUrl` 为不同的 API 提供相同的目的。

来源：[src/McModUtils/Mod.php](src/McModUtils/Mod.php#L250-L328), [public/mods.php](public/mods.php#L200-L260)

### 简化的 MD5 输出

对于轻量级集成场景，系统支持 `simple-md5` 查询参数，返回紧凑的键值映射：

JSON

``` { "ApothicAttributes-1.21.1-2.9.0.jar": "a9312e369434ca703b582ce0de4d612a", "embeddium-0.3.31+mc1.20.1.jar": "1dfb2ee49ce9ad5d484ff3eea0d628b7" } ``` 

此格式对于快速完整性检查或最小元数据要求特别有用。

来源：[public/mods.php](public/mods.php#L230-L260)

## API 端点结构

模组系统暴露了按模组类型组织的 RESTful 端点，支持列出、单个文件查询和批量下载操作。

### 端点概述

| 端点                             | HTTP 方法 | 描述              |
|--------------------------------|---------|-----------------|
| `/:modType`                    | GET     | 检索指定类别的完整模组列表   |
| `/:modType/:filename`          | GET     | 获取特定模组文件的元数据    |
| `/:modType/:filename/download` | GET     | 通过 PHP 下载特定模组文件 |
| `/:modType/zip`                | GET     | 将所有模组下载为 ZIP 归档 |



`:modType` 参数接受 `mods`、`client-mods` 或 `server-mods`，对应于三个配置类别。

来源：[public/mods.php](public/mods.php#L1-L80)

### 查询参数

  * `force`：布尔值（默认：false）- 绕过缓存并强制完全重新扫描
  * `simple-md5`：布尔值（默认：false）- 返回简化的文件名到 MD5 的映射
  * `download`：布尔值（默认：false）- 触发文件下载而不是元数据



来源：[public/mods.php](public/mods.php#L130-L200)

## 性能优化技术

系统采用多种缓存策略来最小化文件系统 I/O 和解析开销，确保即使在大规模模组集合中也能高效运行。

### 双层缓存架构

**第 1 层 - 序列化对象缓存** ：将解析的 `Mod` 对象以 PHP 序列化格式存储，并进行元数据哈希验证。这避免了重复的 JAR 文件打开和正则解析操作。

**第 2 层 - Zip 归档缓存** ：存储生成的 ZIP 文件，哈希值存储在归档注释中。zip 端点将文件夹哈希与存储的归档哈希进行比较，以确定是否需要重新生成。

来源：[src/McModUtils/Mods.php](src/McModUtils/Mods.php#L140-L200), [src/McModUtils/Zip.php](src/McModUtils/Zip.php#L1-L117)

### 延迟求值模式

`Mod` 类为元数据字段实现了延迟加载。`getName()`、`getVersion()` 和 `getAuthors()` 方法检查值是否为空，并仅在需要时触发 `parse()`。同样，`getMd5()` 和 `getSha1()` 仅在首次访问时计算哈希。

来源：[src/McModUtils/Mod.php](src/McModUtils/Mod.php#L201-L249)

哈希计算 (MD5/SHA1) 会推迟到通过 getter 明确请求时才执行，从而允许进行轻量级的元数据列出操作，而无需不必要的文件哈希计算。

## 集成和扩展点

系统为自定义模组加载器或其他元数据源提供了几个扩展点。

### 自定义解析器集成

要添加对新模组加载器的支持，请扩展 `Mod` 类并覆盖 `parse()` 方法。解析器应返回一个数组，其键与 `applyParseResult` 的期望相匹配：`name`、`version` 和 `authors`。

PHP

```php protected function parseCustomLoader($raw): array { $result = []; // 自定义解析逻辑 $result['name'] = /* 提取逻辑 */; $result['version'] = /* 提取逻辑 */; $result['authors'] = /* 提取逻辑 */; return $result; } ``` 

来源：[src/McModUtils/Mod.php](src/McModUtils/Mod.php#L102-L119)

### 输出格式自定义

可以覆盖 `output()` 和 `outputBasic()` 方法以支持其他启动器格式或 API 约定。当前实现已包含多个平台的兼容性字段。

来源：[src/McModUtils/Mod.php](src/McModUtils/Mod.php#L280-L328)

## 后续步骤

了解模组解析系统为探索相关功能奠定了基础：

  * [服务器监控和状态查询 ](8-server-monitoring-and-status-queries.md) \- 了解系统如何监控 Minecraft 服务器状态以及模组管理
  * [Zip 归档生成和管理 ](10-zip-archive-generation-and-management.md) \- 深入探讨用于模组包分发的 zip 生成系统
  * [缓存机制和缓存失效 ](9-caching-mechanism-and-cache-invalidation.md) \- 探索整个系统中使用的详细缓存策略
  * [模组信息 API ](11-mod-information-apis.md) \- 所有模组相关端点的完整 API 文档


