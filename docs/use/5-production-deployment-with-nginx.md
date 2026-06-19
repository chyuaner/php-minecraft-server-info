# 使用 Nginx 进行生产环境部署

本指南提供了全面的部署策略，用于配置 php-minecraft-server-info 应用程序在生产环境中使用 Nginx 作为 Web 服务器。该部署重点关注性能优化、安全加固以及正确处理静态文件交付，以最大程度减少 PHP 开销。

## 架构概览

该应用程序遵循 PHP-Slim 框架架构，其中 Nginx 充当反向代理和静态文件服务器。了解请求流程对于实现最佳部署性能至关重要。

该部署架构利用了 Nginx 卓越的静态文件服务能力，同时将动态内容处理委托给 PHP-FPM。这种混合方法在 README 的性能基准测试中得到了验证，结果显示 Nginx 提供文件服务的速度约为 185-277ms，相比之下 PHP 每次文件下载约为 463-482ms。位于 `public/static/` 的静态目录存储生成的 zip 存档，以避免重复的压缩操作，而 `public/mods.php` 中的回退机制确保即使 Nginx 静态服务失败也能保持功能。  
来源: [README.md](README.md#L200-L256), [public/mods.php](public/mods.php#L1-L200), [config.default.php](config.default.php#L1-L67)

## 系统先决条件

在部署之前，请确保您的环境满足系统要求。该应用程序已在多个 Linux 发行版上进行了测试，并需要特定的 PHP 扩展和配置调整。

### 操作系统支持

| 发行版                            | 已测试的 PHP 版本 | 状态    |
|--------------------------------|-------------|-------|
| Linux 6.14.0-2-rt3-MANJARO     | PHP 8.4.7   | ✅ 已验证 |
| Debian GNU/Linux 12 (bookworm) | PHP 8.2.28  | ✅ 已验证 |
| Debian GNU/Linux 13 (trixie)   | PHP 8.4.11  | ✅ 已验证 |



### 必需的 PHP 扩展

该应用程序需要两个 PHP 扩展来实现核心功能。GD 扩展支持在 `public/server.php#L144-L151` 中生成服务器横幅图片，而 zip 扩展处理 `public/mods.php#L60-L65` 和 `public/other_files.php#L104-L113` 中的压缩存档操作。

**扩展配置** (`/etc/php/{version}/fpm/php.ini`):

INI

``` extension=gd extension=zip max_execution_time = 90 memory_limit = 2048M ``` 

这些配置调整是为了适应大型 mod 文件夹扫描操作和 zip 文件生成。修改配置后，请重新加载 PHP-FPM：`sudo systemctl reload php8.4-fpm.service`。  
来源: [README.md](README.md#L14-L34), [public/server.php](public/server.php#L144-L151), [public/mods.php](public/mods.php#L60-L65)

## 安装步骤

生产环境安装遵循一个结构化过程，该过程在 `/opt/minecraft/` 目录中设置应用程序，这与 `config.default.php` 中的默认配置路径一致。

**分步安装** ：

BASH

```bash sudo apt install php composer php-zip php-gd nodejs npm # 导航到部署目录 cd /opt/minecraft/ # 克隆仓库 git clone <repository-url> cd php-minecraft-mods-info # 安装 PHP 依赖项 composer install # 安装用于文档生成的 Node.js 依赖项 npm install # 从模板创建配置文件 cp config.default.php config.php vim config.php # 根据您的环境进行配置 # 配置 Web 服务器用户的权限 sudo gpasswd -a www-data minecraft sudo chgrp minecraft -R /opt/minecraft/php-minecraft-mods-info sudo chmod g+s -R /opt/minecraft/php-minecraft-mods-info # 重启 PHP-FPM 以应用更改 sudo systemctl restart php8.4-fpm.service ``` 

权限设置确保 Web 服务器用户 可以访问 Minecraft 服务器文件并在 `public/static/` 目录中生成 zip 存档。设置组 ID 位 (`chmod g+s`) 确保新文件继承组权限。  
来源: [README.md](README.md#L67-L84), [config.default.php](config.default.php#L1-L67)

## Nginx 配置

Nginx 配置对于生产环境部署至关重要。该配置必须处理 PHP 处理和静态文件服务，并具有适当的回退机制。

### 完整的 Nginx 服务器块

NGINX

``` server { listen 80; listen [::]:80; server_name your-domain.com; root /opt/minecraft/php-minecraft-mods-info/public; index index.php; # 安全头 add_header X-Frame-Options "SAMEORIGIN" always; add_header X-Content-Type-Options "nosniff" always; add_header X-XSS-Protection "1; mode=block" always; # 主入口点 - Slim 框架路由 location / { try_files $uri $uri/ /index.php?$query_string; } # 带有 PHP 回退的静态文件服务 location /files/ { alias /opt/minecraft/mc-server/; # 安全：防止目录列表 autoindex off; # 首先尝试通过 Nginx 直接提供服务 try_files $uri /index.php?$query_string; # 用于跨域请求的 CORS 头 add_header Access-Control-Allow-Origin "*"; add_header Cache-Control "public, max-age=31536000"; } # 用于动态内容的 PHP 处理 location ~ \\.php$ { include fastcgi_params; fastcgi_split_path_info ^(.+\\.php)(/.+)$; fastcgi_pass unix:/var/run/php/php8.4-fpm.sock; fastcgi_index index.php; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; fastcgi_param PATH_INFO $fastcgi_path_info; # 性能调优 fastcgi_buffer_size 128k; fastcgi_buffers 256 16k; fastcgi_busy_buffers_size 256k; fastcgi_temp_file_write_size 256k; fastcgi_read_timeout 120; } # 拒绝访问隐藏文件 location ~ /\\. { deny all; access_log off; log_not_found off; } # 日志记录 access_log /var/log/nginx/minecraft-api-access.log; error_log /var/log/nginx/minecraft-api-error.log; } ``` 

### 配置组件说明

| 配置块                | 目的                                 | 性能影响                                         |
|--------------------|------------------------------------|----------------------------------------------|
| `location /`       | 通过 `index.php` 将所有非文件请求路由到 Slim 框架 | 确保所有 API 端点（`/mods`、`/ping`、`/banner`）都被正确处理 |
| `location /files/` | 直接从 Minecraft 服务器目录提供 mod 文件和配置    | **高影响** \- 将每个文件的响应时间从 ~463ms 减少到 ~185ms     |
| `try_files $uri`   | 在回退到 PHP 之前尝试 Nginx 直接提供服务         | 对性能优化至关重要                                    |
| `fastcgi_buffers`  | 增加大型 JSON 响应的缓冲区大小                 | 防止大型 mod 列表出现超时错误                            |
| `Cache-Control`    | 缓存静态文件 1 年                         | 减少冗余文件传输                                     |



`/files/` 位置块映射到 `config.default.php#L15-L39` 中配置的路径，包括 `mods_path`、客户端 mods 以及 config、kubejs 和 resourcepacks 等其他文件夹。`try_files` 指令确保如果 Nginx 找不到文件（可能是由于路径配置问题），请求将回退到 `public/mods.php#L24-L32` 中的 PHP 处理程序，后者提供 `X-Served-By: PHP` 头用于调试。  
来源: [public/index.php](public/index.php#L1-L78), [config.default.php](config.default.php#L15-L39), [public/mods.php](public/mods.php#L24-L32), [README.md](README.md#L200-L256)

### SSL/TLS 配置（推荐）

对于生产环境部署，请使用 Let's Encrypt 启用 HTTPS：

NGINX

``` server { listen 443 ssl http2; listen [::]:443 ssl http2; server_name your-domain.com; ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem; ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem; ssl_protocols TLSv1.2 TLSv1.3; ssl_ciphers HIGH:!aNULL:!MD5; ssl_prefer_server_ciphers on; # 在此处包含所有之前的位置块 } # HTTP 到 HTTPS 重定向 server { listen 80; listen [::]:80; server_name your-domain.com; return 301 https://$server_name$request_uri; } ``` 

## 配置自定义

安装完成后，自定义 `config.php` 以匹配您的环境。默认配置提供了一个模板，应针对生产环境进行修改。

### 关键配置参数

PHP

``` return [ // 更新您的基础 URL 'base_url' => 'https://your-domain.com', // Minecraft 服务器路径（确保 www-data 具有读取权限） 'mods_path' => '/opt/minecraft/mc-server/mods', // 服务器连接参数 'minecraft_public_hoststring' => 'mc.your-domain.com', 'minecraft_host' => '127.0.0.1', 'minecraft_port' => 25565, // 生产环境：设置为 false 'debug' => false, ]; ``` 

`base_url` 设置用于在 `public/mods.php#L122-L125` 中构建下载 URL。通过 `minecraft_servers` 数组支持多服务器配置，允许从单个 API 部署监控多个 Minecraft 实例。  
来源: [config.default.php](config.default.php#L1-L67), [public/mods.php](public/mods.php#L122-L125), [public/index.php](public/index.php#L39-L50)

### 多服务器配置示例

PHP

``` 'minecraft_servers' => [ 'survival' => [ 'name' => 'Survival World', 'public_hoststring' => 'mc.domain.com /server survival', 'host' => '127.0.0.1', 'port' => 25565, 'qport' => 25565, ], 'creative' => [ 'name' => 'Creative Mode', 'public_hoststring' => 'mc.domain.com /server creative', 'host' => '127.0.0.1', 'port' => 25566, 'qport' => 25566, ], ], ``` 

此配置启用了服务器特定的端点，如 `/ping/survival`、`/banner/creative` 和 `/online-players/survival`，如 `public/server.php#L47-L58` 中的路由处理程序所定义。  
来源: [config.default.php](config.default.php#L44-L66), [public/server.php](public/server.php#L47-L58)

## 性能优化

生产环境部署需要性能优化策略来处理并发请求并最大限度地减少延迟。

### 静态文件缓存策略

该应用程序在 `public/static/` 中生成 zip 存档以缓存聚合的 mod 包。存档名称包含配置哈希，以确保在文件夹内容更改时缓存失效。

`public/mods.php#L66-L84` 和 `public/other_files.php#L82-L102` 中的 zip 生成逻辑使用文件夹内容哈希来确定缓存的有效性。这可以防止文件未更改时不必要的重新压缩，从而显著降低 CPU 负载和后续请求的响应时间。

### Nginx 缓存头

NGINX

``` # 添加到 location /files/ 块 location /files/ { expires 1y; add_header Cache-Control "public, immutable"; etag on; } ``` 

### PHP-FPM 优化

配置 `/etc/php/8.4/fpm/pool.d/www.conf`：

INI

``` # 进程管理器设置 pm = dynamic pm.max_children = 50 pm.start_servers = 10 pm.min_spare_servers = 5 pm.max_spare_servers = 20 pm.max_requests = 500 # 性能调优 request_terminate_timeout = 120 slowlog = /var/log/php8.4-fpm-slow.log request_slowlog_timeout = 10s ``` 

这些设置处理 mod 扫描操作和服务器查询的并发性质。应根据可用的 RAM 调整 `pm.max_children`（对于此应用程序，每个子进程大约 50-100MB）。  
来源: [public/mods.php](public/mods.php#L66-L84), [public/other_files.php](public/other_files.php#L82-L102)

## 使用 Webhook 进行自动部署

使用 Webhook 集成自动化部署和更新。此设置允许在将更新推送到仓库时实现零停机部署。

### Webhook 服务配置

创建 `/etc/systemd/system/webhook.service`：

INI

``` [Unit] Description=Webhook server for php-minecraft-server-info [Service] Type=exec ExecStart=webhook -hooks /etc/webhook/hooks.json -verbose User=www-data Group=www-data [Install] WantedBy=multi-user.target ``` 

### Webhook Hook 配置

创建 `/etc/webhook/hooks.json`：

JSON

``` [ { "id": "php-minecraft-server-info", "execute-command": "/opt/minecraft/webhook/deploy-php-minecraft-server-info.sh", "command-working-directory": "/opt/minecraft/php-minecraft-server-info" } ] ``` 

### 部署脚本

创建 `/opt/minecraft/webhook/deploy-php-minecraft-server-info.sh`：

BASH

```bash #!/bin/bash set -e cd /opt/minecraft/php-minecraft-server-info echo "▶ [DEPLOY] Starting deploy at $(date)" # 配置 SSH 用于 git 操作 export GIT_SSH_COMMAND="ssh -i /opt/minecraft/ssh/id_ed25519 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null" # 拉取最新代码 git pull origin master # 更新 Composer 依赖项 composer install --no-dev --optimize-autoloader # 更新 Node.js 依赖项 npm install # 生成 API 文档 npm run doc echo "✅ [DEPLOY] Done at $(date)" ``` 

启用 webhook 服务：

BASH

``` # 设置权限 sudo mkdir /var/www/.npm sudo chown -R 33:33 "/var/www/.npm" sudo mkdir /etc/webhook # 启动并启用 webhook 服务 sudo systemctl start webhook sudo systemctl enable webhook # 监控日志 sudo journalctl -u webhook.service -f ``` 

此部署工作流程确保依赖项已更新，自动加载器已针对生产环境进行优化，并且 API 文档与代码更改保持同步。  
来源: [README.md](README.md#L86-L176)

## 安全注意事项

生产环境部署需要安全加固以保护敏感的 Minecraft 服务器信息并防止未经授权的访问。

### 文件访问控制

确保对敏感文件具有适当的权限：

BASH

``` # 限制配置文件访问 sudo chmod 600 /opt/minecraft/php-minecraft-mods-info/config.php # 确保日志不可公开访问 sudo chmod 640 /var/log/nginx/minecraft-api-*.log sudo chown www-data:adm /var/log/nginx/minecraft-api-*.log ``` 

### 速率限制

将速率限制添加到 Nginx 配置以防止滥用：

NGINX

``` # 添加到 http 块 limit_req_zone $binary_remote_addr zone=api_limit:10m rate=10r/s; # 添加到 server 块 location / { limit_req zone=api_limit burst=20 nodelay; try_files $uri $uri/ /index.php?$query_string; } ``` 

### 调试模式生产环境检查

通过检查 `public/index.php#L39-L50` 中的错误中间件配置，验证生产环境中是否已禁用调试模式。当 `$GLOBALS['config']['debug']` 为 false 时，错误处理程序应使用默认的生产环境处理程序。  
来源: [public/index.php](public/index.php#L39-L50)

## 故障排除

### 常见问题和解决方案

| 症状                   | 原因                | 解决方案                                      |
|----------------------|-------------------|-------------------------------------------|
| `/files/*` 出现 404 错误 | Nginx 别名路径不匹配     | 验证 `alias` 路径是否与 `config.php` 路径匹配；检查文件权限 |
| API 响应缓慢             | PHP-FPM 工作进程耗尽    | 在池配置中增加 `pm.max_children`                 |
| Zip 生成失败             | 内存/磁盘空间不足         | 在 php.ini 中增加 `memory_limit`；检查磁盘空间       |
| 服务器查询超时              | Minecraft 服务器无法访问 | 验证 `minecraft_host` 和端口设置；检查防火墙规则         |



### 性能测试

测试部署性能：

BASH

``` # 测试 API 端点响应时间 ab -n 100 -c 10 https://your-domain.com/mods # 测试静态文件下载速度 ab -n 100 -c 10 https://your-domain.com/files/mods/example-mod.jar # 检查 PHP-FPM 状态 systemctl status php8.4-fpm ``` 

生产环境部署的预期性能基准：

  * API JSON 响应：典型 mod 列表 <200ms
  * 静态文件下载：每个文件 185-277ms（通过 Nginx）
  * PHP 提供的文件：每个文件 463-482ms（回退模式）
  * Zip 存档生成：取决于文件夹大小，后续请求缓存



## 监控和维护

### 日志监控

监控应用程序日志以查找错误和性能问题：

BASH

``` # Nginx 访问日志（用于 API 使用模式） tail -f /var/log/nginx/minecraft-api-access.log # Nginx 错误日志（用于配置问题） tail -f /var/log/nginx/minecraft-api-error.log # PHP-FPM 慢日志（用于性能瓶颈） tail -f /var/log/php8.4-fpm-slow.log # Webhook 部署日志 journalctl -u webhook.service -f ``` 

### 缓存管理

监控和管理 `public/static/` 中生成的 zip 存档：

BASH

``` # 检查 zip 文件大小和期限 ls -lh /opt/minecraft/php-minecraft-mods-info/public/static/ # 清理旧的 zip 文件（超过 30 天） find /opt/minecraft/php-minecraft-mods-info/public/static/ -name "*.zip" -mtime +30 -delete ``` 

### 无数据库缓存

该应用程序使用基于文件的缓存而不是数据库。缓存文件存储在系统的临时目录中，并由 `src/McModUtils/Folder.php` 中的 `Folder` 类管理。监控磁盘使用情况以确保缓存不会消耗过多空间。

## 后续步骤

完成 Nginx 部署配置后，请继续了解核心应用程序架构：

  * 要详细了解系统设计原则，请阅读 **[项目架构和设计原则 ](6-project-architecture-and-design-principles.md)**
  * 要实现多 Minecraft 服务器监控，请查看 **[多服务器配置 ](15-multi-server-configuration.md)**
  * 要进一步优化应用程序性能，请探索 **[性能优化策略 ](17-performance-optimization-strategies.md)**
  * 要了解 mod 文件如何解析和服务，请参考 **[Mod 解析系统 ](7-mod-parsing-system-neoforge-forge-fabric.md)**
  * 要在生产环境中实现自动错误处理，请参阅 **[错误处理和异常管理 ](16-error-handling-and-exception-management.md)**


