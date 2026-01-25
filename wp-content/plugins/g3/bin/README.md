# G3 队列消费者 (Consumer) 文档

G3 提供了完整的队列消费者解决方案，支持多种运行模式和部署方式。无论是开发环境的简单测试，还是生产环境的高可用部署，都能找到合适的消费者配置。

## 🚀 概述

G3 队列消费者是处理异步任务的核心组件，支持以下运行模式：

- **CLI 模式** - 命令行直接运行，适合开发和测试
- **Cron 模式** - WordPress Cron 自动调度，适合中小型网站
- **Supervisor 模式** - 进程管理器监控，适合中等规模部署
- **systemd 模式** - 系统服务管理，适合企业级部署

## 📁 文件结构

```
bin/
├── README.md                    # 本文档
├── console.php                  # Symfony Console 应用
├── queue-worker.php             # 队列工作器脚本
├── queue-manager.php            # 队列管理脚本
├── start-worker.sh              # 启动脚本
├── supervisor/                  # Supervisor 配置
│   └── queue-consumer/
│       ├── g3-queue-worker.conf # Supervisor 配置文件
│       ├── install.sh           # 自动安装脚本
│       └── README.md            # Supervisor 专用文档
└── systemd/                     # systemd 配置
    └── queue-consumer/
        ├── g3-queue-worker@.service # systemd 模板服务
        ├── install.sh           # 自动安装脚本
        ├── manage.sh            # 管理脚本
        └── README.md            # systemd 专用文档
```

## 🛠️ 核心脚本

### 1. queue-worker.php - 队列工作器

队列工作器是消费者的核心，负责从队列中取出任务并执行。

#### 基本用法

```bash
# 基本运行
php queue-worker.php

# 守护进程模式
php queue-worker.php --daemon --verbose

# 处理完队列后停止
php queue-worker.php --stop-when-empty --verbose

# 指定队列和参数
php queue-worker.php --queue=high --sleep=1 --tries=5 --verbose
```

#### 命令行选项

| 选项 | 默认值 | 说明 |
|------|--------|------|
| `--queue=default` | default | 指定队列名称 |
| `--sleep=3` | 3 | 空队列时的休眠时间（秒） |
| `--tries=3` | 3 | 任务最大重试次数 |
| `--timeout=60` | 60 | 任务执行超时时间（秒） |
| `--daemon` | false | 以守护进程模式运行 |
| `--stop-when-empty` | false | 队列为空时停止 |
| `--verbose` | false | 显示详细输出 |

#### 特性

- **自动重试** - 任务失败时自动重试，支持指数退避
- **优雅关闭** - 支持信号处理，优雅关闭进程
- **内存管理** - 智能内存管理，避免内存泄漏
- **错误处理** - 完善的错误处理和日志记录
- **自动清理** - 定期清理过期和已完成的任务

### 2. queue-manager.php - 队列管理器

队列管理器提供队列状态查看、清理等管理功能。

#### 基本用法

```bash
# 查看队列状态
php queue-manager.php status

# 查看驱动信息
php queue-manager.php info

# 清空队列
php queue-manager.php clear --queue=default

# 清理过期任务
php queue-manager.php cleanup
```

#### 支持的动作

| 动作 | 说明 |
|------|------|
| `status` | 查看队列状态和统计信息 |
| `info` | 查看队列驱动配置信息 |
| `clear` | 清空指定队列的所有任务 |
| `cleanup` | 清理过期和旧任务 |

### 3. start-worker.sh - 启动脚本

便捷的启动脚本，支持多种运行模式。

#### 基本用法

```bash
# 守护进程模式（默认）
./start-worker.sh daemon --queue=default --verbose

# 单次运行模式
./start-worker.sh single --stop-when-empty

# 测试模式
./start-worker.sh test
```

#### 运行模式

| 模式 | 说明 | 适用场景 |
|------|------|----------|
| `daemon` | 守护进程模式，持续运行 | 生产环境 |
| `single` | 单次运行，处理完停止 | 定时任务 |
| `test` | 测试模式，快速处理 | 开发测试 |

## 🔧 部署方案

### 1. 开发环境 - CLI 模式

适合开发和测试环境，简单直接。

```bash
# 进入 bin 目录
cd /path/to/wp-content/plugins/g3/bin

# 直接运行工作器
php queue-worker.php --stop-when-empty --verbose

# 或使用启动脚本
./start-worker.sh single --verbose
```

**优点**：
- 简单易用，无需额外配置
- 适合调试和测试
- 可以随时启动和停止

