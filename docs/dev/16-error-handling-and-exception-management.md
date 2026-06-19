# 错误处理和异常管理

该 PHP Minecraft 服务器信息系统的错误处理架构通过 Slim Framework 的中间件栈实现了集中式处理，结合了全局异常处理器、内容感知响应格式以及特定于域的错误恢复机制。系统在 JSON 和 HTML 接口上提供一致的错误响应，同时针对外部服务故障保持优雅降级。

## 全局错误处理架构

核心错误处理框架利用 Slim 的 `ErrorMiddleware` 以及自定义的 `AppErrorHandler` 实现，后者继承自基础 `ErrorHandler` 类。此自定义处理器的激活取决于调试配置标志，以确保在开发和生产环境之间拥有适当的错误可见性。

错误中间件的注册发生在应用程序引导期间，其中调试模式配置控制着错误详情的暴露。当配置中启用了 `debug` 时，自定义的 `AppErrorHandler` 会通过 callable 解析器和响应工厂进行适当的依赖注入实例化。

来源：[public/index.php](public/index.php#L45-L54), [config.default.php](config.default.php#L65)

## 内容协商与响应格式化

系统采用复杂的内容协商机制，以适当的格式传递错误响应。`AppErrorHandler` 和 `ResponseFormatter` 共享相同的内容类型检测逻辑，优先考虑显式查询参数而非 HTTP Accept 请求头。

内容类型的确定遵循以下优先级顺序：

  1. URL 参数 `json=true` 强制输出 JSON
  2. URL 参数 `type=json` 强制输出 JSON，`type=html` 强制输出 HTML
  3. 包含 `application/json` 的 HTTP Accept 请求头触发 JSON
  4. 默认回退到 HTML



这种三层方法确保开发人员可以在测试期间显式控制响应格式，同时为生产客户端保持 RESTful 语义。

来源：[src/App/AppErrorHandler.php](src/App/AppErrorHandler.php#L12-L24), [src/App/ResponseFormatter.php](src/App/ResponseFormatter.php#L21-L41)

## 异常类型映射

系统将特定的异常类型映射到适当的 HTTP 状态码，并对 Minecraft 服务器连接故障进行专门处理。`AppErrorHandler` 重写了 `determineStatusCode()` 方法，将 Minecraft 特定的异常转换为标准的 HTTP 状态码。

| 异常类型                      | HTTP 状态码                  | 基本原理        |
|---------------------------|---------------------------|-------------|
| `MinecraftPingException`  | 502 Bad Gateway           | 指示上游服务器连接失败 |
| `MinecraftQueryException` | 502 Bad Gateway           | 指示查询服务不可用   |
| 其他异常                      | 500 Internal Server Error | 默认的服务器端错误处理 |



这种映射确保客户端可以通过标准的 HTTP 状态码区分服务器故障和上游服务不可用的情况。

来源：[src/App/AppErrorHandler.php](src/App/AppErrorHandler.php#L26-L38)

## 服务器连接错误处理

服务器监控端点根据请求的响应格式实施双重错误处理策略。对于标准 API 端点（JSON/HTML），异常通过全局错误处理程序传播。然而，横幅生成端点实现了本地异常处理，以便即使在服务器故障期间也能保持 PNG 输出格式。

横幅端点专门捕获 `MinecraftPingException` 并生成错误横幅，而不是允许异常传播。这种方法确保基于图像的集成无论服务器可用性如何都能收到有效的 PNG 响应，错误消息直接嵌入在生成的图像中。

来源：[public/server.php](public/server.php#L175-L214), [src/McModUtils/Server.php](src/McModUtils/Server.php#L70-L94)

## 文件系统操作错误处理

Mod 解析和文件服务操作对文件系统访问失败实施立即抛出异常，允许这些错误被全局错误处理程序捕获。`Mod` 类构造函数在初始化期间验证文件是否存在，并为缺失的文件抛出异常，从而防止无效状态在应用程序中传播。

对于文件下载，系统为缺失的文件抛出 `HttpNotFoundException`，该异常被 Slim 的错误中间件捕获并转换为适当的 404 响应。这种方法在所有文件访问操作中提供一致的错误处理，同时保持业务逻辑和错误呈现之间的关注点分离。

来源：[src/McModUtils/Mod.php](src/McModUtils/Mod.php#L26-L32), [public/mods.php](public/mods.php#L48-L51)

横幅生成的双重错误处理模式——在图像格式本地捕获异常，而在 API 格式允许异常传播——展示了一种在不同错误场景中维护响应格式契约的复杂方法。这种模式对于需要严格遵守内容类型的集成特别有价值。 

## 调试模式配置

错误处理行为通过 `config.default.php` 中的 `debug` 配置参数进行全局控制。在生产环境中设置为 `false` 时，系统使用 Slim 的默认错误处理程序，该处理程序会抑制详细的错误信息。在开发环境中设置为 `true` 时，自定义的 `AppErrorHandler` 提供完整的异常详细信息，包括堆栈跟踪。

此配置应谨慎管理：

  * 开发环境：`debug = true` 用于详细的错误诊断
  * 生产环境：`debug = false` 以防止信息泄露



配置在引导期间通过默认和本地配置文件的合并加载，允许特定环境的覆盖而无需修改核心配置。

来源：[bootstrap.php](bootstrap.php#L10-L13), [public/index.php](public/index.php#L45-L46)

## 错误响应结构

JSON 错误响应遵循 Slim 的标准格式，包含结构化的错误信息。当启用调试模式时，`JsonErrorRenderer` 生成包含消息、文件、行和类型信息的一致错误对象。对于生产环境，响应仅限于通用错误消息，以防止信息泄露。

HTML 错误响应使用 `HtmlErrorRenderer`，它提供具有适当样式的人类可读错误页面。`ResponseFormatter` 中的回退 HTML 格式提供了递归的数组到列表的转换，用于在未请求 JSON 时显示结构化数据。

来源：[src/App/AppErrorHandler.php](src/App/AppErrorHandler.php#L51-L63), [src/App/ResponseFormatter.php](src/App/ResponseFormatter.php#L56-L67)

## 依赖配置的错误处理

几种错误处理行为依赖于正确的配置设置。服务器连接的配置缺失或无效会导致通过标准异常流处理的连接错误。多服务器配置系统在尝试连接之前验证服务器 ID 的存在，并对无效的服务器标识符抛出异常。

对于 ZIP 存档操作，系统实现了对文件系统故障的优雅处理。当 ZIP 生成失败或文件丢失时，返回适当的 HTTP 状态码（404）和描述性错误消息，而不是抛出未处理的异常。

来源：[src/McModUtils/Server.php](src/McModUtils/Server.php#L18-L21), [public/mods.php](public/mods.php#L116-L120)

## 生产部署注意事项

对于使用 Nginx 作为反向代理的生产部署，错误处理扩展到 PHP 应用程序层之外。Nginx 配置应优雅地处理上游连接故障，并为长时间运行的操作（如 ZIP 生成）提供适当的超时。有关 Nginx 错误处理配置的详细指导，请参阅 [使用 Nginx 进行生产部署 ](5-production-deployment-with-nginx.md) 文档。

在实现自定义错误处理程序时，始终应考虑消费客户端的响应格式要求。横幅生成端点的本地异常处理演示了即使在错误条件下也维护响应格式契约的重要性——这是 API 设计的一个关键考虑因素。 
