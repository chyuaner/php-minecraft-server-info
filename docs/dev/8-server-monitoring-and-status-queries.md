# 服务器监控和状态查询

服务器监控系统通过 HTTP API 提供实时的 Minecraft 服务器状态信息，支持标准 ping/query 协议和可视化横幅生成。该模块使开发者能够获取服务器健康状况指标、玩家信息，并生成用于集成到 Web 应用程序、仪表板或监控工具中的状态图像。

## 架构概览

监控系统采用三层架构构建，将服务器配置管理、协议通信和响应格式化分离。核心 `Server` 类封装了所有服务器交互逻辑，利用 xPaw Minecraft Query 库处理协议，并将内容协商委托给 `ResponseFormatter` 组件。

这种架构确保了服务器配置保持集中，同时通过定义良好的接口抽象协议处理。该系统支持单服务器设置和多服务器配置，实现了从独立实例到 Velocity 代理网络的灵活部署场景。

## 服务器配置模型

配置系统支持两种主要运行模式：单个默认服务器和多个命名服务器。配置从全局 `$GLOBALS['config']` 数组加载，系统根据请求参数智能选择合适的服务器。

### 单个默认服务器配置

对于简单部署，系统使用单个默认服务器配置，可通过三个全局键访问：

| Configuration Key             | Purpose                  | Example Value          |
|-------------------------------|--------------------------|------------------------|
| `minecraft_public_hoststring` | 面向用户显示的服务器地址             | `mcserver.example.com` |
| `minecraft_host`              | 用于协议连接的内部网络地址            | `127.0.0.1`            |
| `minecraft_port`              | Minecraft 服务器 Ping 协议端口  | `25565`                |
| `minecraft_qport`             | Query 协议端口（可选，null 表示禁用） | `25565`                |