**缺点**：
- 需要手动管理
- 不适合长期运行
- 无自动重启机制

### 2. 小型网站 - Cron 模式

使用 WordPress Cron 自动调度，适合中小型网站。

```php
// 在队列配置中启用 cron 消费者
$config = [
    'consumer' => 'cron',
    'cron' => [
        'interval_minutes' => 1,    // 每分钟执行一次
        'jobs_per_run' => 10,       // 每次处理10个任务
        'auto_cleanup' => true,     // 自动清理
    ]
];
```

**优点**：
- 无需额外配置
- 与 WordPress 完美集成
- 自动调度执行

**缺点**：
- 依赖 WordPress Cron
- 处理能力有限
- 不适合高并发场景

### 3. 中等规模 - Supervisor 模式

使用 Supervisor 进程管理器，适合中等规模的部署。

```bash
# 自动安装
cd /path/to/wp-content/plugins/g3/bin/supervisor/queue-consumer
sudo ./install.sh

# 手动管理
sudo g3-queue-manage start    # 启动所有队列
sudo g3-queue-manage status   # 查看状态
sudo g3-queue-manage logs     # 查看日志
```

**配置特点**：
- **双队列支持**：default（2进程）+ high（1进程）
- **自动重启**：进程异常时自动重启
- **日志管理**：自动日志轮转
- **Web界面**：可选的Web管理界面

**优点**：
- 进程监控和自动重启
- 支持多进程并发
- 完善的日志管理
- 相对简单的配置

**缺点**：
- 需要安装额外软件
- 资源控制能力有限
- 安全隔离较弱

### 4. 企业级 - systemd 模式

使用 systemd 系统服务，适合企业级部署。

```bash
# 自动安装
cd /path/to/wp-content/plugins/g3/bin/systemd/queue-consumer
sudo ./install.sh

# 使用管理脚本
sudo ./manage.sh enable default  # 启用默认队列
sudo ./manage.sh status          # 查看状态
sudo ./manage.sh health          # 健康检查
```

**配置特点**：
- **模板服务**：一个服务文件支持多队列
- **企业级安全**：完整的安全沙箱
- **资源控制**：精确的CPU和内存限制
- **原生集成**：与系统完美集成

**优点**：
- 企业级安全和资源控制
- 与系统深度集成
- 强大的依赖管理
- 高可靠性

**缺点**：
- 配置相对复杂
- 需要 systemd 支持
- 学习曲线较陡

## 📊 性能对比

| 特性 | CLI | Cron | Supervisor | systemd |
|------|-----|------|------------|---------|
| **易用性** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐ |
| **可靠性** | ⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **性能** | ⭐⭐⭐ | ⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **安全性** | ⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **监控** | ⭐ | ⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **扩展性** | ⭐⭐ | ⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |

## 🎯 选择指南

### 开发环境
```bash
# 推荐：CLI 模式
php queue-worker.php --stop-when-empty --verbose
```

### 个人博客/小型网站
```php
// 推荐：Cron 模式
'consumer' => 'cron'
```

### 中小企业网站
```bash
# 推荐：Supervisor 模式
sudo ./supervisor/queue-consumer/install.sh
```

### 大型企业/高并发
```bash
# 推荐：systemd 模式
sudo ./systemd/queue-consumer/install.sh
```

## 🔍 监控和调试

### 日志查看

#### CLI 模式
```bash
# 直接在终端查看输出
php queue-worker.php --verbose
```

#### Cron 模式
```bash
# 查看 WordPress 错误日志
tail -f /path/to/wp-content/debug.log
```

#### Supervisor 模式
```bash
# 查看 Supervisor 日志
sudo g3-queue-manage logs
tail -f /var/log/supervisor/g3-queue-default.log
```

#### systemd 模式
```bash
# 查看 systemd 日志
./manage.sh logs default
journalctl -u g3-queue-worker@default.service -f
```

### 性能监控

#### 队列状态
```bash
# 查看队列状态
php queue-manager.php status

# 查看驱动信息
php queue-manager.php info
```

#### 系统资源
```bash
# 查看进程资源使用
ps aux | grep queue-worker

# 查看系统负载
top -p $(pgrep -f queue-worker)
```

### 健康检查

#### Supervisor 模式
```bash
# 查看进程状态
sudo supervisorctl status

# 重启异常进程
sudo supervisorctl restart g3-queue-workers:*
```

#### systemd 模式
```bash
# 健康检查
./manage.sh health

# 查看服务状态
systemctl status g3-queue-worker@default.service
```

