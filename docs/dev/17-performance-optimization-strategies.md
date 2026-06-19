# 性能优化策略

本页详细介绍了 php-minecraft-server-info 项目中实施的全面性能优化架构。该系统采用多层缓存策略，显著减少了 I/O 操作和 CPU 密集型解析任务，特别是在处理大型 Minecraft 模组集合和 zip 归档文件时。

## 三层缓存架构

项目实现了复杂的三层缓存系统，在每一层逐步消除昂贵的操作。该架构基于以下观察：在生产环境中，直接读取和解析 zip 文件与提供预生成的 JSON 内容相比，时间差异显著。

第一层检查预先存在的 JSON 缓存文件，当缓存有效时完全消除文件系统扫描。第二层使用基于哈希的比对来验证内容更改，避免在文件未更改时进行不必要的重新解析。第三层仅在绝对必要时执行昂贵的 zip 归档解析，并将结果立即序列化以供将来请求使用。

## 基于内容哈希的缓存失效

系统使用 SHA256 哈希来实现强大的缓存失效机制，该机制不仅考虑文件内容，还考虑文件元数据。`Mods` 类通过对每个文件的三个关键属性进行哈希运算来实现这一点：相对路径、修改时间和文件大小。

来源：[src/McModUtils/Mods.php](src/McModUtils/Mods.php#L60-L75), [src/McModUtils/Mods.php](src/McModUtils/Mods.php#L107-L112)

PHP

``` $hashComponents[] = $relativePath . '|' . $mtime . '|' . $size; $hash = hash('sha256', implode("\n", $hashComponents)); ``` 

这种方法提供了几个优点：与完整内容哈希相比，它的计算成本较低；它能检测文件的添加、删除和修改；并且通过在哈希生成前进行排序来保持一致的顺序。生成的哈希值同时存储在缓存文件名和缓存负载中，以便进行验证。

系统分别为元数据配置（路径、忽略前缀）和实际文件内容生成单独的哈希。这种双重哈希方法确保了即使在没有文件修改的情况下更改过滤器设置，缓存也能保持有效。

## 基于文件系统的 JSON 缓存

项目有意选择文件系统 JSON 缓存而不是数据库存储来保存模组信息。这一架构决策基于性能基准测试，表明读取 JSON 文件并重新解析比直接读取 zip 文件和渲染要快得多。

来源：[README.md](README.md#L11-L16), [src/McModUtils/Mods.php](src/McModUtils/Mods.php#L114-L145)

缓存文件结构包括三个关键组件：

  * `folder_hashed`：当前文件夹状态的 SHA256 哈希，用于验证
  * `update_at`：ISO 8601 时间戳，用于跟踪缓存新鲜度
  * `mods`：Mod 对象的序列化数组，包含完整的解析元数据



缓存文件存储在 `/public/static/` 中，文件名遵循 `mods-{metaHash}.json` 模式，其中 `metaHash` 是配置参数的 MD5 哈希（包括路径、ignorePrefixs、onlyPrefixs）。这允许同时为不同的过滤器配置共存多个缓存。

## 延迟加载和延迟初始化

`Mods` 类实现了延迟加载模式，将昂贵的操作推迟到绝对必要时再执行。`analyzeModsFolder()` 方法不在对象构造期间调用，而是仅在调用 `getHashed()`、`getModNames()` 或 `getModPaths()` 等方法时触发。

来源：[src/McModUtils/Mods.php](src/McModUtils/Mods.php#L117-L145), [src/McModUtils/Mods.php](src/McModUtils/Mods.php#L148-L156)

PHP

```php public function getHashed(): string { if (!isset($this->hashed)) { $this->analyzeModsFolder(); } return $this->hashed; } ``` 

这种模式在实例化对象但不需要立即使用数据时减少了不必要的文件系统扫描。该类还通过 `resetCache()` 提供缓存失效功能，当配置更改时清除计算的哈希和路径映射。

## Zip 归档优化

`Zip` 类实现了基于哈希的验证，以避免不必要的归档重新生成。系统将文件夹内容哈希存储为归档注释，允许在不解压 zip 文件的情况下进行快速验证。

来源：[src/McModUtils/Zip.php](src/McModUtils/Zip.php#L24-L35), [public/mods.php](public/mods.php#L105-L112)

PHP

``` $folderHash = $modsUtil->getHashed(); $zipedHash = $zip->getZipComment(); if ($isForce || (!empty($folderHash) && $folderHash !== $zipedHash)) { $zip->zipFolder($baseModsPath, $filePaths, $folderHash); } ``` 

`zipFolder()` 方法使用 `ZipArchive::CREATE | ZipArchive::OVERWRITE` 标志来高效替换归档。在重新生成归档时，系统使用 `SplFileInfo` 对象进行优化的文件元数据访问，并且每个文件只计算一次相对路径。

## 静态文件服务与 PHP 回退

项目实现了双路径文件服务策略，以 Nginx 静态服务为主路径，PHP 为回退机制。这种架构在保持可靠性的同时提供了显著的性能改进。

来源：[README.md](README.md#L17-L22), [public/mods.php](public/mods.php#L52-L65)

### 性能对比表

| 服务方法       | 响应时间       | 实现方式                         | 使用场景            |
|------------|------------|------------------------------|-----------------|
| Nginx 直接服务 | 186-191 ms | 无 PHP 的静态文件服务                | 已配置 Nginx 的生产环境 |
| PHP 下载     | 463-482 ms | 带 headers 的 PHP `readfile()` | Nginx 配置不可用时的回退 |



PHP 回退包含特定的 headers 以标识服务方法（`X-Served-By: PHP`），并正确处理文件元数据，包括 content-type、content-disposition 和 content-length headers。这确保了即使静态服务配置失败也能保持兼容性。

## 服务器查询优化

`Server` 类实现了对 Minecraft 服务器查询结果的缓存，以最大限度地减少网络延迟。`fetchPing()` 方法执行实际查询，而 `outputPing()` 在有缓存可用时提供缓存结果。

来源：[src/McModUtils/Server.php](src/McModUtils/Server.php#L52-L70), [public/server.php](public/server.php#L45-L57)

PHP

```php public function outputPing(): array { if (empty($this->pingData)) { return $this->fetchPing(); } return $this->pingData; } ``` 

系统使用 `xPaw\MinecraftPing` 库进行高效的服务器通信，并在 `finally` 块中进行适当的资源清理。连接参数（主机、端口、查询端口）可根据每个服务器实例进行配置，支持多服务器环境而无需重复解析配置。

## 内存优化技术

项目实施了几个内存优化策略，对于处理大型模组集合和 zip 归档至关重要：

  * **顺序文件处理** ：`analyzeModsFolder()` 方法通过 `RecursiveIteratorIterator` 顺序处理文件，而不会同时将所有文件内容加载到内存中
  * **受控序列化** ：Mod 对象仅在写入缓存时才序列化，在运行时保持正常的对象状态
  * **Zip 归档流式传输** ：`Zip` 类使用 `ZipArchive` 的流式传输功能，而不是将整个归档内容加载到内存中



来源：[src/McModUtils/Mods.php](src/McModUtils/Mods.php#L47-L80), [config.default.php](config.default.php#L9-L18)

配置文件指定了增加的内存限制（`2048M`）和执行时间（`90s`）以安全处理大型 zip 操作，但由于这些优化模式，实际运行时内存使用量保持较低。

## 基于配置的性能调优

系统提供了直接影响性能特征的配置选项：

  * **前缀过滤** ：`serverside_prefixs`、`ignore_serverside_prefix` 和 `only_serverside_prefix` 参数减少了需要处理的文件数量
  * **路径配置** ：为通用、客户端和服务器模组分别设置路径，允许进行有针对性的扫描，而不是完整的目录遍历
  * **多服务器支持** ：预配置的服务器定义避免了运行时 DNS 查找和连接测试



来源：[config.default.php](config.default.php#L9-L35), [src/McModUtils/Mods.php](src/McModUtils/Mods.php#L24-L40)

## 性能监控和验证

系统包括内置的性能监控功能，特别是在服务器横幅生成端点中，该端点测量并显示 ping 持续时间：

来源：[public/server.php](public/server.php#L143-L151)

PHP

``` $startPing = microtime(true); // Server queries... $endPing = microtime(true); $durationPing = ($endPing - $startPing) * 1000; ``` 

此测量结果被纳入生成的横幅图像中，提供实时的性能可见性。缓存失效时间戳（`update_at`）允许监控缓存新鲜度和重新生成频率。

## 高级部署策略

为了在生产环境中获得最大性能，项目支持高级部署配置：

  * **基于 Webhook 的更新** ：使用 composer 优化（`--no-dev --optimize-autoloader`）进行自动部署
  * **静态内容生成** ：通过 `npm run doc` 生成的 API 文档作为静态文件提供服务
  * **文件系统权限** ：优化的组权限（`chmod g+s`）用于高效的多用户访问



来源：[README.md](README.md#L90-L135)

使用 Nginx 静态文件服务时，请确保回退 PHP 路由保持功能，以处理静态配置可能不可用或不正确的边缘情况。