来源：[config.default.php](config.default.php#L42-L45)

### 多服务器配置

对于涉及多个 Minecraft 服务器或 Velocity 代理网络的复杂部署，系统支持在 `minecraft_servers` 数组中定义命名服务器：

PHP

``` 'minecraft_servers' => [ 'youer1' => [ 'name' => 'youer1', 'public_hoststring' => 'mcserver.barian.moe /server youer1', 'host' => '127.0.0.1', 'port' => 24565, 'qport' => 24565, ], 'youer2' => [ 'name' => 'youer2', 'public_hoststring' => 'mcserver.barian.moe /server youer2', 'host' => '127.0.0.1', 'port' => 23565, 'qport' => null, ], ] ``` 

来源：[config.default.php](config.default.php#L48-L63)

每个服务器定义需要：

  * **Server ID** : 用于 API 路由的数组键
  * **name** : 用于显示目的的可读标识符
  * **public_hoststring** : 用户应使用的连接字符串
  * **host** : 用于协议查询的内部 IP 地址或主机名
  * **port** : Ping 协议的服务器端口
  * **qport** : 用于检索扩展信息的可选 Query 协议端口



### 服务器实例化策略

`Server` 类构造函数实现了灵活的初始化策略，根据提供的参数自动选择适当的配置：

该策略支持多种实例化模式：

  * `new Server()` → 从全局配置加载默认服务器
  * `new Server('127.0.0.1', 25565)` → 直接指定主机/端口
  * `new Server(null, null, 'youer1')` → 加载命名服务器配置
  * `new Server(null, null, 'youer1', null, 25566)` → 加载命名服务器并覆盖端口



来源：[src/McModUtils/Server.php](src/McModUtils/Server.php#L49-L68)

## 协议通信层

系统实现了两个 Minecraft 服务器协议：用于基本状态信息的标准 Ping 协议和用于扩展服务器详情的 Query 协议。两个协议均通过 xPaw 库处理，提供强大的异常处理和连接管理。

### Ping 协议实现

Ping 协议是检索服务器状态的主要方法，在 `fetchPing()` 方法中使用 `xPaw\MinecraftPing` 类实现：

PHP

```php public function fetchPing() : array { $host = $this->host; $port = $this->port; try { $Query = new MinecraftPing($host, $port); $output = $Query->Query(); } catch(MinecraftPingException $e) { $output = ['error' => $e->getMessage()]; throw $e; } finally { if($Query) { $Query->Close(); } } $this->pingData = $output; return $output; } ``` 

来源：[src/McModUtils/Server.php](src/McModUtils/Server.php#L70-L94)

该实现通过 `finally` 块确保正确的资源管理，即使在发生异常时也保证关闭 socket。Ping 数据在实例级别的 `$pingData` 属性中缓存，使得可以在不进行冗余网络请求的情况下多次调用方法。

`outputPing()` 方法实现了惰性求值模式：仅在数据尚未缓存时执行网络调用，对于需要从单个查询响应中提取多个服务器指标的场景，这提高了效率。

### Query 协议实现

为了获取包括玩家列表在内的扩展服务器信息，系统使用 `xPaw\MinecraftQuery` 提供了专用的 Query 协议端点：

PHP

```php $app->get('/query/', function (Request $request, Response $response, array $args) { $output = []; $Query = new MinecraftQuery(); $Query->Connect($GLOBALS['config']['minecraft_host'], $GLOBALS['config']['minecraft_qport']); $output = [ 'info' => $Query->GetInfo(), 'players' => $Query->GetPlayers(), ]; $formatter = new ResponseFormatter(); return $formatter->format($request, $output); }); ``` 

来源：[public/server.php](public/server.php#L63-L74)

Query 协议要求 Minecraft 服务器在 `server.properties` 中设置 `enable-query=true` 并配置 `query.port`。默认配置中 `qport` 参数默认为 `null`，需要进行显式设置此功能才能正常工作。

### 数据提取方法

`Server` 类提供了专门的方法，用于从 Ping 协议响应中提取特定指标：

| Method                    | Return Type | Description                 | Error Handling |
|---------------------------|-------------|-----------------------------|----------------|
| `getMaxPlayersCount()`    | int         | 来自 `players.max` 的最大玩家容量    | 数据缺失时返回 0      |
| `getOnlinePlayersCount()` | int         | 来自 `players.online` 的当前在线玩家 | 数据缺失时返回 0      |
| `getPlayers()`            | array       | 包括 UUID 在内的玩家样本数据           | 数据缺失时返回空数组     |
| `getPlayersName()`        | array       | 从样本中提取的玩家名称                 | 数据缺失时返回空数组     |



来源：[src/McModUtils/Server.php](src/McModUtils/Server.php#L132-L173)

这些方法实现了防御性编程模式，在访问值之前检查嵌套数组结构，并在数据不可用时提供安全的默认值。

## API 端点

监控系统公开了四个主要的 HTTP 端点，每个端点都为特定用例设计，从状态监控到可视化表示生成。

### 服务器状态端点

`/ping[/{serverId}]` 端点通过 Minecraft Ping 协议提供完整的服务器状态信息：

**请求格式：**

``` GET /ping GET /ping/youer1 ``` 

**响应结构：**

JSON

``` { "description": "A Minecraft Server", "players": { "max": 20, "online": 2, "sample": [ { "id": "330ec9fb-cbb3-3ac5-b19e-6678ebde0b18", "name": "Barianyyy0517" }, { "id": "58dba7b3-3a27-384f-9145-21fac550cde6", "name": "chyuaner" } ] }, "version": { "name": "Youer 1.21.1", "protocol": 767 } } ``` 

来源：[public/server.php](public/server.php#L16-L61)

**错误处理：**  
连接失败返回 HTTP 500 并附带错误详情：

JSON

``` { "error": "Failed to connect or create a socket: 111 (Connection refused)" } ``` 

### 在线玩家端点

`/online-players[/{serverId}]` 端点提供灵活的玩家信息检索，并支持输出格式自定义：

**请求参数：**

| Parameter  | Type  | Values        | Description     |
|------------|-------|---------------|-----------------|
| `serverId` | path  | server ID     | 配置中的命名服务器（可选）   |
| `otype`    | query | `name`, `all` | 输出格式：仅名称或完整玩家数据 |



**使用示例：**

``` GET /online-players → 默认服务器的完整玩家数据 GET /online-players/youer1 → 命名服务器的完整玩家数据 GET /online-players?otype=name → 默认服务器的仅玩家名称 GET /online-players/youer1?otype=name → 命名服务器的仅玩家名称 ``` 

**响应格式：**

完整玩家数据 (`otype=all`):

JSON

``` [ { "id": "330ec9fb-cbb3-3ac5-b19e-6678ebde0b18", "name": "Barianyyy0517" }, { "id": "58dba7b3-3a27-384f-9145-21fac550cde6", "name": "chyuaner" } ] ``` 

仅名称 (`otype=name`):

JSON

``` ["Barianyyy0517", "chyuaner"] ``` 

来源：[public/server.php](public/server.php#L77-L123)

### 服务器横幅端点

`/banner[/{serverId}]` 端点生成适合嵌入网站或论坛的可视化服务器状态横幅：

**请求参数：**

| Parameter  | Type  | Description                  |
|------------|-------|------------------------------|
| `serverId` | path  | 配置中的命名服务器（可选）                |
| `players`  | query | 显示玩家列表的布尔标志（例如 `?players=1`） |



**使用示例：**

``` GET /banner → 默认服务器横幅 GET /banner/youer1 → 命名服务器横幅 GET /banner?players=1 → 带玩家名称的横幅 GET /banner/youer1?players=1 → 带玩家名称的命名服务器横幅 ``` 

**响应格式：**

  * Content-Type: `image/png`
  * HTTP Status: 成功时为 200，连接错误时为 502
  * 通过 `MinecraftBanner\ServerBanner` 生成的二进制图像数据



横幅生成包括服务器延迟测量，在服务器名称、玩家数量和可选玩家列表旁边显示 ping 时间。

来源：[public/server.php](public/server.php#L154-L215)

### Query 协议端点

`/query/` 端点使用 Minecraft Query 协议提供扩展的服务器信息：

**请求格式：**

``` GET /query/ ``` 

**响应结构：**

JSON

``` { "info": { "hostname": "A Minecraft Server", "map": "world", "numplayers": "2", "maxplayers": "20", "hostport": "25565" }, "players": [ {"name": "Player1", "score": "100"}, {"name": "Player2", "score": "200"} ] } ``` 

此端点需要设置 `minecraft_qport` 配置，并且 Minecraft 服务器需要启用 query 协议。

来源：[public/server.php](public/server.php#L63-L74)

## 响应格式化和内容协商

系统通过 `ResponseFormatter` 类实现了复杂的内容协商，根据客户端首选项支持 JSON 和 HTML 输出格式。这使得 API 能够为 Web 浏览器提供人类可读的 HTML，同时为程序化访问保持 JSON 兼容性。

### 协商策略

格式化程序遵循基于优先级的决策树进行格式选择：

来源：[src/App/ResponseFormatter.php](src/App/ResponseFormatter.php#L21-L67)

### 格式优先级规则

| Priority | Detection Method    | Condition                   | Format |
|----------|---------------------|-----------------------------|--------|
| 1        | URL query parameter | `?json=1` or `?type=json`   | JSON   |
| 2        | URL query parameter | `?type=html`                | HTML   |
| 3        | HTTP Accept header  | Contains `application/json` | JSON   |
| 4        | Fallback            | None of the above           | HTML   |



这种灵活的方法实现了无缝集成，既适用于自动客户端（发送 `Accept: application/json`），也适用于直接在浏览器中访问端点的用户。

### HTML 渲染功能

HTML 输出模式包括智能数据展示：

  * **URL 自动链接** ：自动将 URL 转换为可点击的超链接
  * **布尔格式化** ：显示 `true`/`false` 而不是 `1`/`0`
  * **Null 处理** ：显式显示 `null` 值
  * **递归结构** ：使用适当的缩进处理嵌套数组和对象
  * **类名** ：显示对象类名以便调试



来源：[src/App/ResponseFormatter.php](src/McModUtils/Server.php#L72-L111)

## 集成模式和用例

### 基本状态监控

对于简单的仪表板集成，使用带有自动 JSON 内容协商的 `/ping` 端点：

PHP

``` $client = new GuzzleHttp\Client(); $response = $client->get('http://api.example.com/ping'); $data = json_decode($response->getBody(), true); echo "Players: {$data['players']['online']}/{$data['players']['max']}"; ``` 

### 多服务器仪表板

为了监控多个服务器，利用命名服务器配置：

PHP

``` $servers = ['youer1', 'youer2']; $stats = []; foreach ($servers as $serverId) { $response = $client->get("http://api.example.com/ping/{$serverId}"); $stats[$serverId] = json_decode($response->getBody(), true); } ``` 

### 可视化状态指示器

将服务器横幅直接嵌入网页：

HTML

``` <img src="http://api.example.com/banner/youer1?players=1" alt="Server Status" onerror="this.src='offline-banner.png'"> ``` 

横幅端点中的错误处理确保即使离线服务器也能生成状态图像，防止出现损坏的图像图标。

### 玩家列表集成

对于动态玩家显示，使用过滤后的仅名称输出：

PHP

``` $response = $client->get('http://api.example.com/online-players/youer1?otype=name'); $players = json_decode($response->getBody(), true); echo implode(', ', $players); // "Player1, Player2, Player3" ``` 

## 后续步骤

为了加深您对系统功能的理解，请探索以下相关文档部分：

  * **[Server Status APIs ](12-server-status-apis.md)**：详细的 API 参考，包含所有服务器监控端点的参数规范、响应模式和身份验证要求
  * **[Response Formatting and Content Negotiation ](14-response-formatting-and-content-negotiation.md)**：自定义输出格式、处理不同内容类型以及扩展格式化程序以用于自定义用例的综合指南
  * **[Error Handling and Exception Management ](16-error-handling-and-exception-management.md)**：深入分析错误传播、异常处理策略以及服务器连接问题的故障排除
  * **[Performance Optimization Strategies ](17-performance-optimization-strategies.md)**：缓存、减少协议开销以及扩展监控系统以实现高可用性部署的最佳实践


