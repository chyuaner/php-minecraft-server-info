# 项目架构和设计原则

本文档深入探讨了驱动 php-minecraft-server-info 系统的基础架构和核心设计原则。理解这些架构决策能为后续文档章节中涉及的特定模块、API 和实现细节提供必要的背景知识。

## 系统架构概述

该应用遵循基于 PSR-4 自动加载标准的**分层模块化架构** ，并以 Slim Framework 作为 HTTP 路由层。系统组织为三个不同的层：应用引导层、包含内容协商的请求处理层，以及用于 Minecraft 特定操作的领域逻辑层。

该架构优先考虑**关注点的清晰分离** ：App 层处理 HTTP 特定的问题，如内容协商和错误处理，而 McModUtils 命名空间封装了独立于 Web 框架的所有 Minecraft 领域逻辑。

## 引导与配置系统

应用初始化遵循两阶段配置加载模式，支持默认值和特定于环境的覆盖。`bootstrap.php` 文件作为所有应用初始化的单一入口点，加载 PSR-4 自动加载并按层级合并配置。  
来源：[bootstrap.php](bootstrap.php#L1-L15)

配置加载遵循以下顺序：

  1. **自动加载器注册** ：通过 Composer 注册 `McModUtils\` 和 `App\` 的 PSR-4 命名空间 来源：[composer.json](composer.json#L4-L8)
  2. **默认配置** ：从 `config.default.php` 加载包含合理默认值的基础配置 来源：[bootstrap.php](bootstrap.php#L11-L11)
  3. **本地覆盖** ：可选的 `config.php` 文件与默认值合并，用于特定环境的设置 来源：[bootstrap.php](bootstrap.php#L12-L13)
  4. **全局可访问性** ：最终合并的配置存储在 `$GLOBALS['config']` 中以保持传统兼容性 来源：[bootstrap.php](bootstrap.php#L13-L13)



这种分层配置方法允许开发环境覆盖路径、服务器地址和调试标志，而无需修改默认配置文件——这是在不同部署环境中维护配置的关键模式。

## HTTP 路由与中间件堆栈

Slim Framework 应用在 `public/index.php` 中配置了精心排序的中间件堆栈，遵循 Slim 关于中间件顺序的最佳实践。中间件管道在到达路由处理程序之前处理路由、错误处理和 URL 规范化。  
来源：[public/index.php](public/index.php#L26-L56)

**中间件顺序（关键）** ：

| 中间件    | 用途              | 位置 |
|--------|-----------------|----|
| 路由中间件  | 路由解析            | 第一 |
| 末尾斜杠处理 | URL 规范化（移除末尾斜杠） | 第二 |
| 错误中间件  | 异常处理和错误响应生成     | 最后 |



必须在错误中间件之前添加路由中间件，以确保正确捕获和处理路由异常。末尾斜杠中间件配置为移除末尾斜杠，为所有端点提供规范 URL。  
来源：[public/index.php](public/index.php#L32-L56)

路由定义在单独的文件中模块化，通过 `public/index.php` 中的 `require` 语句加载：

  * `mods.php`：Mod 列表、下载和 zip 生成路由
  * `server.php`：服务器 ping、查询和横幅生成路由
  * `other_files.php`：配置和资源文件服务路由 来源：[public/index.php](public/index.php#L60-L62)



这种分离允许独立管理不同的功能区域，同时保持单一的应用入口点。

## 内容协商与响应格式化

**ResponseFormatter** 类实现了一种复杂的内容协商策略，支持 JSON 和 HTML 两种输出格式。这种双重格式支持为浏览器访问提供对人类友好的 HTML 响应，同时为程序化消费提供结构化的 JSON。  
来源：[src/App/ResponseFormatter.php](src/App/ResponseFormatter.php#L21-L41)

**格式检测优先级（从高到低）** ：

  1. **查询参数覆盖** ：`?type=json` 或 `?type=html` 显式强制格式 来源：[src/App/ResponseFormatter.php](src/App/ResponseFormatter.php#L29-L36)
  2. **传统 JSON 参数** ：`?json=1` 用于向后兼容 来源：[src/App/ResponseFormatter.php](src/App/ResponseFormatter.php#L24-L27)
  3. **HTTP Accept 头** ：`Accept: application/json` 对比 `Accept: text/html` 来源：[src/App/ResponseFormatter.php](src/App/ResponseFormatter.php#L38-L41)
  4. **默认回退** ：针对未知请求使用 HTML 格式



格式化程序包含智能 HTML 呈现，具有自动 URL 链接、布尔值显示和递归数组/对象表示以便于调试。JSON 响应使用 `JSON_UNESCAPED_UNICODE` 编码以保留多语言字符。  
来源：[src/App/ResponseFormatter.php](src/App/ResponseFormatter.php#L46-L67)

## 错误处理策略

自定义错误处理扩展了 Slim 的 `ErrorHandler`，以提供格式感知的错误响应，该响应遵循与成功响应相同的内容协商逻辑。`AppErrorHandler` 使用与 `ResponseFormatter` 完全相同的逻辑确定错误格式，确保正常响应和错误响应之间的一致性。  
来源：[src/App/AppErrorHandler.php](src/App/AppErrorHandler.php#L12-L24)

**异常状态码映射** ：

| 异常类型                      | HTTP 状态         | 来源                                                                 |
|---------------------------|-----------------|--------------------------------------------------------------------|
| `MinecraftPingException`  | 502 Bad Gateway | [src/App/AppErrorHandler.php](src/App/AppErrorHandler.php#L29-L31) |
| `MinecraftQueryException` | 502 Bad Gateway | [src/App/AppErrorHandler.php](src/App/AppErrorHandler.php#L32-L34) |
| 其他异常                      | 继承自父类           | [src/App/AppErrorHandler.php](src/App/AppErrorHandler.php#L36-L38) |



当 Minecraft 服务器查询无法到达底层游戏服务器时，502 状态码适当地发出了网关故障信号，将其与由应用程序错误引起的 500 内部服务器错误区分开来。

## 领域模型设计

`McModUtils` 命名空间封装了所有 Minecraft 特定的业务逻辑，保持独立于 Web 框架。这种清晰的分离促进了测试、重用以及将来迁移到不同的 HTTP 框架。

### Mods 管理系统

**Mods** 类管理 mod 文件夹扫描，并根据文件名前缀进行智能过滤。它实现了**基于内容哈希的缓存策略** ，以避免冗余的文件系统操作。  
来源：[src/McModUtils/Mods.php](src/McModUtils/Mods.php#L64-L129)

**关键设计模式** ：

  * **前缀过滤** ：支持忽略列表（`hide_`、`serveronly_`）和服务端 mod 的独占过滤器 来源：[src/McModUtils/Mods.php](src/McModUtils/Mods.php#L36-L53)
  * **内容哈希** ：根据相对路径、修改时间和文件大小计算 SHA-256 哈希以进行缓存失效 来源：[src/McModUtils/Mods.php](src/McModUtils/Mods.php#L119-L128)
  * **递归扫描** ：使用 PHP SPL 迭代器进行高效的目录遍历 来源：[src/McModUtils/Mods.php](src/McModUtils/Mods.php#L71-L81)



### Mod 元数据解析

**Mod** 类实现了**策略模式** ，用于从不同的 mod 加载器格式解析元数据。它按照定义的回退顺序尝试多种解析策略。  
来源：[src/McModUtils/Mod.php](src/McModUtils/Mod.php#L38-L76)

**解析策略顺序** ：

  1. **NeoForge** ：使用正则表达式模式解析 `META-INF/neoforge.mods.toml` 来源：[src/McModUtils/Mod.php](src/McModUtils/Mod.php#L44-L49)
  2. **Forge (Legacy)** ：使用相同的 NeoForge 解析器解析 `META-INF/mods.toml` 来源：[src/McModUtils/Mod.php](src/McModUtils/Mod.php#L52-L57)
  3. **Fabric** ：使用 JSON 解码解析 `fabric.mod.json` 来源：[src/McModUtils/Mod.php](src/McModUtils/Mod.php#L60-L64)
  4. **文件名回退** ：使用正则表达式模式从文件名中提取名称和版本 来源：[src/McModUtils/Mod.php](src/McModUtils/Mod.php#L69-L73)



这种级联方法确保了跨不同 mod 加载器的最大兼容性，并在嵌入式元数据不可用时优雅地回退到文件名解析。

### 服务器状态查询

**Server** 类封装了 xPaw Minecraft Query 库，并具有特定于应用程序的配置管理。它通过灵活的构造函数初始化支持默认服务器配置和多服务器设置。  
来源：[src/McModUtils/Server.php](src/McModUtils/Server.php#L49-L68)

Server 构造函数实现了一个复杂的回退链：它首先检查配置数组中的显式服务器 ID，然后接受显式的主机/端口参数，接着将主机参数解释为服务器 ID，最后回退到全局配置中的默认服务器配置。 

服务器配置支持两种不同的模式：

| 模式    | 配置来源                               | 用例                       |
|-------|------------------------------------|--------------------------|
| 默认服务器 | `minecraft_host`, `minecraft_port` | 单服务器部署                   |
| 多服务器  | `minecraft_servers[id][...]` 数组    | 具有多个后端服务器的代理/velocity 设置 |



这种灵活性使应用程序既能服务于简单的单服务器安装，也能服务于复杂的多服务器代理环境。

## 缓存架构

系统实现了针对 Minecraft 服务器信息 API 典型的读密集型工作负载优化的多级缓存策略。缓存失效由内容哈希而非基于时间的到期驱动，确保当底层文件更改时立即保持一致。

**缓存存储位置** ：

| 缓存类型    | 存储路径                                 | 失效策略             |
|---------|--------------------------------------|------------------|
| Mod 元数据 | `public/static/mods-{metaHash}.json` | 文件夹内容 SHA-256 哈希 |
| Zip 归档  | `public/static/mods-{type}.zip`      | 存储在 ZIP 注释中的哈希   |



缓存系统包括一个根据配置状态（路径、忽略前缀、独占前缀）计算的**元数据哈希** ，这使得当过滤器设置更改时即使文件夹内容保持相同也能使缓存失效。  
来源：[src/McModUtils/Mods.php](src/McModUtils/Mods.php#L131-L138)

可以使用 `?force=true` 查询参数按需绕过缓存，这在开发期间或需要立即更新时特别有用。

## 路由组织与分组

路由使用 Slim 的路由组进行组织，以便在相关端点之间共享通用逻辑。与 mod 相关的路由通过映射到配置键的 URL 路径展示了此模式。  
来源：[public/mods.php](public/mods.php#L41-L64)

**路由组映射** ：

| URL 前缀         | 配置键      | 用途                 |
|----------------|----------|--------------------|
| `/mods`        | `common` | 客户端和服务器之间共享的通用 mod |
| `/client-mods` | `client` | 仅客户端 mod           |
| `/server-mods` | `server` | 仅服务端 mod           |



每个组共享相同的端点结构：

  * `GET /{group}`：列出带有元数据的 mod
  * `GET /{group}/:file`：单个 mod 文件信息
  * `GET /{group}/:file/download`：下载 mod 文件
  * `GET /{group}/zip`：将所有 mod 下载为 ZIP 归档



这种一致的结构使客户端代码能够使用相同的 API 模式处理不同的 mod 类型，只需更改基本 URL 前缀。

## 设计原则总结

该架构体现了指导整个代码库实现决策的几个核心设计原则：

  1. **关注点分离** ：HTTP 处理、内容协商和领域逻辑在 App 和 McModUtils 命名空间之间清晰分离
  2. **配置分层** ：具有特定于环境覆盖的默认配置支持多种部署方案
  3. **内容协商** ：基于查询参数、HTTP 头和智能默认值自动选择 JSON/HTML
  4. **基于内容哈希的缓存** ：基于实际内容更改而非任意时间到期的缓存失效
  5. **防御性解析** ：元数据解析的多种回退策略确保优雅降级
  6. **PSR 标准** ：遵守 PSR-4 自动加载可以轻松集成 PHP 生态系统工具
  7. **模块化路由** ：路由组和单独的路由文件实现有组织的端点管理
  8. **格式一致性** ：错误响应使用与成功响应相同的格式检测逻辑



这些架构决策为 Minecraft 服务器信息 API 创建了一个可维护、可扩展的基础。此处演示的模式在后续文档部分详述的特定模块实现中反复出现。

## 后续步骤

有了这个架构基础，你现在可以探索具体的实现细节：

  * 有关跨不同加载器的详细 mod 元数据解析策略，请参阅 [Mod 解析系统 (NeoForge, Forge, Fabric) ](7-mod-parsing-system-neoforge-forge-fabric.md)
  * 有关服务器查询机制和横幅生成，请参阅 [服务器监控和状态查询 ](8-server-monitoring-and-status-queries.md)
  * 有关缓存实现细节和失效策略，请参阅 [缓存机制和缓存失效 ](9-caching-mechanism-and-cache-invalidation.md)
  * 有关 API 端点规范和使用示例，请参阅从 [Mod 信息 API ](11-mod-information-apis.md) 开始的 API 部分


