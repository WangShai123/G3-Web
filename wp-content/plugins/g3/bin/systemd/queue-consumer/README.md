# G3 Queue Worker - systemd 配置

生产环境中使用 systemd 管理 G3 队列工作进程的最优配置方案。

## 文件结构

```
systemd/
├── README.md                      # 本文档
├── install.sh                     # 自动安装脚本
├── manage.sh                      # 管理脚本
└── g3-queue-worker@.service       # 模板服务文件（支持多队列）
```

## 特性

- **模板服务**：一个服务文件支持多种队列类型
- **动态实例**：按需启动不同队列实例
- **企业级安全**：完整的安全沙箱配置
- **资源控制**：精确的CPU和内存限制
- **原生集成**：与系统完美集成

## 队列类型

| 队列类型 | 用途 | 推荐配置 |
|----------|------|----------|
| default  | 处理所有类型任务（包括邮件） | 基础配置，适合大多数场景 |
| high     | 高优先级紧急任务 | 更快响应，更多资源 |

## 快速开始

### 自动安装

```bash
cd /path/to/wp-content/plugins/g3/bin/systemd
sudo ./install.sh
```

### 管理命令

```bash
# 启动默认队列
sudo systemctl start g3-queue-worker@default.service

# 启用开机自启
sudo systemctl enable g3-queue-worker@default.service

# 查看状态
systemctl status g3-queue-worker@default.service

# 查看日志
journalctl -u g3-queue-worker@default.service -f
```

### 使用管理脚本

```bash
# 启用并启动默认队列
sudo ./manage.sh enable default

# 查看所有队列状态
./manage.sh status

# 健康检查
./manage.sh health

# 查看日志
./manage.sh logs default
```

## 手动安装

如需手动安装：

```bash
# 1. 复制服务文件
sudo cp g3-queue-worker@.service /etc/systemd/system/

# 2. 更新路径（替换为实际路径）
sudo sed -i 's|/var/www/html|/your/wordpress/path|g' /etc/systemd/system/g3-queue-worker@.service

# 3. 重新加载 systemd
sudo systemctl daemon-reload

# 4. 启用并启动默认队列
sudo systemctl enable g3-queue-worker@default.service
sudo systemctl start g3-queue-worker@default.service
```

## 多队列管理

### 启动多个队列

```bash
# 启动默认队列
sudo systemctl start g3-queue-worker@default.service

# 启动高优先级队列
sudo systemctl start g3-queue-worker@high.service
```

### 批量管理

```bash
# 启动所有队列
sudo ./manage.sh start

# 停止所有队列
sudo ./manage.sh stop

# 重启所有队列
sudo ./manage.sh restart
```

## 监控和日志

### 查看服务状态

```bash
# 详细状态
systemctl status g3-queue-worker@default.service

# 简要状态
systemctl is-active g3-queue-worker@default.service

# 是否启用
systemctl is-enabled g3-queue-worker@default.service
```

### 日志管理

```bash
# 实时日志
journalctl -u g3-queue-worker@default.service -f

# 最近日志
journalctl -u g3-queue-worker@default.service -n 100

# 错误日志
journalctl -u g3-queue-worker@default.service -p err

# 特定时间范围
journalctl -u g3-queue-worker@default.service --since "1 hour ago"
```

### 性能监控

```bash
# 资源使用情况
systemctl status g3-queue-worker@default.service

# 系统资源统计
systemd-cgtop

# 进程信息
ps aux | grep queue-worker
```

## 配置调优

### 资源限制调整

```bash
# 编辑服务配置
sudo systemctl edit g3-queue-worker@default.service

# 添加自定义配置
[Service]
MemoryLimit=1G
CPUQuota=100%
```

### 安全配置调整

```bash
# 添加额外的安全配置
[Service]
PrivateTmp=yes
ProtectKernelLogs=yes
ProtectProc=invisible
```

## 故障排除

### 常见问题

1. **服务启动失败**
   ```bash
   journalctl -u g3-queue-worker@default.service -n 50
   systemd-analyze verify /etc/systemd/system/g3-queue-worker@.service
   ```

2. **权限问题**
   ```bash
   sudo chown www-data:www-data /var/www/html/wp-content/plugins/g3/bin/queue-worker.php
   sudo chmod +x /var/www/html/wp-content/plugins/g3/bin/queue-worker.php
   ```

3. **数据库连接问题**
   ```bash
   systemctl status mysql
   sudo -u www-data php -r "require_once '/var/www/html/wp-config.php'; echo 'DB OK';"
   ```

### 调试模式

```bash
# 启用详细日志
sudo systemctl edit g3-queue-worker@default.service

[Service]
ExecStart=
ExecStart=/usr/bin/php /var/www/html/wp-content/plugins/g3/bin/queue-worker.php --daemon --verbose --debug --queue=%i

sudo systemctl daemon-reload
sudo systemctl restart g3-queue-worker@default.service
```

## 安全特性

- **用户隔离**：以 www-data 用户运行
- **文件系统保护**：只读系统，限制写入路径
- **网络限制**：仅允许必要的网络访问
- **系统调用过滤**：限制可用的系统调用
- **内存保护**：禁止执行内存写入
- **权限限制**：禁止获取新权限

## 性能优化

- **资源限制**：512MB 内存，80% CPU 配额
- **重启策略**：智能重启，避免频繁重启
- **信号处理**：优雅关闭，避免数据丢失
- **日志管理**：集成 journald，高效日志处理

## 与 Supervisor 对比

| 特性 | systemd | Supervisor |
|------|---------|------------|
| 系统集成 | ✅ 原生集成 | ❌ 第三方工具 |
| 资源控制 | ✅ 强大的 cgroups | ⚠️ 基本限制 |
| 安全性 | ✅ 企业级安全 | ⚠️ 基本隔离 |
| 日志管理 | ✅ journald 集成 | ⚠️ 独立文件 |
| 依赖管理 | ✅ 原生支持 | ❌ 手动配置 |
| Web 界面 | ❌ 无 | ✅ 内置界面 |
| 学习曲线 | ⚠️ 中等 | ✅ 较低 |

## 推荐使用场景

- **企业环境**：需要严格的安全和资源控制
- **现代系统**：使用 systemd 的 Linux 发行版
- **高可靠性**：需要与系统深度集成
- **资源敏感**：需要精确的资源控制

这个配置方案提供了企业级的可靠性和安全性，适合对稳定性要求较高的生产环境。


## 简易步骤：

1. 配置文件

把 .service 模板文件复制到 /etc/systemd/system/ 目录，并修改文件内路径为你的 WordPress 安装路径。

/etc/systemd/system/g3-queue-worker@.service

2. 关闭 php-cli.init JIT

以 宝塔面板 PHP 8.3 为例
PHP_CLI_INI="/www/server/php/83/etc/php-cli.ini"

```bash
; === 关键：禁用 JIT（仅 CLI）===
opcache.jit=0
; opcache.jit_buffer_size=128M   ← 必须注释或删除！
```

3. 重载

```bash
# 重载 systemd 配置
sudo systemctl daemon-reload

# 启用并启动服务
sudo systemctl enable g3-queue-worker@default.service
sudo systemctl restart g3-queue-worker@default.service
```

4. 验证

```bash
# 查看实时日志
sudo journalctl -u g3-queue-worker@default.service -f

# 检查服务状态
systemctl status g3-queue-worker@default.service
```
