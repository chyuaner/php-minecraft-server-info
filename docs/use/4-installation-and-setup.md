# 安装和设置

本指南将引导你完成安装和配置 PHP Minecraft Server Info API 系统。你将学习如何准备环境、安装依赖、为你的 Minecraft 服务器基础设施配置应用程序，以及验证设置。按照本指南操作，你应该能拥有一个功能齐全的 API 服务器，能够提供 Mod 信息、服务器状态查询和文件下载服务。

## 先决条件和系统要求

在开始安装之前，请确保你的系统满足以下要求。该项目已在 Debian 12、Debian 13 和 Manjaro Linux 发行版上使用 PHP 8.2 或 PHP 8.4 进行了测试。

![PHP Versions](https://github.com/chyuaner/php-minecraft-server-info/blob/master/.github/workflows/badges/badge.svg?raw=true)

### 操作系统支持

| 发行版                  | 测试的 PHP 版本 | 状态    |
|----------------------|------------|-------|
| Manjaro Linux        | 8.4.7      | ✅ 已验证 |
| Debian 12 (bookworm) | 8.2.28     | ✅ 已验证 |
| Debian 13 (trixie)   | 8.4.11     | ✅ 已验证 |



### 必需的 PHP 扩展

应用程序正常运行必须启用两个关键的 PHP 扩展。系统需要 `php-zip` 来解析 Minecraft Mod JAR 文件（即 ZIP 压缩包），以及 `php-gd` 来生成服务器横幅和处理图像。

来源: [README.md](README.md#L28-L29)

### PHP 配置要求

应用程序处理可能较大的 Mod 压缩包，需要调整 PHP 设置以获得最佳性能。你需要修改通常位于 `/etc/php/php.ini` 或 `/etc/php/8.4/fpm/php.ini` 的 PHP 配置文件。

| 配置                   | 推荐值   | 用途                   |
|----------------------|-------|----------------------|
| `extension=gd`       | 取消注释  | 启用 GD 图像库以生成横幅       |
| `extension=zip`      | 取消注释  | 启用 ZIP 压缩包处理以解析 Mod  |
| `max_execution_time` | 90    | 允许足够的时间来处理大量的 Mod 集合 |
| `memory_limit`       | 2048M | 为 ZIP 操作提供充足的内存      |



修改这些设置后，重新加载你的 PHP-FPM 服务以应用更改：

BASH

``` sudo systemctl reload php8.4-fpm.service ``` 

来源: [README.md](README.md#L30-L39), [composer.json](composer.json#L13-L18)

## 安装过程

安装过程遵循基于 Composer 的标准工作流。应用程序使用 PSR-4 自动加载机制，在 `McModUtils` 和 `App` 命名空间下组织其代码结构。

### 步骤 1：克隆仓库

首先将仓库克隆到你想要的位置：

BASH

```bash git clone <repository-url> cd php-minecraft-server-info ``` 

来源: [README.md](README.md#L43-L47)

### 步骤 2：安装依赖

使用 Composer 安装所有必需的 PHP 包。该项目依赖于几个关键库，包括用于路由的 Slim 框架、用于服务器通信的 xpaw's PHP Minecraft Query 库，以及用于视觉元素的 Minecraft Banner Generator。

BASH

```bash composer install composer dump-autoload ``` 

来源: [composer.json](composer.json#L1-L25), [README.md](README.md#L44-L48)

### 步骤 3：配置设置

通过复制默认模板来创建你的本地配置文件：

BASH

``` cp config.default.php config.php vim config.php ``` 

引导系统会将你的本地配置与默认配置合并，允许你覆盖特定设置而无需修改基础模板。此配置加载发生在应用程序初始化期间，配置数组存储在全局作用域中以便应用程序全局访问。

来源: [README.md](README.md#L47-L49), [bootstrap.php](bootstrap.php#L1-L15)

## 应用程序架构概述

该应用程序遵循模块化架构，具有清晰的关注点分离。理解此结构将有助于你进行配置和故障排除。

### 组件职责

| 组件      | 位置                              | 职责                      |
|---------|---------------------------------|-------------------------|
| 入口点     | `public/index.php`              | 应用程序引导、路由设置、中间件配置       |
| 引导程序    | `bootstrap.php`                 | 自动加载、配置合并、路径常量          |
| Mod 解析器 | `src/McModUtils/Mod.php`        | 解析单个 Mod JAR 文件并提取元数据   |
| 服务器监控器  | `src/McModUtils/Server.php`     | 查询 Minecraft 服务器状态和玩家信息 |
| Mod 管理器 | `src/McModUtils/Mods.php`       | 管理 Mod 集合，支持过滤和分类       |
| 文件夹处理器  | `src/McModUtils/Folder.php`     | 扫描目录并管理文件系统操作           |
| ZIP 处理器 | `src/McModUtils/Zip.php`        | 处理 JAR 文件的 ZIP 压缩包操作    |
| 响应格式化器  | `src/App/ResponseFormatter.php` | 标准化不同内容类型的 API 响应       |
| 错误处理器   | `src/App/AppErrorHandler.php`   | 集中式错误处理和调试输出            |



来源: [composer.json](composer.json#L4-L7), [public/index.php](public/index.php#L1-L78)

## 配置指南

配置系统设计灵活，支持单服务器和多服务器部署。你的配置文件将与默认配置合并，允许你仅指定需要覆盖的设置。

### 核心应用程序设置

Base URL 设置定义了 API 实例的根 URL。这对于生成正确的下载链接和引用尤为重要：

PHP

``` 'base_url' => 'http://localhost:8000', ``` 

Debug 标志控制是否显示详细的错误信息。在生产环境中，应始终将其设置为 `false`：

PHP

``` 'debug' => false, ``` 

来源: [config.default.php](config.default.php#L9), [config.default.php](config.default.php#L67)

### Minecraft 路径配置

应用程序需要 Minecraft 服务器安装的基础路径。所有其他路径都由此基础路径衍生而来：

| 配置                   | 描述                 | 示例                              |
|----------------------|--------------------|---------------------------------|
| `mods_path`          | 主要 Mod 目录路径        | `/opt/minecraft/mc-server/mods` |
| `serverside_prefixs` | 用于标识仅服务器 Mod 的前缀数组 | `['serveronly_', 'server_']`    |



这些前缀允许系统自动区分客户端、服务端和通用 Mod。

来源: [config.default.php](config.default.php#L4-L8), [config.default.php](config.default.php#L10-L11)

### Mod 类别配置

系统支持三种类型的 Mod 类别，每种类别在过滤和服务方面具有不同的行为：

| 类别           | 路径                                    | 下载 URL 路径            | 忽略服务器前缀 | 仅限服务器前缀 |
|--------------|---------------------------------------|----------------------|---------|---------|
| 通用 (Common)  | `/opt/minecraft/mc-server/mods`       | `/files/mods/`       | 是       | 否       |
| 客户端 (Client) | `/opt/minecraft/mc-server/clientmods` | `/files/clientmods/` | 是       | 否       |
| 服务器 (Server) | `/opt/minecraft/mc-server/mods`       | `/files/mods/`       | 否       | 是       |



`common` 类别包括除带有仅服务器前缀之外的所有 Mod。`client` 类别用于特定于客户端的 Mod，而 `server` 类别专门提供带有仅服务器前缀的 Mod。

来源: [config.default.php](config.default.php#L13-L25)

### 其他文件夹配置

除了 Mod 之外，你还可以配置其他文件夹作为静态文件提供服务。这对于配置文件、资源包和自定义脚本非常有用：

| 文件夹路径                                      | URL 路径                    | 用途                            |
|--------------------------------------------|---------------------------|-------------------------------|
| `/opt/minecraft/mc-server/config`          | `/files/config/`          | 服务器配置                         |
| `/opt/minecraft/mc-server/defaultconfigs`  | `/files/defaultconfigs/`  | 默认 Mod 配置                     |
| `/opt/minecraft/mc-server/kubejs`          | `/files/kubejs/`          | KubeJS 脚本                     |
| `/opt/minecraft/mc-server/modernfix`       | `/files/modernfix/`       | ModernFix 配置                  |
| `/opt/minecraft/mc-server/resourcepacks`   | `/files/resourcepacks/`   | 资源包                           |
| `/opt/minecraft/mc-server/tacz`            | `/files/tacz/`            | Timeless and Classics Zero 配置 |
| `/opt/minecraft/mc-server/tlm_custom_pack` | `/files/tlm_custom_pack/` | 自定义整合包文件                      |



来源: [config.default.php](config.default.php#L27-L36)

### 单服务器配置

对于单服务器部署，请配置主服务器入口点。如果你使用 Velocity 作为代理，请在此处指定代理的主机：

PHP

``` 'minecraft_public_hoststring' => 'mcserver.barian.moe', 'minecraft_host' => '127.0.0.1', 'minecraft_port' => 25565, 'minecraft_qport' => null, // 如果启用，设置为查询端口 ``` 

仅当你在 `server.properties` 文件中配置了 `enable-query=true` 和 `query.port` 时，才应设置 `minecraft_qport`。

来源: [config.default.php](config.default.php#L38-L42)

### 多服务器配置

系统支持复杂设置的多服务器配置。每个服务器都需要唯一的标识符和连接详细信息：

PHP

``` 'minecraft_servers' => [ 'youer1' => [ 'name' => 'youer1', 'public_hoststring' => 'mcserver.barian.moe /server youer1', 'host' => '127.0.0.1', 'port' => 24565, 'qport' => 24565, ], 'youer2' => [ 'name' => 'youer2', 'public_hoststring' => 'mcserver.barian.moe /server youer2', 'host' => '127.0.0.1', 'port' => 23565, 'qport' => null, ], ], ``` 

| 参数                  | 用途            | 是否必需 |
|---------------------|---------------|------|
| `name`              | 前端 UI 显示的名称   | 是    |
| `public_hoststring` | 向用户显示的连接字符串   | 是    |
| `host`              | 服务器 IP 地址或主机名 | 是    |
| `port`              | 服务器 TCP 端口    | 是    |
| `qport`             | 查询端口（可选）      | 否    |



来源: [config.default.php](config.default.php#L44-L62)

## 开发服务器设置

出于开发和测试目的，你可以启动内置的 PHP 开发服务器。不建议在生产环境中使用此方式。

BASH

```bash php -S 127.0.0.1:8000 -t public ``` 

应用程序将在 `http://localhost:8000` 上可访问。入口点自动路由请求，加载相应的路由文件，并配置用于错误处理和尾部斜杠规范化的中间件。

开发服务器根据你的配置自动包含基于调试模式的错误中间件。当 `'debug' => true` 时，系统使用自定义的 AppErrorHandler，提供详细的错误信息。在生产环境中，请确保禁用调试模式以防止信息泄露。 

来源: [README.md](README.md#L51-L54), [public/index.php](public/index.php#L34-L47)

## 验证

服务器运行后，你可以通过访问根端点来验证安装：

BASH

```bash curl http://localhost:8000/ ``` 

这应该返回应用程序的索引页面。对于 API 测试，你可以根据配置访问不同的端点：

  * `/mods` \- 列出所有 Mod
  * `/server` \- 返回服务器状态
  * `/files/mods/` \- 直接文件访问（如果已配置）



来源: [public/index.php](public/index.php#L59-L78)

## 后续步骤

完成安装和基本配置后，你可能想要探索以下内容：

  * [使用 Nginx 进行生产部署 ](5-production-deployment-with-nginx.md) \- 用于带有 Nginx 配置和 SSL 设置的生产级部署
  * [项目架构和设计原则 ](6-project-architecture-and-design-principles.md) \- 了解系统的架构模式和设计决策
  * [多服务器配置 ](15-multi-server-configuration.md) \- 高级多服务器设置场景
  * [错误处理和异常管理 ](16-error-handling-and-exception-management.md) \- 了解应用程序如何处理错误和异常



如果在设置过程中遇到问题，调试模式会提供详细的错误信息以帮助解决问题。如果遇到 ZIP 相关错误或超时问题，请检查系统要求和 PHP 配置部分。
