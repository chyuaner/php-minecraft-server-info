# 系统要求和依赖项

本文档概述了部署 PHP Minecraft Server Info 应用所需的关键系统要求、依赖项和配置先决条件。理解这些要求对于确保 mod 解析、服务器监控和文件服务功能的成功安装及最佳性能至关重要。

## PHP 运行时要求

应用需要 PHP 8.2 或更高版本的运行环境。该项目已在多个 Linux 发行版和 PHP 版本上进行了测试和验证，确保了部署场景的广泛兼容性。

### 测试平台配置

| 操作系统                                  | PHP 版本       | 状态    |
|---------------------------------------|--------------|-------|
| Linux 6.14.0-2-rt3-MANJARO            | 8.4.7 (NTS)  | ✓ 已测试 |
| Debian GNU/Linux 12 (bookworm) x86_64 | 8.2.28 (NTS) | ✓ 已测试 |
| Debian GNU/Linux 13 (trixie) x86_64   | 8.4.11 (NTS) | ✓ 已测试 |



来源: [README.md](README.md#L11-L19)

### 必需的 PHP 扩展

核心功能需要两个关键的 PHP 扩展：

  1. **php-zip** \- 用于解析 Minecraft mod JAR 归档文件、提取 mod 元数据以及生成可下载的 ZIP 归档文件以进行 mod 同步所必需
  2. **php-gd** \- Minecraft 横幅生成器组件需要它来创建服务器状态横幅图像



在运行应用程序之前，必须在 PHP 配置中启用这些扩展。

来源: [README.md](README.md#L20-L23)

### PHP 配置参数

应用程序需要特定的 PHP 配置调整，以处理 Minecraft 服务器环境中常见的大文件操作和延长的处理时间：

| 配置参数                 | 推荐值   | 用途                            |
|----------------------|-------|-------------------------------|
| `max_execution_time` | 90    | 允许足够的时间来解析大型 mod 目录和生成 ZIP 归档 |
| `memory_limit`       | 2048M | 提供足够的内存用于处理多个 mod 文件和横幅生成     |



**配置文件位置：**

  * Manjaro: `/etc/php/php.ini`
  * Debian/Ubuntu: `/etc/php/8.4/fpm/php.ini` (或特定版本路径)



修改配置文件后，重新加载 PHP-FPM 服务以应用更改：

BASH

``` sudo systemctl reload php8.4-fpm.service ``` 

来源: [README.md](README.md#L24-L36)

## PHP 依赖项

该应用程序利用 Composer 进行依赖管理，拥有按功能组织的六个核心包：

### 核心依赖架构

### 依赖项细分

| 包                                     | 版本     | 类别           | 功能                      |
|---------------------------------------|--------|--------------|-------------------------|
| `slim/slim`                           | 4.x    | Web 框架       | HTTP 路由、中间件管道、应用程序结构    |
| `slim/psr7`                           | ^1.7   | PSR-7        | 请求/响应接口实现               |
| `slim/php-view`                       | ^3.4   | 模板引擎         | 基于 PHP 的模板渲染            |
| `middlewares/trailing-slash`          | ^2.1   | 中间件          | URL 尾部斜杠标准化             |
| `xpaw/php-minecraft-query`            | ^5.0   | Minecraft 协议 | 查询 Minecraft 服务器状态、在线玩家 |
| `games647/minecraft-banner-generator` | ^0.4.1 | 图像生成         | 服务器状态横幅可视化              |



**自动加载配置：**  
项目使用带有两个命名空间前缀的 PSR-4 自动加载：

  * `McModUtils\` → `src/McModUtils/`
  * `App\` → `src/App/`



来源: [composer.json](composer.json#L4-L23), [bootstrap.php](bootstrap.php#L7-L8)

## Node.js 依赖项

Node.js 和 NPM 专门用于开发和部署期间的 API 文档生成。这些依赖项不是应用程序本身的运行时要求。

| 包                        | 类型                     | 用途               |
|--------------------------|------------------------|------------------|
| `apidoc`                 | ^1.2.0 (devDependency) | 从内联代码注释生成 API 文档 |
| `@gitawego/apidoc-theme` | dependency             | API 文档样式的自定义主题   |



**文档生成：**

BASH

``` npm install npm run doc ``` 

此过程使用 apidoc-theme 包中的模板在 `public/docs` 目录中生成文档。

来源: [package.json](package.json#L17-L53), [README.md](README.md#L68-L69)

## 系统和基础设施要求

### 文件系统访问

应用程序需要对 Minecraft 服务器目录具有读取权限，以便进行 mod 解析和文件服务：

**必需的 Minecraft 服务器目录：**

  * Mods 目录 (`/opt/minecraft/mc-server/mods`)
  * 客户端 mods (`/opt/minecraft/mc-server/clientmods`)
  * 配置文件 (`/opt/minecraft/mc-server/config`)
  * 其他整合包目录（kubejs、resourcepacks 等）



**配置默认路径：**

PHP

``` 'baseMinecraftPath' => '/opt/minecraft/mc-server' ``` 

此路径可在 `config.php` 中配置，应根据你实际的 Minecraft 服务器安装位置进行调整。

来源: [config.default.php](config.default.php#L2-L39)

### Web 服务器要求

需要一个 Web 服务器来运行该应用程序。虽然内置的 PHP 开发服务器可用于测试，但生产部署需要一个带有 PHP-FPM 集成的适当 Web 服务器。

**开发服务器：**

BASH

```bash php -S 127.0.0.1:8000 -t public ``` 

**生产服务器：**

  * 推荐：使用 PHP-FPM 的 Nginx
  * Apache with mod_php (替代方案)
  * 配置详情请参阅 [使用 Nginx 进行生产部署 ](5-production-deployment-with-nginx.md)



### 网络要求

应用程序需要网络连接来查询 Minecraft 服务器状态：

| 协议              | 默认端口        | 用途         |
|-----------------|-------------|------------|
| Minecraft Query | 25565 (可配置) | 服务器状态、玩家数量 |
| Minecraft 服务器连接 | 25565 (可配置) | 服务器通信      |



应用程序支持单服务器和多服务器配置，每台服务器都需要自己的主机和端口设置。

来源: [config.default.php](config.default.php#L41-L63)

## 可选系统集成

### Webhook 自动化（可选）

对于自动化部署，项目支持 webhook 集成：

**所需组件：**

  * `webhook` 包（Linux 服务）
  * Git 访问权限（已配置 SSH 密钥）
  * 部署脚本执行权限



**部署自动化优势：**

  * 从 Git 仓库自动更新代码
  * 依赖管理（composer、npm）
  * API 文档重新生成
  * 服务重启能力



来源: [README.md](README.md#L78-L139)

### 静态文件回退（可选）

为了获得最佳性能，请配置 Nginx 直接提供静态文件而无需 PHP 处理。应用程序包含一个回退机制（`public/files/mods/index.php`），用于在静态服务配置不可用或失败时处理文件。

**性能对比：**

| 提供方式     | 响应时间   | 备注           |
|----------|--------|--------------|
| PHP 后端   | ~480ms | 包括 mod 解析和处理 |
| Nginx 直接 | ~190ms | 推荐用于生产环境     |



来源: [README.md](README.md#L146-L173), [config.default.php](config.default.php#L10-L28)

对于拥有大量 mod 集合（>500 个 mod）的生产环境部署，建议将 `memory_limit` 增加到 4096M，以防止在批量 mod 解析操作期间内存耗尽。应用程序使用基于文件的缓存而不是数据库存储，以在保持性能的同时最小化依赖开销。 

## 安装先决条件摘要

在继续安装之前，请确保你的系统满足以下检查清单：

### 基础要求

  * Linux 操作系统（Debian 12+、Manjaro 或同版本）
  * PHP 8.2+ 及 FPM
  * Composer（PHP 包管理器）
  * Node.js 和 NPM（用于文档生成）



### PHP 扩展

  * 已启用 `php-zip`
  * 已启用 `php-gd`



### PHP 配置

  * `memory_limit` ≥ 2048M
  * `max_execution_time` ≥ 90



### 文件系统

  * 对 Minecraft 服务器目录的读取权限
  * 对应用程序目录的写入权限



### 网络

  * 到 Minecraft 服务器的网络连接
  * 用于服务器查询的开放端口



### 开发/生产工具

  * Git（用于 webhook 自动化，可选）
  * Nginx（用于生产部署）



一旦验证了所有先决条件，请继续阅读 [安装和设置 ](4-installation-and-setup.md) 获取详细的部署说明。
