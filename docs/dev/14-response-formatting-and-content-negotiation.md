# 响应格式化和内容协商

响应格式化系统提供了一种统一的机制，通过智能内容协商以多种格式（JSON/HTML）传递 API 数据。这一抽象层使开发者能够构建与客户端无关的端点，同时在所有 API 路由中保持一致的输出格式。

## 架构概述

该系统以 `ResponseFormatter` 类为核心，实现了数据准备与展示的清晰分离。这种设计允许端点专注于业务逻辑，同时将特定于格式的渲染委托给集中式组件。

该格式化程序与 Slim Framework 的 PSR-7 实现无缝集成，确保与现代 PHP HTTP 标准的兼容性，同时为各种客户端需求提供灵活性 [ResponseFormatter.php](src/App/ResponseFormatter.php#L5-L8)。

## 内容协商策略

协商遵循**基于优先级的级联** 规则，既尊重显式的 URL 参数，也尊重标准的 HTTP 头：

  1. **URL 参数覆盖** \- `?json=1`、`?type=json` 或 `?type=html` 提供显式控制
  2. **Accept 头回退** \- 当不存在 URL 参数时，遵守 `Accept: application/json` 头
  3. **默认 HTML** \- 未请求 JSON 的浏览器接收格式化的 HTML 输出



这种三层方法确保了与现有基于浏览器的客户端的向后兼容性，同时通过标准 HTTP 约定支持编程方式访问 API [ResponseFormatter.php](src/App/ResponseFormatter.php#L21-L41)。

根据设计，URL 参数优先于 Accept 头。这种有意的覆盖允许开发者在调试或测试期间强制使用特定格式，而无需修改客户端头。请务必在 API 规范中清楚地记录此行为，以避免客户端混淆。 

### 协商逻辑实现

`isJson()` 方法封装了完整的协商逻辑：

PHP

```php public function isJson(ServerRequestInterface $request): bool { // URL 參數強制 JSON $query = $request->getQueryParams(); if (!empty($query['json'])) { return true; } if (!empty($query['type'])) { if ($query['type'] == 'json') { return true; } if ($query['type'] == 'html') { return false; } } // Accept header 判斷 $accept = strtolower($request->getHeaderLine('Accept') ?? ''); return strpos($accept, 'application/json') !== false; } ``` 

来源：[ResponseFormatter.php](src/App/ResponseFormatter.php#L21-L41)

## 特定格式渲染

### JSON 输出

JSON 响应利用 PHP 的 `json_encode()` 函数并配合 `JSON_UNESCAPED_UNICODE` 标志来保留 Unicode 字符（这对于国际化玩家名称和 Mod 描述至关重要）。格式化程序会自动设置适当的 `Content-Type` 头：

PHP

``` $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE)); return $response->withHeader('Content-Type', 'application/json; charset=utf-8'); ``` 

来源：[ResponseFormatter.php](src/App/ResponseFormatter.php#L50-L53)

### HTML 输出生成

HTML 格式化程序采用**递归渲染策略** ，将嵌套的数据结构转换为语义化的 HTML 列表。这种方法确保了复杂对象（如 Mod 信息或服务器状态）在浏览器环境中保持可读性。

关键渲染行为：

  * 对象显示带有嵌套属性的类名
  * 数组呈现为分层 `<ul>/<li>` 结构
  * 基本类型显示为纯文本
  * 布尔值转换为 `true`/`false` 字符串
  * URL 自动呈现为可点击链接
  * Null 值显示为 `null`



来源：[ResponseFormatter.php](src/App/ResponseFormatter.php#L72-L111)

| 数据类型    | HTML 表示   | 示例                              |
|---------|-----------|---------------------------------|
| Object  | 类名 + 嵌套属性 | `Server {...properties}`        |
| Array   | 嵌套无序列表    | `<ul><li>key: value</li></ul>`  |
| Boolean | 字符串表示     | `true`, `false`                 |
| URL     | 可点击的锚标签   | `<a href="...">https://...</a>` |
| Null    | 字面量字符串    | `null`                          |



## 与 API 端点的集成模式

格式化程序在所有端点中遵循一致的实例化与调用模式：

PHP

``` $formatter = new ResponseFormatter(); return $formatter->format($request, $data); ``` 

这种模式出现在整个代码库中的服务器状态端点、Mod 信息 API 和文件列表服务中 [server.php](public/server.php#L59-L61), [mods.php](public/mods.php#L232-L233), [other_files.php](public/other_files.php#L69-L70)。

### 状态码支持

`format()` 方法接受一个可选的 `$status` 参数（默认：200），允许端点指定适当的 HTTP 状态码：

PHP

```php public function format(ServerRequestInterface $request, $data, int $status = 200): ResponseInterface ``` 

这使得在保持格式一致的同时，能够为缺失的 Mod 返回 404 或服务器查询失败返回 500 等场景。

来源：[ResponseFormatter.php](src/App/ResponseFormatter.php#L46-L48)

## 错误处理集成

应用程序的错误处理子系统（`AppErrorHandler`）反映了内容协商逻辑，确保错误响应遵循与成功响应相同的格式选择规则。这种一致性意味着客户端可以期望的格式接收错误信息，而无需特殊处理。

错误处理程序实现：

  * 继承 Slim 的 `ErrorHandler` 基类
  * 通过 `isJson()` 方法应用相同的协商逻辑
  * 委托给 Slim 内置的 `JsonErrorRenderer` 或 `HtmlErrorRenderer`
  * 为成功和错误响应保持一致的 `Content-Type` 头



来源：[AppErrorHandler.php](src/App/AppErrorHandler.php#L12-L24), [AppErrorHandler.php](src/App/AppErrorHandler.php#L40-L64)

自定义错误响应时，请保持与主格式化程序的协商逻辑一致。AppErrorHandler 复制了 `isJson()` 方法，而不是调用 ResponseFormatter，这是为了避免循环依赖并保持错误处理独立于应用程序状态。 

## 特殊响应类型

虽然 ResponseFormatter 处理结构化数据，但某些端点直接返回专门的内容类型：

### 二进制下载

文件下载路由完全绕过格式化程序，直接设置头并输出二进制数据：

PHP

``` header('Content-Type: application/java-archive'); header('Content-Disposition: attachment; filename="' . basename($modFilePath) . '"'); header('Content-Length: ' . filesize($modFilePath)); readfile($modFilePath); ``` 

来源：[mods.php](public/mods.php#L48-L60)

### 图像生成

Banner 端点动态生成 PNG 图像，设置 `Content-Type: image/png` 并通过 `imagepng()` 输出二进制图像数据 [server.php](public/server.php#L196-L199)。

这些专门路由展示了对于非结构化数据类型，**直接头操作** 何时优于格式化程序抽象。

## 性能考虑

ResponseFormatter 的轻量级设计引入的开销极小：

  * 除 PSR-7 接口外无外部依赖
  * 每个请求仅实例化一次
  * 递归 HTML 渲染使用 PHP 原生字符串操作
  * JSON 编码利用内置的 `json_encode()` 函数



对于高流量场景，可以考虑：

  * 缓存格式化响应以应对昂贵的数据操作
  * 为频繁访问的 Mod 列表预计算 HTML 表示
  * 对大响应使用 FastCGI 输出缓冲



## 测试策略

测试具有响应格式化的端点时：

  1. **测试两种格式** \- 验证每个端点的 JSON 和 HTML 输出
  2. **验证协商** \- 确认 URL 参数正确覆盖头
  3. **检查内容头** \- 确保 `Content-Type` 与输出格式匹配
  4. **测试错误路径** \- 验证错误响应遵循相同的协商规则
  5. **边缘情况** \- 使用空数组、null 值、嵌套对象进行测试



示例 curl 命令：

BASH

```bash curl "https://api.example.com/ping?type=json" # 依赖 Accept 头 curl -H "Accept: application/json" https://api.example.com/ping # 获取 HTML 默认值 curl https://api.example.com/ping ``` 

## 下一步

了解了响应格式化后，探索这些端点如何处理故障：

  * [错误处理与异常管理 ](16-error-handling-and-exception-management.md) \- 深入研究错误处理架构
  * [服务器状态 API ](12-server-status-apis.md) \- 完整的服务器监控端点文档
  * [性能优化策略 ](17-performance-optimization-strategies.md) \- 高级缓存和性能技术


