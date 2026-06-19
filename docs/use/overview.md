**PHP Minecraft Server Info** 是一个专为管理、监控和分发 Minecraft 服务器资源而设计的综合后端解决方案。该项目提供了一个强大的 API 层，用于解析 Minecraft 模组、监控服务器状态、通过智能缓存提供文件服务以及生成可下载包——所有这些都基于 PHP 8.2+ 和现代架构原则构建。

## 本项目解决的问题[](https://zread.ai/chyuaner/php-minecraft-server-info#%E6%9C%AC%E9%A1%B9%E7%9B%AE%E8%A7%A3%E5%86%B3%E7%9A%84%E9%97%AE%E9%A2%98)

管理 Minecraft 服务器生态系统涉及复杂的挑战：在服务器和客户端之间同步模组、监控多个服务器实例、高效处理文件分发以及保持与不同模组加载器（NeoForge、Forge、Fabric）的兼容性。该系统通过提供以下功能解决了这些挑战：

-   **自动化模组解析**：扫描指定的模组文件夹以查找 `.jar` 文件，并提取包括名称、版本、作者和校验和在内的元数据，而无需完全解压 ZIP [README.md](https://zread.ai/chyuaner/README.md#L3-L5)
-   **多服务器监控**：同时查询多个 Minecraft 服务器实例的状态，提供实时玩家数量和版本信息 [config.default.php](https://zread.ai/chyuaner/config.default.php#L49-L63)
-   **智能缓存策略**：实现基于文件的 JSON 缓存，以显著减少处理时间——基准测试显示，与实时 ZIP 解析相比，性能有显著提升 [README.md](https://zread.ai/chyuaner/README.md#L6-L13)
-   **灵活的文件分发**：支持 PHP 介导的下载和直接 Nginx 静态文件服务，并具有自动回退机制 [README.md](https://zread.ai/chyuaner/README.md#L108-L117)
-   **模组加载器兼容性**：从 NeoForge (`META-INF/neoforge.mods.toml`)、Forge 和 Fabric 模组格式解析元数据 [Mod.php](https://zread.ai/chyuaner/src/McModUtils/Mod.php#L33-L43)

该系统专为高性能生产环境而设计，其架构中内置了 Nginx 配置指南和基于 Webhook 的部署自动化 [README.md](https://zread.ai/chyuaner/README.md#L71-L98)。

## 系统架构[](https://zread.ai/chyuaner/php-minecraft-server-info#%E7%B3%BB%E7%BB%9F%E6%9E%B6%E6%9E%84)

应用程序遵循关注点清晰分离的原则，主要包含三个架构层：

Data Layer

Business Logic Layer

Application Layer

Client Layer

Web Browser

API Client

Sync Scripts

Slim Router

Error Handler

Response Formatter

Server Monitor

Mods Manager

Mod Parser

Zip Generator

Minecraft Files

JSON Cache

Configuration

**请求流程**：

1.  **入口点**：所有请求通过 [`public/index.php`](https://zread.ai/chyuaner/public/index.php#L1-L50) 进入，该文件使用 [`bootstrap.php`](https://zread.ai/chyuaner/bootstrap.php#L1-L15) 启动 Slim 框架
2.  **配置加载**：来自 [`config.default.php`](https://zread.ai/chyuaner/config.default.php#L1-L67) 的默认配置与本地覆盖合并，创建一个存储在 `$GLOBALS['config']` 中的统一配置数组
3.  **路由分发**：路由被组织到模块化文件中——[`mods.php`](https://zread.ai/chyuaner/public/mods.php#L1-L80) 用于模组操作，[`server.php`](https://zread.ai/chyuaner/public/server.php#L1-L80) 用于服务器查询，[`other_files.php`](https://zread.ai/chyuaner/public/other_files.php) 用于常规文件服务
4.  **业务逻辑执行**：[`src/McModUtils/`](https://zread.ai/chyuaner/src/McModUtils/) 中的域模型处理核心操作
5.  **响应格式化**：[`ResponseFormatter`](https://zread.ai/chyuaner/public/index.php#L1-L50) 根据 Accept 标头或查询参数应用内容协商
6.  **错误处理**：[`AppErrorHandler`](https://zread.ai/chyuaner/src/App/AppErrorHandler.php) 为所有端点提供一致的错误响应

## 核心组件[](https://zread.ai/chyuaner/php-minecraft-server-info#%E6%A0%B8%E5%BF%83%E7%BB%84%E4%BB%B6)

### 模组解析引擎[](https://zread.ai/chyuaner/php-minecraft-server-info#%E6%A8%A1%E7%BB%84%E8%A7%A3%E6%9E%90%E5%BC%95%E6%93%8E)

模组解析系统围绕 [`Mod`](https://zread.ai/chyuaner/src/McModUtils/Mod.php#L1-L50) 类展开，该类无需完全解压即可智能地从 JAR 存档中提取元数据：

| 功能  | 实现  | 优势  |
| --- | --- | --- |
| **多加载器支持** | 解析 `META-INF/neoforge.mods.toml`、Forge `mcmod.info`、Fabric `fabric.mod.json` | 适用于所有主要模组加载器的统一 API |
| **哈希生成** | 计算 MD5 和 SHA1 校验和以进行完整性验证 | 安全的文件同步 |
| **缓存策略** | 将解析结果存储在 `public/static/` 目录中 | 后续请求避免 ZIP 解析开销 |
| **前缀过滤** | 支持 `serveronly_`、`server_` 前缀用于服务器/客户端分离 | 灵活的模组组织 |

[`Mods`](https://zread.ai/chyuaner/src/McModUtils/Mods.php#L1-L50) 类管理模组集合，具有复杂的过滤功能，允许你根据文件命名约定查询公共模组、仅客户端模组或仅服务器模组 [config.default.php](https://zread.ai/chyuaner/config.default.php#L9-L39)。

### 服务器监控系统[](https://zread.ai/chyuaner/php-minecraft-server-info#%E6%9C%8D%E5%8A%A1%E5%99%A8%E7%9B%91%E6%8E%A7%E7%B3%BB%E7%BB%9F)

服务器状态查询利用集成了两个外部库的 [`Server`](https://zread.ai/chyuaner/src/McModUtils/Server.php#L1-L50) 类：

-   **xPaw/MinecraftPing**：用于标准服务器 ping 操作（玩家数量、MOTD、版本信息）[composer.json](https://zread.ai/chyuaner/composer.json#L1-L25)
-   **Query 协议**：当配置了 Query 端口时，用于获取详细的玩家列表信息

该系统支持通过 `minecraft_servers` 配置数组监控多个服务器，每个服务器都有唯一标识符、主机地址和端口 [config.default.php](https://zread.ai/chyuaner/config.default.php#L49-L63)。

### 文件服务架构[](https://zread.ai/chyuaner/php-minecraft-server-info#%E6%96%87%E4%BB%B6%E6%9C%8D%E5%8A%A1%E6%9E%B6%E6%9E%84)

该项目实现了一种平衡性能和可靠性的混合文件服务策略：

![Architecture Diagram](https://github.com/chyuaner/php-minecraft-server-info/blob/master/docs/nginx-flow.svg?raw=true)

**双层服务模型**：

| 方法  | 性能  | 可靠性 | 用例  |
| --- | --- | --- | --- |
| **Nginx 直连** | 平均约 190ms | 需要配置 | 生产环境主要方法 |
| **PHP 回退** | 平均约 480ms | 始终可用 | 开发和备份 |

位于 `/mods/:filename/download` 的 PHP 回退路由确保即使 Nginx 静态配置失败，文件也始终可访问，使用带有正确标头的 `readfile()` 进行可靠交付 [README.md](https://zread.ai/chyuaner/README.md#L118-L122)。

性能差异源于 Nginx 的零拷贝 `sendfile()` 系统调用与 PHP 的内存映射文件读取——这就是为什么生产部署应优先使用 Nginx 静态服务并将 PHP 作为回退的原因。

## 项目结构[](https://zread.ai/chyuaner/php-minecraft-server-info#%E9%A1%B9%E7%9B%AE%E7%BB%93%E6%9E%84)

![Directory Structure](https://github.com/chyuaner/php-minecraft-server-info/blob/master/.github/project-structure.svg?raw=true)

该仓库遵循 PSR-4 自动加载约定，具有清晰的关注点分离：

Copy code

```
php-minecraft-server-info/
├── 📄 bootstrap.php              ├── 📄 config.default.php         # 包含所有选项的配置模板
├── 📄 composer.json              # 依赖项和 PSR-4 自动加载规则
├── 📄 package.json               # 前端工具和文档脚本
│
├── 📁 public/                    # Web 根目录
│   ├── 📄 index.php             # Slim 应用程序工厂、中间件设置
│   ├── 📄 mods.php              # 模组列表、下载和 zip 生成路由
│   ├── 📄 server.php            # 服务器 ping、查询和横幅生成
│   ├── 📄 other_files.php       # 配置、资源包和其他文件服务
│   └── 📁 static/                # 生成的缓存文件（JSON 响应、ZIP 存档）
│
└── 📁 src/                       # 应用程序源代码
    ├── 📁 App/                   # 应用程序级服务
    │   ├── AppErrorHandler.php   # 全局异常处理
    │   └── ResponseFormatter.php # 内容协商 (JSON/HTML)
    │
    ├── 📁 McModUtils/            # Minecraft 操作的域模型
    │   ├── Server.php           # 服务器状态监控
    │   ├── Mods.php             # 模组集合管理
    │   ├── Mod.php              # 单个模组解析
    │   ├── Folder.php           # 文件系统操作
    │   └── Zip.php              # 存档生成
    │
    └── 📁 templates/             # PHP 视图模板
        └── index.php            # HTML 响应模板
```

## 主要功能概述[](https://zread.ai/chyuaner/php-minecraft-server-info#%E4%B8%BB%E8%A6%81%E5%8A%9F%E8%83%BD%E6%A6%82%E8%BF%B0)

### 模组管理 API[](https://zread.ai/chyuaner/php-minecraft-server-info#%E6%A8%A1%E7%BB%84%E7%AE%A1%E7%90%86-api)

| 端点  | 描述  | 输出格式 |
| --- | --- | --- |
| `GET /mods` | 列出所有公共模组 | JSON, HTML |
| `GET /mods/:filename` | 获取特定模组元数据 | JSON |
| `GET /mods/zip` | 下载所有模组为 ZIP 存档 | ZIP 文件 |
| `GET /mods/:filename/download` | 下载单个模组文件 | JAR 文件 |

类似的端点也存在于 `client-mods` 和 `server-mods` 变体中，根据配置自动应用前缀过滤 [public/mods.php](https://zread.ai/chyuaner/public/mods.php#L1-L80)。

### 服务器状态 API[](https://zread.ai/chyuaner/php-minecraft-server-info#%E6%9C%8D%E5%8A%A1%E5%99%A8%E7%8A%B6%E6%80%81-api)

| 端点  | 描述  | 提供的详细信息 |
| --- | --- | --- |
| `GET /ping[/:serverId]` | 服务器状态查询 | 玩家数量、MOTD、版本、示例玩家 |
| `GET /query/` | Query 协议详细信息 | 完整玩家列表、扩展服务器信息 |
| `GET /online-players[/:serverId]` | 当前在线玩家 | 玩家名称、UUID、会话信息 |
| `GET /banner[/:serverId]` | 服务器横幅图像 | 用于嵌入的生成 PNG 横幅 |

Query 协议提供比标准 ping 更丰富的信息，但要求在 `server.properties` 中设置 `enable-query=true` 并正确转发 query 端口。

### 文件服务 API[](https://zread.ai/chyuaner/php-minecraft-server-info#%E6%96%87%E4%BB%B6%E6%9C%8D%E5%8A%A1-api)

| 端点  | 描述  | 性能  |
| --- | --- | --- |
| `GET /files/mods/*` | 通过 Nginx 直接文件服务 | 最佳 (~190ms) |
| `GET /files/config/*` | 服务器配置文件 | 最佳  |
| `GET /files/resourcepacks/*` | 客户端资源包 | 最佳  |
| 回退路由 | PHP 介导的下载 | 可靠 (~480ms) |

## 技术栈[](https://zread.ai/chyuaner/php-minecraft-server-info#%E6%8A%80%E6%9C%AF%E6%A0%88)

| 组件  | 技术  | 用途  |
| --- | --- | --- |
| **运行时** | PHP 8.2+ | 现代 PHP，具有改进的性能和类型系统 |
| **框架** | Slim 4.x | 轻量级 PSR-7 微框架，用于路由和中间件 |
| **自动加载** | Composer PSR-4 | 标准 PHP 包管理和自动加载 |
| **Minecraft Query** | xPaw/MinecraftPing | 服务器状态和玩家查询 |
| **横幅生成** | games647/minecraft-banner-generator | 可视化服务器状态横幅 |
| **尾部斜杠** | middlewares/trailing-slash | URL 标准化中间件 |
| **PHP 扩展** | php-zip, php-gd | 存档处理和图像处理 |

该项目在包括 Manjaro、Debian 12 (bookworm) 和 Debian 13 (trixie) 在内的 Linux 发行版上进行了积极测试，使用的 PHP 版本为 8.2.28 和 8.4.x [README.md](https://zread.ai/chyuaner/README.md#L15-L21)。

## 典型用例[](https://zread.ai/chyuaner/php-minecraft-server-info#%E5%85%B8%E5%9E%8B%E7%94%A8%E4%BE%8B)

**模组同步脚本**：使用带有 JSON 输出的 `/mods` 端点为 Prism Launcher 或其他模组管理器生成 `mod_list.json`。输出格式与 Prism Launcher 清单规范匹配 [README.md](https://zread.ai/chyuaner/README.md#L186-L206)。

**Web 仪表板集成**：服务器状态 API 为构建管理面板或公共服务器页面提供实时玩家数量和 MOTD。`/online-players` 端点启用实时玩家列表显示 [server.php](https://zread.ai/chyuaner/public/server.php#L1-L80)。

**自动化模组分发**：`/zip` 端点生成完整的模组包，用于快速客户端-服务器同步。结合基于 Webhook 的部署，这实现了完全自动化的模组分发管道 [README.md](https://zread.ai/chyuaner/README.md#L71-L98)。

**文件托管**：通过具有智能缓存和 CDN 友好 URL 结构的统一 API，提供配置文件、资源包和其他服务器资产。

## 入门指南[](https://zread.ai/chyuaner/php-minecraft-server-info#%E5%85%A5%E9%97%A8%E6%8C%87%E5%8D%97)

对于你与系统的第一次交互，通常需要：

1.  了解安装过程 → [快速入门](https://zread.ai/chyuaner/php-minecraft-server-info/2-quick-start)
2.  验证系统要求 → [系统要求和依赖项](https://zread.ai/chyuaner/php-minecraft-server-info/3-system-requirements-and-dependencies)
3.  部署你的第一个实例 → [安装和设置](https://zread.ai/chyuaner/php-minecraft-server-info/4-installation-and-setup)
4.  配置生产环境 → [使用 Nginx 进行生产部署](https://zread.ai/chyuaner/php-minecraft-server-info/5-production-deployment-with-nginx)

对于有兴趣扩展系统或了解其内部工作的开发者：

-   探索模块化架构 → [项目架构和设计原则](https://zread.ai/chyuaner/php-minecraft-server-info/6-project-architecture-and-design-principles)
-   深入研究模组解析逻辑 → [模组解析系统](https://zread.ai/chyuaner/php-minecraft-server-info/7-mod-parsing-system-neoforge-forge-fabric)
-   了解服务器监控 → [服务器监控和状态查询](https://zread.ai/chyuaner/php-minecraft-server-info/8-server-monitoring-and-status-queries)

本文档假设读者熟悉 PHP 开发、基本服务器管理和 Minecraft 服务器操作概念。该系统的设计旨在让初学者易于上手，同时为复杂的生产部署提供所需的深度和灵活性。
