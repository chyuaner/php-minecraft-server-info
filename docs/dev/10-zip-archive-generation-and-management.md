# Zip 归档生成和管理

Zip 压缩包生成与管理系统为 Minecraft 服务器资源提供了智能、具备缓存感知能力的压缩功能。该模块通过按需创建 zip 压缩包并基于哈希值的失效机制，实现了模组集合和配置文件夹的高效分发，在最小化服务器负载的同时，确保了服务器-客户端同步场景中的内容完整性和一致性。

## 架构概览

zip 生成系统通过统一的 `Zip` 工具类运行，该类实现了两种互补的压缩策略：用于结构化模组目录的**文件夹相对压缩** （folder-relative compression），以及用于异构文件集合的**路径相对压缩** （path-relative compression）。系统通过将内容验证哈希直接存储在 zip 压缩包注释中，与缓存层深度集成，从而实现快速的失效检测，无需冗余的文件系统扫描或昂贵的重新压缩操作。

架构设计将压缩关注点与内容分析分离：`Mods` 和 `Folder` 类处理文件发现和哈希生成，而 `Zip` 类专注于通过 zip 注释进行压缩包创建和基于哈希的验证。这种分离使得每个组件可以独立地进行优化和测试。

来源: [Zip.php](src/McModUtils/Zip.php#L1-L117), [Mods.php](src/McModUtils/Mods.php#L1-L200), [Folder.php](src/McModUtils/Folder.php#L1-L200)

## 核心压缩策略

系统实现了两种针对不同用例优化的不同压缩算法。每种策略都针对被归档内容类型的特定需求，如路径保留、目录结构维护和性能特征。

### 文件夹相对压缩

`zipFolder()` 方法主要用于压缩模组集合，同时保留其内部目录层次结构。该方法的工作原理是接受一个基础目录路径和一个绝对文件路径数组，然后通过解析基础路径的字符串操作自动计算相对路径。

该实现使用 PHP 的 `SplFileInfo` 来区分文件和目录，创建相应的 zip 条目：目录使用 `addEmptyDir()`，文件则使用带有计算出的相对路径的 `addFile()`。压缩标志 `ZipArchive::CREATE | ZipArchive::OVERWRITE` 确保每次操作都生成全新的压缩包。

`zipFolder()` 方法使用 `realpath()` 执行路径规范化，并通过 `substr($filePath, $sourceLen)` 计算相对路径，其中 `$sourceLen = strlen($source) + 1`。这确保了跨平台兼容性以及在不同操作系统环境中一致的目录结构保留。

来源: [Zip.php](src/McModUtils/Zip.php#L43-L67)

### 路径相对压缩

`zipRelativePath()` 方法为需要自定义相对路径映射的异构文件集合提供了最大的灵活性。该方法接受一个关联数组，其中键表示绝对文件系统路径，值指定压缩包内所需的相对路径，从而允许在压缩过程中任意重组文件结构。

该算法通过在 `$addedDirs` 数组中跟踪已创建的目录来实现复杂的目录管理，以防止重复的 `addEmptyDir()` 操作。对于每个文件，它确保父目录存在，通过在添加文件条目之前递归创建缺失的目录组件。路径规范化通过将 Windows 反斜杠转换为正斜杠并移除前导斜杠来处理。

`zipRelativePath()` 方法包含一个关键优化：仅在必要时创建父目录，并且根据 `$addedDirs` 跟踪数组验证每个目录路径组件。这防止了冗余的目录条目，并减少了深层目录结构的压缩包大小。

来源: [Zip.php](src/McModUtils/Zip.php#L69-L117)

## 基于哈希的缓存验证

zip 生成系统通过直接存储在 zip 压缩包注释中的基于哈希的失效机制实现了智能缓存。这种方法无需昂贵的文件系统扫描或哈希重新计算即可快速检测内容变化，显著提高了重复下载请求的响应时间。

### 内容哈希生成

`Mods` 和 `Folder` 类都通过为每个跟踪的文件连接文件元数据组件来生成 SHA-256 内容哈希。哈希算法结合了每个文件条目的相对路径、修改时间和文件大小，然后在哈希之前对组件数组进行排序，以确保无论文件系统迭代顺序如何，哈希值都是确定性的。

`Mods` 类在哈希生成期间结合了额外的过滤逻辑，遵守服务器端前缀配置（`ignore_serverside_prefix` 和 `only_serverside_prefix`），以确保哈希值仅反映应包含在压缩包中的文件。`Folder` 类应用类似的逻辑，但通过结合单个文件夹哈希来支持多文件夹聚合。

来源: [Mods.php](src/McModUtils/Mods.php#L78-L120), [Folder.php](src/McModUtils/Folder.php#L100-L159)

### 压缩包注释存储和验证

`getZipComment()` 方法从现有 zip 压缩包的注释字段中检索存储的哈希，作为验证参考。在压缩包生成请求期间，系统将当前内容哈希与存储的压缩包注释哈希进行比较，仅当值不同或显式设置 `force` 查询参数时才重新生成压缩包。

这种验证模式同样出现在 mods zip 端点（`/mods/zip`）和其他文件 zip 端点（`/ofolder/zip`）中，为不同的内容类型创建了一致的失效策略。压缩包注释字段提供了一个随 zip 文件本身移动的持久存储机制，使得在服务器重启和部署周期内都能进行验证。

来源: [Zip.php](src/McModUtils/Zip.php#L28-L41), [mods.php](public/mods.php#L137-L149), [other_files.php](public/other_files.php#L143-L161)

## API 端点

系统通过两个主要的 API 端点暴露 zip 生成功能，每个端点都针对特定的内容类型和使用模式进行了优化。两个端点共享通用的验证逻辑，但在压缩策略和哈希生成方法上有所不同。

### 模组集合压缩包

mods zip 端点（`/mods/zip`、`/client-mods/zip`、`/server-mods/zip`）根据服务器端前缀配置生成包含已过滤模组集合的压缩包。这些端点使用 `zipFolder()` 压缩方法，并支持 `force` 查询参数以绕过缓存验证。

端点实现遵循以下工作流：初始化 `Mods` 工具，应用服务器端过滤配置，生成当前内容哈希，与压缩包存储的注释哈希进行比较，必要时重新生成，然后使用适当的 HTTP 标头（包括支持国际字符的 UTF-8 编码文件名）提供文件服务。

**配置映射** ：

| Endpoint           | Config Key | Server-Side Filter               |
|--------------------|------------|----------------------------------|
| `/mods/zip`        | `common`   | `ignore_serverside_prefix: true` |
| `/client-mods/zip` | `client`   | `ignore_serverside_prefix: true` |
| `/server-mods/zip` | `server`   | `only_serverside_prefix: true`   |



来源: [mods.php](public/mods.php#L73-L168), [config.default.php](config.default.php#L11-L32)

### 配置文件夹压缩包

其他文件 zip 端点（`/ofolder/zip`）使用 `zipRelativePath()` 压缩方法将多个异构文件夹聚合到单个压缩包中。该端点结合了 `other_folders` 配置中定义的所有文件夹，创建一个统一的压缩包，其中每个文件夹在压缩包根目录内保持其目录结构。

此端点的哈希生成通过使用管道分隔符连接单个文件夹内容哈希，然后计算组合字符串的 MD5 哈希来生成组合哈希。这种方法确保对任何受监控文件夹的更改都会使整个压缩包失效，从而在整个配置集之间保持一致性。

**受监控的文件夹** （默认配置）：

| Folder Path                 | URL Path                  | Description                |
|-----------------------------|---------------------------|----------------------------|
| `minecraft/config`          | `/files/config/`          | Mod configuration files    |
| `minecraft/defaultconfigs`  | `/files/defaultconfigs/`  | Default mod configurations |
| `minecraft/kubejs`          | `/files/kubejs/`          | KubeJS scripts             |
| `minecraft/modernfix`       | `/files/modernfix/`       | ModernFix configs          |
| `minecraft/resourcepacks`   | `/files/resourcepacks/`   | Resource packs             |
| `minecraft/tacz`            | `/files/tacz/`            | TACZ configs               |
| `minecraft/tlm_custom_pack` | `/files/tlm_custom_pack/` | Custom pack configs        |



来源: [other_files.php](public/other_files.php#L104-L168), [config.default.php](config.default.php#L34-L40)

## 压缩包文件命名和存储

系统根据修改时间戳和配置元数据生成确定性的 zip 压缩包文件名，从而实现可预测的文件识别，并防止不同配置场景下的缓存冲突。

### 文件命名约定

压缩包文件名遵循以下模式：模组压缩包为 `BarianMcMods整合包{configKey}-{timestamp}.zip`，配置文件夹压缩包为 `BarianMcMods整合包(other-all)-{timestamp}.zip`。时间戳使用 Asia/Taipei 时区的 `Ymd-Hi`（年月日-小时分钟）格式，提供可读的日期信息，同时保持可排序的时间顺序。

该实现通过 UTF-8 编码正确处理国际化文件名，使用 RFC 5987 编码设置 `filename` 和 `filename*` HTTP 标头，以确保跨浏览器兼容性以及非 ASCII 字符的正确显示。

来源: [mods.php](public/mods.php#L154-L162), [other_files.php](public/other_files.php#L163-L171)

### 存储位置

压缩包存储在 `public/static/` 目录中，文件名包含配置键的元数据哈希，以区分不同的过滤配置。对于模组压缩包，模式为 `mods-{configKey}.zip`，对于文件夹压缩包，为 `folder-{metaHash}.zip`。这种方法使得可以根据不同的过滤配置同时缓存多个压缩包变体。

存储位置在 `Mods` 和 `Folder` 类中都定义为常量 `CACHE_PATH`，指向 `BASE_PATH.'/public/static/'`，确保了整个代码库的一致性，并实现了缓存压缩包的集中清理和管理。

来源: [Mods.php](src/McModUtils/Mods.php#L13), [Folder.php](src/McModUtils/Folder.php#L13), [mods.php](public/mods.php#L129), [other_files.php](public/other_files.php#L137)

## 性能考虑

zip 生成系统结合了几种性能优化，以最小化服务器负载并最大化压缩包下载请求的响应吞吐量。这些优化侧重于避免不必要的重新计算和利用高效的压缩策略。

### 缓存命中优化

当缓存的压缩包通过哈希验证时，系统直接提供现有文件，而无需调用 PHP 压缩操作，从而将 CPU 利用率和响应延迟降低到接近零的水平。与 zip 压缩包生成相比，哈希比较操作本身的成本可以忽略不计，因此缓存命中极其高效。

缓存失效阈值设置为内容哈希不匹配，而不是基于时间的过期，确保只要基础内容保持不变，压缩包就会无限期地缓存。这种方法对于模组集合不常更改的 Minecraft 服务器环境特别有效。

来源: [mods.php](public/mods.php#L137-L149), [other_files.php](public/other_files.php#L143-L161)

### 压缩效率

`zipRelativePath()` 方法的目录跟踪优化减少了压缩包中冗余的目录条目，根据目录深度和广度，可能会将深层目录结构的压缩包大小减少 5-15%。使用 `ZipArchive::OVERWRITE` 标志确保压缩包生成不会产生附加到现有文件的开销，可能会通过新的压缩分析提高压缩率。

对于超过 500 个文件的大型模组集合，请考虑使用临时文件名前缀实现异步后台压缩包生成，以避免在长时间的压缩操作期间阻塞请求线程。当前的同步设计可能会在共享主机环境中生成超大压缩包时导致超时问题。

来源: [Zip.php](src/McModUtils/Zip.php#L43-L117)

## 配置集成

zip 生成系统通过几个控制文件发现、过滤行为和 URL 路径映射的关键设置与应用程序配置集成。了解这些配置选项对于自定义压缩包生成行为至关重要。

### 模组配置

`mods` 配置数组定义了三种主要的集合类型：`common`、`client` 和 `server`。每个条目指定物理文件系统路径、下载链接的 URL 路径，以及两个控制服务器端模组过滤行为的布尔标志。

  * **`ignore_serverside_prefix`** ：启用后，从压缩包中排除匹配 `serverside_prefixs` 模式的文件。用于客户端和通用模组集合。
  * **`only_serverside_prefix`** ：启用后，仅包含匹配 `serverside_prefixs` 模式的文件。用于服务器端模组集合。
  * **`serverside_prefixs`** ：全局数组，定义指示服务器端模组的文件名前缀（默认：`['serveronly_', 'server_']`）。



来源: [config.default.php](config.default.php#L9-L32)

### 其他文件夹配置

`other_folders` 配置是一个将物理文件系统路径映射到 URL 路径前缀的关联数组。每个键值对定义要包含在组合压缩包中的文件夹，其中键指定绝对文件系统路径，值指定单个文件下载的 URL 路径。

此配置允许通过简单的配置文件更新灵活地添加新文件夹到监控系统，从而支持动态服务器配置，而无需更改代码。

来源: [config.default.php](config.default.php#L34-L40)

## 后续步骤

  * 有关为压缩包提供元数据的模组解析系统的详细信息，请参阅 [模组解析系统 (NeoForge, Forge, Fabric) ](7-mod-parsing-system-neoforge-forge-fabric.md)
  * 要了解与 zip 生成配合使用的缓存机制，请参阅 [缓存机制和缓存失效 ](9-caching-mechanism-and-cache-invalidation.md)
  * 有关利用 zip 生成的 API 端点的详细信息，请探索 [模组信息 API ](11-mod-information-apis.md) 和 [文件服务 API ](13-file-serving-apis.md)


