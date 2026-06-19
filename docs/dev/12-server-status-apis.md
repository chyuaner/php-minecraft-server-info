# 服务器状态 API

Server Status API 提供了全面的端点，用于监控和查询 Minecraft 服务器的实时状态。这些 API 利用 Minecraft Server List Ping (SLP) 协议和 Query 协议来检索服务器信息、玩家数量，并生成可视化的状态横幅。该系统支持单服务器和多服务器部署，并针对 JSON 或 HTML 响应提供灵活的内容协商。

## 架构概览

Server Status 模块遵循分层架构，具有清晰的关注点分离。`Server` 类封装了所有服务器交互逻辑，API 路由处理 HTTP 请求，`ResponseFormatter` 根据客户端偏好管理内容协商。

## API 端点

### Ping 端点

`/ping[/{serverId}]` 端点使用 Minecraft Server List Ping 协议检索全面的服务器状态信息。包括服务器描述、玩家数量、玩家样本和版本信息。

**端点** : `GET /ping[/{serverId}]`

**参数** :

  * `serverId` (可选): 在 `config.php` 中配置的服务器标识符（例如 `youer1`, `youer2`）。如果省略，则使用默认服务器。



**响应格式** :  
该端点支持基于 `Accept` 请求头或 `?json=1`/`?type=json` 查询参数的内容协商。