## 🛡️ 安全配置

### 用户权限
```bash
# 确保以正确用户运行
sudo chown www-data:www-data /path/to/queue-worker.php
sudo chmod +x /path/to/queue-worker.php
```

### 文件权限
```bash
# 设置适当的文件权限
find /path/to/g3/bin -name "*.php" -exec chmod 644 {} \;
find /path/to/g3/bin -name "*.sh" -exec chmod 755 {} \;
```

### 网络安全
- 确保数据库连接安全
- 限制 Redis 访问权限
- 使用防火墙保护服务端口

## 🚨 故障排除

### 常见问题

#### 1. 工作器无法启动
```bash
# 检查 PHP 路径
which php

# 检查文件权限
ls -la queue-worker.php

# 检查 WordPress 配置
php -r "require_once '/path/to/wp-config.php'; echo 'OK';"
```

#### 2. 数据库连接失败
```bash
# 检查数据库服务
systemctl status mysql

# 测试数据库连接
mysql -u username -p -h host database_name
```

#### 3. Redis 连接失败
```bash
# 检查 Redis 服务
systemctl status redis

# 测试 Redis 连接
redis-cli ping
```

#### 4. 内存不足
```bash
# 检查内存使用
free -h

# 调整 PHP 内存限制
php -d memory_limit=512M queue-worker.php
```

### 调试技巧

#### 启用详细日志
```bash
# CLI 模式
php queue-worker.php --verbose

# 修改 PHP 错误报告
php -d error_reporting=E_ALL queue-worker.php
```

#### 单步调试
```bash
# 处理单个任务后停止
php queue-worker.php --stop-when-empty --verbose --tries=1
```

#### 检查队列状态
```bash
# 查看队列详细信息
php queue-manager.php status --queue=default
```

## 📈 性能优化

### 配置优化

#### 调整进程数量
```bash
# Supervisor: 编辑配置文件
sudo vim /etc/supervisor/conf.d/g3-queue-worker.conf
# 修改 numprocs 参数

# systemd: 启动多个实例
sudo systemctl start g3-queue-worker@default.service
sudo systemctl start g3-queue-worker@high.service
```

#### 调整资源限制
```bash
# 设置内存限制
php -d memory_limit=1G queue-worker.php

# 设置执行时间限制
php -d max_execution_time=300 queue-worker.php
```

### 队列优化

#### 使用多队列
```php
// 高优先级任务
Queue::driver()->push('UrgentJob', $data, 0, 'high');

// 普通任务
Queue::driver()->push('NormalJob', $data, 0, 'default');

// 低优先级任务
Queue::driver()->push('LowPriorityJob', $data, 0, 'low');
```

#### 批量处理
```php
// 批量推送任务
for ($i = 0; $i < 100; $i++) {
    Queue::driver()->push('BatchJob', ['item' => $i], 0, 'batch');
}
```

## 🔄 升级和维护

### 定期维护

#### 清理过期任务
```bash
# 手动清理
php queue-manager.php cleanup

# 定时清理（添加到 crontab）
0 2 * * * /usr/bin/php /path/to/queue-manager.php cleanup
```

#### 日志轮转
```bash
# Supervisor 自动轮转
# systemd 使用 journald 自动管理

# 手动清理日志
find /var/log -name "*g3-queue*" -mtime +30 -delete
```

### 版本升级

#### 停止服务
```bash
# Supervisor
sudo g3-queue-manage stop

# systemd
sudo ./manage.sh stop
```

#### 更新代码
```bash
# 更新 G3 插件代码
# 重新加载配置
```

#### 重启服务
```bash
# Supervisor
sudo g3-queue-manage start

# systemd
sudo ./manage.sh start
```

## 📚 最佳实践

### 1. 环境隔离
- 开发环境使用 CLI 模式
- 测试环境使用 Cron 模式
- 生产环境使用 Supervisor 或 systemd

### 2. 监控告警
- 设置进程监控
- 配置错误日志告警
- 监控队列积压情况

### 3. 资源管理
- 合理设置内存限制
- 控制并发进程数量
- 定期清理过期任务

### 4. 安全防护
- 使用专用用户运行
- 限制文件系统访问
- 加密敏感配置信息

---

**G3 队列消费者** - 为您的 WordPress 应用提供强大、可靠的异步任务处理能力！

## 📞 技术支持

- **文档**: 查看完整的 G3 文档
- **问题反馈**: 通过 GitHub Issues 报告问题
- **社区支持**: 加入 G3 开发者社区

---

*最后更新: 2024年*