# 快速开始

在几分钟内快速上手并运行 php-minecraft-server-info。这个后端 API 系统从 Minecraft 模组 `.jar` 文件中提取元数据，并通过 RESTful 接口提供服务器状态信息。该项目通过智能缓存策略优先考虑性能——比较实际解析时间与缓存 JSON 检索时间显示出的显著延迟差异，使得基于文件的缓存比数据库解决方案更具优势。

![项目概览](https://github.com/chyuaner/php-minecraft-server-info/blob/master/README.md?raw=true)

## 系统架构概览

应用程序遵循关注点分离的模块化架构。启动系统初始化 PSR-4 自动加载并合并配置文件，而 Slim 框架处理 HTTP 路由。核心业务逻辑位于 `McModUtils` 命名空间中，负责处理模组文件和监控服务器状态。

来源：[bootstrap.php](bootstrap.php#L1-L15), [public/index.php](public/index.php#L1-L78), [composer.json](composer.json#L1-L25)

## 安装与初始设置

安装过程需要 PHP 8.2+ 及特定扩展。首先克隆仓库并使用 Composer 安装依赖，Composer 管理应用程序及包括 Slim 框架和 Minecraft 查询工具在内的第三方库的自动加载。

**按顺序执行以下命令：**

BASH

```bash git clone <repository-url> cd php-minecraft-server-info composer install composer dump-autoload cp config.default.php config.php ``` 

复制配置模板后，编辑 `config.php` 以匹配你的 Minecraft 服务器环境。配置文件包含模组文件夹路径、服务器连接详细信息，以及如果你在使用 Velocity 运行代理设置时的多个服务器定义。

来源：[README.md](README.md#L1-L100), [bootstrap.php](bootstrap.php#L1-L15)

## 配置要点

配置系统采用双层方法：`config.default.php` 包含基础模板，而 `config.php` 存储你的本地覆盖设置。启动过程合并这些数组，允许你将特定环境的设置与版本控制的默认设置分开。

**关键配置部分：**

| 配置部分                 | 目的            | 示例值                             |
|----------------------|---------------|---------------------------------|
| `base_url`           | 你的 API 公共 URL | `http://localhost:8000`         |
| `mods_path`          | 主模组文件夹路径      | `/opt/minecraft/mc-server/mods` |
| `serverside_prefixs` | 仅服务器端模组的前缀    | `['serveronly_', 'server_']`    |
| `minecraft_host`     | 服务器 IP 地址     | `127.0.0.1`                     |
| `minecraft_port`     | 服务器端口         | `25565`                         |



配置支持多个模组类别（通用、客户端、服务器端），并具有不同的过滤规则。可以根据前缀匹配包含或排除服务器端模组，使你能够为不同场景提供不同的模组列表。

设置 `mods` 配置路径时，请确保 Web 服务器用户对所有 Minecraft 目录具有读取权限。应用程序需要读取 `.jar` 文件头以提取模组元数据，而无需完全解压归档文件。

来源：[config.default.php](config.default.php#L1-L67), [bootstrap.php](bootstrap.php#L8-L15)

## 运行开发服务器

PHP 内置 Web 服务器提供了一种无需配置 Nginx 或 Apache 即可测试应用程序的快速方法。服务器应指向包含所有路由定义和入口点的 `public` 目录。

**启动开发服务器：**

BASH

```bash php -S 127.0.0.1:8000 -t public ``` 

在 `http://localhost:8000` 访问应用程序。根端点显示一个简单的着陆页，而 API 端点立即开始处理。开发服务器适合测试，但由于性能限制和安全考虑，不应在生产环境中使用。

来源：[README.md](README.md#L40-L50), [public/index.php](public/index.php#L1-L78)

## 核心 API 端点

应用程序通过主路由器加载的不同路由文件暴露三个主要 API 类别。每个端点支持 HTML 和 JSON 响应格式，通过 `Accept` 头或 `type` 查询参数控制。

### 模组信息端点

| 端点             | 方法  | 描述              | 示例                                  |
|----------------|-----|-----------------|-------------------------------------|
| `/mods`        | GET | 列出所有带元数据的通用模组   | `http://localhost:8000/mods`        |
| `/mods/zip`    | GET | 将所有模组下载为 zip 归档 | `http://localhost:8000/mods/zip`    |
| `/client-mods` | GET | 列出客户端专用模组       | `http://localhost:8000/client-mods` |
| `/server-mods` | GET | 列出仅服务器端模组       | `http://localhost:8000/server-mods` |



**来自`/mods` 的示例 JSON 响应：**

JSON

```json { "modsHash": "d9e9ae1ba3b4771ed389518777747fd38b641c25ef7a9a5ff2628e83d57f474d", "updateAt": "2025-07-27T14:52:10+08:00", "mods": [ { "name": "Apothic Attributes", "authors": ["Shadows_of_Fire"], "version": "2.9.0", "filename": "ApothicAttributes-1.21.1-2.9.0.jar", "sha1": "eed5808509eb279fd342cafebadd5b95accb4ef8", "download": "https://mc-api.yuaner.tw/files/mods/ApothicAttributes-1.21.1-2.9.0.jar" } ] } ``` 

模组解析系统仅从 `.jar` 归档中读取文件头，与完全解压相比显著提高了性能。结果缓存在 `public/static/` 中，并使用 SHA-256 哈希进行内容验证。

来源：[public/mods.php](public/mods.php#L1-L200), [src/McModUtils/Mods.php](src/McModUtils/Mods.php#L1-L80)

### 服务器状态端点

| 端点                 | 方法  | 描述         | 参数                 |
|--------------------|-----|------------|--------------------|
| `/ping`            | GET | Ping 默认服务器 | 无                  |
| `/ping/{serverId}` | GET | Ping 特定服务器 | 配置中的 `serverId`    |
| `/query/`          | GET | 查询服务器详细信息  | 需要配置中的查询端口         |
| `/online-players`  | GET | 获取在线玩家列表   | `otype=name` 以简化输出 |



服务器监控使用两种协议：现代 ping 协议（服务器列表 ping）和传统 query 协议。可以在 `minecraft_servers` 数组中配置多个服务器，支持代理设置，通过 Velocity 或 BungeeCord 代理访问不同的后端服务器。

来源：[public/server.php](public/server.php#L1-L100), [src/McModUtils/Server.php](src/McModUtils/Server.php#L1-L60)

## 测试你的安装

通过向每种端点类型发出测试请求来验证你的设置。以下序列确认配置、文件权限和网络连接都正常工作。

BASH

```bash curl http://localhost:8000/mods # 测试特定模组类型 curl http://localhost:8000/client-mods # 测试服务器 ping curl http://localhost:8000/ping # 测试明确的 JSON 输出 curl -H "Accept: application/json" http://localhost:8000/mods ``` 

如果遇到权限错误，请检查 Web 服务器用户是否可以读取你的 Minecraft 目录。如果模组列表返回空数组，请验证配置中的 `mods_path` 指向包含 `.jar` 文件的有效目录。

使用 PHP 开发服务器进行测试时，请注意如果文件不存在，静态文件服务会回退到 PHP。在生产环境中使用 Nginx 时，你可以配置静态文件服务，完全绕过 PHP 以处理 `public/static/` 目录中的缓存 JSON 文件，从而获得最大性能。

来源：[public/mods.php](public/mods.php#L130-L200), [public/server.php](public/server.php#L1-L60)

## 后续步骤

随着开发服务器的运行和基本功能的验证，你准备好探索更高级的主题了。为了全面了解系统如何处理不同的模组加载器和缓存策略，请继续阅读[系统要求和依赖 ](3-system-requirements-and-dependencies.md)。在规划生产部署时，请查阅[使用 Nginx 进行生产部署 ](5-production-deployment-with-nginx.md)以获取最佳性能配置。

要深入理解能够从 NeoForge、Forge 和 Fabric 模组中提取元数据而无需完全解压的模组解析架构，请继续阅读[模组解析系统 (NeoForge, Forge, Fabric) ](7-mod-parsing-system-neoforge-forge-fabric.md)。对于多服务器设置或代理配置，[多服务器配置 ](15-multi-server-configuration.md)指南解释了如何配置和监控多个后端服务器。