来源: [public/server.php](public/server.php#L53-L61)

**响应结构** :

| 字段                 | 类型      | 描述              |
|--------------------|---------|-----------------|
| `description`      | string  | 服务器 MOTD (每日消息) |
| `players.max`      | integer | 最大玩家容量          |
| `players.online`   | integer | 当前在线玩家数量        |
| `players.sample[]` | array   | 在线玩家样本（最多 10 个） |
| `version.name`     | string  | 服务器版本名称         |
| `version.protocol` | integer | 网络协议版本          |



**成功响应示例** :

JSON

``` { "description": "A Minecraft Server", "players": { "max": 20, "online": 2, "sample": [ { "id": "330ec9fb-cbb3-3ac5-b19e-6678ebde0b18", "name": "Barianyyy0517" }, { "id": "58dba7b3-3a27-384f-9145-21fac550cde6", "name": "chyuaner" } ] }, "version": { "name": "Youer 1.21.1", "protocol": 767 } } ``` 

**错误响应示例** :

JSON

``` { "error": "Failed to connect or create a socket: 111 (Connection refused)" } ``` 

来源: [public/server.php](public/server.php#L16-L51)

### Query 端点

`/query/` 端点利用 Minecraft Query 协议 (UDP)，与 ping 协议相比，它提供更详细的服务器信息。该协议需要在 `server.properties` 中配置 `enable-query=true` 和 `query.port`。

**端点** : `GET /query/`

**配置要求** :

  * 在 `server.properties` 中启用: 

``` enable-query=true query.port=25565 ``` 

  * 在 `config.php` 中配置 `minecraft_qport`



**响应结构** :

| 字段        | 类型     | 描述                   |
|-----------|--------|----------------------|
| `info`    | object | 服务器元数据 (主机名、游戏模式、地图) |
| `players` | array  | 所有在线玩家列表             |



来源: [public/server.php](public/server.php#L63-L74)

Query 协议可以返回完整的玩家列表（而不仅仅是像 ping 那样的样本），但它需要 UDP 端口访问和特定的服务器配置。使用 ping 进行快速状态检查，当需要完整的玩家列表时使用 query。 

### 在线玩家端点

`/online-players[/{serverId}]` 端点提供对玩家信息的专门访问，并支持灵活的输出格式。

**端点** : `GET /online-players[/{serverId}]`

**查询参数** :

  * `otype` (可选): 输出格式 
    * `name` (默认): 返回玩家名称数组
    * `all`: 返回包含 UUID 的玩家对象数组



**示例** :

**1\. 仅获取玩家名称** :

``` GET /online-players?otype=name ``` 

响应:

JSON

``` ["Barianyyy0517", "chyuaner"] ``` 

**2\. 获取完整玩家信息** :

``` GET /online-players?otype=all ``` 

响应:

JSON

``` [ { "id": "330ec9fb-cbb3-3ac5-b19e-6678ebde0b18", "name": "Barianyyy0517" }, { "id": "58dba7b3-3a27-384f-9145-21fac550cde6", "name": "chyuaner" } ] ``` 

**3\. 查询特定服务器** :

``` GET /online-players/youer1?otype=name ``` 

来源: [public/server.php](public/server.php#L104-L123)

### Banner 端点

`/banner[/{serverId}]` 端点生成一个可视化的 PNG 状态横幅，用于显示服务器信息。非常适合用于 Discord 签名、网站状态指示器或嵌入式状态显示。

**端点** : `GET /banner[/{serverId}]`

**查询参数** :

  * `players` (可选): 布尔值，用于在横幅中显示玩家列表 
    * `0` 或省略: 显示服务器名称
    * `1`: 显示在线玩家名称



**使用示例** :

  1. **基本横幅 (服务器名称)** :

``` GET /banner ``` 

显示: 服务器名称、在线/最大玩家数、ping 时间

  2. **玩家列表横幅** :

``` GET /banner?players=1 ``` 

显示: "Online: player1, player2, ..."

  3. **多服务器横幅** :

``` GET /banner/youer1?players=1 ``` 




来源: [public/server.php](public/server.php#L154-L200)

Banner 端点在生成过程中会自动计算 ping 时间。如果服务器名称足够短（<30 个字符），ping 时间将显示在同一行以实现紧凑显示。 

## Server 类实现

`src/McModUtils/Server.php` 中的 `Server` 类封装了所有服务器交互逻辑。它支持多种初始化模式，并为服务器数据提供方便的访问器方法。

### 初始化模式

`Server` 构造函数接受灵活的参数以支持不同的用例:

**初始化示例** :

PHP

``` // 1. 从服务器 ID 加载（最常用） $server = new Server('youer1'); // 2. 使用默认服务器 $server = new Server(); // 3. 自定义主机和端口 $server = new Server('127.0.0.1', 25565); // 4. 从配置 ID 覆盖特定字段 $server = new Server(null, 25570, 'youer1'); ``` 

来源: [src/McModUtils/Server.php](src/McModUtils/Server.php#L49-L68)

### 核心方法

**fetchPing()** : 执行实际的服务器 ping 操作并返回原始数据。该方法处理连接异常并确保正确的 socket 清理。

来源: [src/McModUtils/Server.php](src/McModUtils/Server.php#L70-L94)

**outputPing()** : 返回缓存的 ping 数据或如果尚未检索则执行 fetchPing()。实现延迟加载模式以最大程度减少网络调用。

来源: [src/McModUtils/Server.php](src/McModUtils/Server.php#L96-L101)

**getMaxPlayersCount()** : 使用安全的数组访问从 ping 数据中提取最大玩家容量。

来源: [src/McModUtils/Server.php](src/McModUtils/Server.php#L132-L141)

**getOnlinePlayersCount()** : 从 ping 数据中检索当前在线玩家数量。

来源: [src/McModUtils/Server.php](src/McModUtils/Server.php#L143-L152)

**getPlayers()** : 返回包含 UUID 和名称的在线玩家样本。

来源: [src/McModUtils/Server.php](src/McModUtils/Server.php#L154-L164)

**getPlayersName()** : 便捷方法，仅从玩家样本中提取玩家名称。

来源: [src/McModUtils/Server.php](src/McModUtils/Server.php#L166-L173)

## 响应格式化

所有 Server Status API 都使用 `ResponseFormatter` 类进行一致的内容协商。这使得与使用 JSON 的应用程序和人类可读的 HTML 视图能够无缝集成。

### 内容协商逻辑

格式化程序通过基于优先级的决策树确定输出格式:

**优先级顺序** :

  1. 查询参数 `?json=1`
  2. 查询参数 `?type=json`
  3. HTTP `Accept` 请求头包含 `application/json`
  4. 默认为 HTML



来源: [src/App/ResponseFormatter.php](src/App/ResponseFormatter.php#L21-L41)

### HTML 渲染功能

渲染 HTML 响应时，格式化程序提供智能格式化:

  * **对象** : 显示类名，后跟递归属性列表
  * **数组** : 渲染为嵌套的 `<ul>` 列表
  * **布尔值** : 渲染为 `true`/`false` 字符串
  * **URL** : 自动转换为可点击链接
  * **Null 值** : 渲染为 `null`
  * **标量值** : 渲染时进行 HTML 转义



来源: [src/App/ResponseFormatter.php](src/App/ResponseFormatter.php#L72-L111)

## 多服务器配置

系统通过 `minecraft_servers` 配置数组支持监控多个 Minecraft 服务器。每个服务器都有独立的配置，包括内部连接详细信息和公共显示字符串。

### 配置结构

PHP

``` 'minecraft_servers' => [ 'youer1' => [ 'name' => 'youer1', // 显示名称 'public_hoststring' => 'mcserver.barian.moe /server youer1', 'host' => '127.0.0.1', // 内部 IP 'port' => 24565, // 查询端口 'qport' => 24565, // Query 协议端口 ], 'youer2' => [ 'name' => 'youer2', 'public_hoststring' => 'mcserver.barian.moe /server youer2', 'host' => '127.0.0.1', 'port' => 23565, 'qport' => null, ], ] ``` 

来源: [config.default.php](config.default.php#L48-L63)

### 服务器 ID 验证

`Server::isExistServerId()` 方法在尝试加载之前验证提供的服务器 ID 是否存在于配置中。

来源: [src/McModUtils/Server.php](src/McModUtils/Server.php#L18-L21)

## 错误处理

Server Status API 为网络故障和配置问题实现了全面的错误处理。

### Ping 异常

`fetchPing()` 方法将 `xPaw\MinecraftPing` 库操作包装在 try-catch-finally 块中，以确保即使在发生错误时也能正确清理资源。

**错误响应格式** :

JSON

``` { "error": "Failed to connect or create a socket: 111 (Connection refused)" } ``` 

常见错误场景:

  * 连接被拒绝（服务器离线）
  * 连接超时
  * 无效的主机/端口组合
  * 网络防火墙阻止连接



来源: [src/McModUtils/Server.php](src/McModUtils/Server.php#L74-L91)

在客户端应用程序中实现错误处理时，请始终检查响应中的 `error` 字段并优雅地处理连接故障。考虑对瞬态网络问题实现具有指数退避的重试逻辑。 

## API 比较

| 端点                             | 协议        | 返回内容           | 用例        |
|--------------------------------|-----------|----------------|-----------|
| `/ping[/{serverId}]`           | TCP (SLP) | 完整服务器状态 + 玩家样本 | 快速状态检查    |
| `/query/`                      | UDP       | 服务器信息 + 所有玩家   | 完整玩家列表    |
| `/online-players[/{serverId}]` | TCP (SLP) | 玩家名称或对象        | 以玩家为中心的查询 |
| `/banner[/{serverId}]`         | TCP (SLP) | PNG 图像         | 可视化状态显示   |



## 集成示例

### JavaScript/Fetch 示例

JAVASCRIPT

```php // 获取带有 JSON 响应的服务器状态 async function getServerStatus(serverId = null) { const url = serverId ? `/ping/${serverId}?type=json` : '/ping?type=json'; const response = await fetch(url); const data = await response.json(); if (data.error) { console.error('Server error:', data.error); return null; } return { description: data.description, players: data.players.online, maxPlayers: data.players.max, version: data.version.name }; } // 获取在线玩家名称 async function getOnlinePlayers(serverId = null) { const url = serverId ? `/online-players/${serverId}?type=json&otype=name` : '/online-players?type=json&otype=name'; const response = await fetch(url); return await response.json(); } ``` 

### PHP 集成示例

PHP

``` <?php require __DIR__ . '/vendor/autoload.php'; require __DIR__ . '/bootstrap.php'; use McModUtils\Server; // 查询特定服务器 $server = new Server('youer1'); // 获取玩家信息 $onlineCount = $server->getOnlinePlayersCount(); $maxCount = $server->getMaxPlayersCount(); $players = $server->getPlayers(); echo "Server Status:\n"; echo "Players: {$onlineCount}/{$maxCount}\n"; echo "Online Players:\n"; foreach ($players as $player) { echo " - {$player['name']}\n"; } ?> ``` 

来源: [src/McModUtils/Server.php](src/McModUtils/Server.php#L1-L175)

## 性能考虑

Server Status API 实现了多项性能优化:

  1. **延迟加载** : 仅在第一次通过 `outputPing()` 访问时才获取 ping 数据
  2. **数据缓存** : 一旦获取，ping 数据将存储在 `$pingData` 属性中，以避免在单个请求中重复进行网络调用
  3. **资源清理** : `finally` 块确保即使在发生异常时也能正确关闭 socket



来源: [src/McModUtils/Server.php](src/McModUtils/Server.php#L85-L91), [src/McModUtils/Server.php](src/McModUtils/Server.php#L96-L101)

对于需要在请求之间进行额外缓存的生产环境部署，请参阅 [缓存机制和缓存失效 ](9-caching-mechanism-and-cache-invalidation.md) 文档。

## 后续步骤

掌握 Server Status API 后，请探索以下相关主题:

  * **[Mod 信息 API ](11-mod-information-apis.md)**: 查询已安装的模组和模组元数据
  * **[文件服务 API ](13-file-serving-apis.md)**: 向客户端提供 Minecraft 服务器文件（模组、配置）
  * **[响应格式化和内容协商 ](14-response-formatting-and-content-negotiation.md)**: 深入了解 ResponseFormatter 实现
  * **[多服务器配置 ](15-multi-server-configuration.md)**: 高级多服务器设置模式
  * **[错误处理和异常管理 ](16-error-handling-and-exception-management.md)**: 全面的错误处理策略


