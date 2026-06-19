# 多服务器配置

该架构通过统一的 API 网关实现了对多个 Minecraft 服务器实例的集中管理，支持基于代理的服务器组（Velocity/BungeeCord）和独立服务器部署。该系统提供了灵活的服务器发现机制、统一的状态监控以及跨异构服务器环境的 Mod 分发。

## 配置架构

多服务器配置遵循分层合并策略，其中默认配置提供结构模板，而本地配置覆盖操作参数。位于 `bootstrap.php` 中的配置加载器将 `config.default.php` 与可选的 `config.php` 文件合并，允许开发人员维护基础设施默认值与环境特定设置之间的分离 [来源：bootstrap.php](/bootstrap.php#L8-L14)。

### 服务器定义结构

每个服务器实例在 `minecraft_servers` 配置键中定义为一个关联数组，包含五个核心属性，支持编程访问和用户识别 [来源：config.default.php](/config.default.php#L38-L53)：

**服务器配置参数：**

| 参数                  | 类型      | 必填  | 描述                     |
|---------------------|---------|-----|------------------------|
| `name`              | string  | 是   | 用于 UI 显示的人类可读名称        |
| `public_hoststring` | string  | 是   | 公共连接字符串（可能包含代理命令）      |
| `host`              | string  | 是   | 服务器主机名或 IP 地址          |
| `port`              | integer | 是   | 服务器端口（后端服务器通常不是 25565） |
| `qport`             | integer |null | 否                      | 用于高级状态检索的 Query 协议端口 |



`public_hoststring` 字段支持代理服务器命令（例如 `mcserver.barian.moe /server youer1`），这使其非常适合 Velocity/BungeeCord 环境，因为这类环境的公共入口点与后端服务器地址不同。 

## 服务器实例化模式

`Server` 类通过多种构造函数模式提供灵活的实例化，使开发人员能够通过配置 ID、显式主机/端口参数选择服务器，或默认回退到主服务器 [来源：src/McModUtils/Server.php](/src/McModUtils/Server.php#L41-L69)。

**构造函数参数优先级：**

  1. **通过 ID** ：`$server = new Server($id='youer1')` \- 从 `minecraft_servers` 数组加载完整配置
  2. **通过主机/端口覆盖** ：`$server = new Server($id='youer1', $port=24566)` \- 从 ID 加载但允许运行时端口覆盖
  3. **显式参数** ：`$server = new Server($host='127.0.0.1', $port=25565)` \- 直接指定，无需配置查找
  4. **默认回退** ：`$server = new Server()` \- 从 `minecraft_host`、`minecraft_port` 和 `minecraft_public_hoststring` 键加载



构造函数灵活的参数处理机制支持动态服务器发现模式。使用代理系统时，传递后端服务器 ID 以加载代理感知配置；对于独立监控，使用显式的主机/端口参数。 

## 多服务器 API 集成

所有服务器状态端点均通过 URL 路径参数支持可选的服务器标识符，从而无需定义单独的端点即可实现对多个服务器实例的统一 API 访问 [来源：public/server.php](/public/server.php#L57-L61)。

### 端点模式

| 端点       | URL 模式                         | 服务器选择                   | 默认行为        |
|----------|--------------------------------|-------------------------|-------------|
| 服务器状态    | `/ping[/{serverId}]`           | `new Server($serverId)` | 使用配置中的默认服务器 |
| 玩家列表     | `/online-players[/{serverId}]` | `new Server($serverId)` | 使用默认服务器     |
| 服务器横幅    | `/banner[/{serverId}]`         | `new Server($serverId)` | 使用默认服务器     |
| Query 协议 | `/query/`                      | 使用 `minecraft_qport` 配置 | 不特定于服务器     |



**API 请求示例：**

BASH

```bash curl https://api.example.com/ping # 特定服务器状态 curl https://api.example.com/ping/youer1 # 带有玩家列表的特定服务器横幅 curl https://api.example.com/banner/youer2?players=1 # 简单名称列表形式的在线玩家 curl https://api.example.com/online-players/youer1?otype=name ``` 

## Mod 分发配置

多服务器环境通常需要在仅客户端、仅服务器和通用 Mod 之间进行隔离。配置系统通过三种 Mod 类型定义支持这一点，每种类型都有独立的路径解析和过滤规则 [来源：config.default.php](/config.default.php#L12-L33)。

### Mod 类型配置矩阵

| Mod 类型   | 路径前缀          | `ignore_serverside_prefix` | `only_serverside_prefix` | 用例               |
|----------|---------------|----------------------------|--------------------------|------------------|
| `common` | `/mods`       | true                       | false                    | 客户端和服务器之间共享的 Mod |
| `client` | `/clientmods` | true                       | false                    | 客户端视觉/调整 Mod     |
| `server` | `/mods`       | false                      | true                     | 服务器端性能/插件 Mod    |



**服务端前缀过滤：**

系统根据 `serverside_prefixs` 配置（`['serveronly_', 'server_']`）应用文件名前缀过滤。这无需单独的目录结构即可实现自动 Mod 分类：

  * **通用 Mod** ：包含没有服务端前缀的文件
  * **服务器 Mod** ：仅包含具有匹配前缀的文件
  * **客户端 Mod** ：排除具有服务端前缀的文件



**API 端点结构：**

路由系统通过配置映射为每种 Mod 类型动态生成端点，为所有 Mod 类别提供一致的 API 模式 [来源：public/mods.php](/public/mods.php#L36-L39)：

PHP

``` $routerConfigMap = [ 'mods' => 'common', // → /mods, /mods/zip 等。 'client-mods' => 'client', // → /client-mods, /client-mods/zip 等。 'server-mods' => 'server', // → /server-mods, /server-mods/zip 等。 ]; ``` 

## Query 协议集成

除了标准的 ping 协议之外，为了进行高级服务器监控，系统通过 `qport` 参数支持 Minecraft 的 Query 协议。这提供了标准服务器列表 ping 无法获取的额外服务器信息 [来源：public/server.php](/public/server.php#L78-L88)。

**Query 协议特性：**

  * **协议** ：基于 UDP 的 Query 协议（旧版 Minecraft 服务器协议）
  * **配置** ：默认服务器使用 `minecraft_qport`，特定服务器使用 `qport`
  * **端点** ：`/query/` 返回 `info`（服务器详细信息）和 `players`（玩家列表）
  * **回退** ：如果 `qport` 为 null，端点可能无法连接



**Query 与 Ping 协议对比：**

| 特性    | Ping 协议           | Query 协议                  |
|-------|-------------------|---------------------------|
| 传输    | TCP               | UDP                       |
| 信息    | MOTD、玩家数量、版本、示例玩家 | 扩展服务器信息、完整玩家列表、插件         |
| 防火墙要求 | TCP 端口（默认 25565）  | UDP query 端口（通常与游戏端口相同）   |
| 响应格式  | JSON 结构           | 分离的 `info` 和 `players` 对象 |



## 错误处理和服务器验证

系统在尝试连接之前实施可靠的服务器存在性验证，防止对未定义服务器的连接尝试 [来源：src/McModUtils/Server.php](/src/McModUtils/Server.php#L21-L35)。

**验证流程：**

  1. **服务器 ID 检查** ：`isExistServerId($id)` 验证配置中是否存在该 ID
  2. **配置加载** ：`loadFromServerId($id)` 从配置填充服务器属性
  3. **连接尝试** ：Ping/query 操作尝试与超时的服务器连接
  4. **异常传播** ：`MinecraftPingException` 传播到错误处理程序



**错误响应示例：**

JSON

``` { "error": "Failed to connect or create a socket: 111 (Connection refused)" } ``` 

响应格式系统自动处理 JSON 内容协商，确保 API 消费者无论底层异常如何都能收到结构化的错误响应 [来源：src/App/ResponseFormatter.php](/src/App/ResponseFormatter.php#L34-L52)。

## 高级配置模式

### 代理服务器集成

对于 Velocity 或 BungeeCord 部署，将代理配置为默认服务器，同时维护后端服务器配置以进行详细监控：

PHP

``` 'minecraft_public_hoststring' => 'play.example.com', 'minecraft_host' => '127.0.0.1', 'minecraft_port' => 25577, // 代理端口 'minecraft_servers' => [ 'survival' => [ 'name' => 'Survival World', 'public_hoststring' => 'play.example.com /server survival', 'host' => '127.0.0.1', 'port' => 25565, // 后端服务器端口 'qport' => 25565, ], ] ``` 

### 动态服务器发现

灵活的构造函数模式支持基于外部配置源进行运行时服务器发现：

PHP

``` // 从数据库或外部服务加载服务器 ID $serverId = fetchActiveServerFromDatabase(); $server = new Server($serverId); $status = $server->outputPing(); ``` 

## 相关文档

要全面了解系统架构，请探索以下相关主题：

  * **[服务器监控和状态查询 ](8-server-monitoring-and-status-queries.md)** \- ping 和 query 协议的详细说明
  * **[服务器状态 API ](12-server-status-apis.md)** \- 完整的 API 端点文档
  * **[Mod 解析系统 ](7-mod-parsing-system-neoforge-forge-fabric.md)** \- 如何分析和分类 Mod
  * **[错误处理和异常管理 ](16-error-handling-and-exception-management.md)** \- 错误传播和响应处理
  * **[响应格式和内容协商 ](14-response-formatting-and-content-negotiation.md)** \- 系统如何格式化 JSON 与 HTML 响应


