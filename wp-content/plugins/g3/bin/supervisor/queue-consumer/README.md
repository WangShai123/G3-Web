# G3 Queue Worker - Supervisor 配置

生产环境中使用 Supervisor 管理 G3 队列工作进程的最优配置方案。

## 文件结构

```
supervisor/
├── README.md                    # 本文档
├── install.sh                   # 自动安装脚本
└── g3-queue-worker.conf         # 统一配置文件（支持多队列）
```

## 特性

- **统一配置**：一个配置文件管理所有队列类型
- **双队列支持**：默认队列处理所有任务，高优先级队列处理紧急任务
- **自动扩展**：支持多进程处理
- **生产就绪**：优化的安全和性能配置

## 队列配置

| 队列类型 | 进程数 | 睡眠间隔 | 重试次数 | 超时时间 | 优先级 | 用途 |
|----------|--------|----------|----------|----------|--------|------|
| default  | 2      | 3秒      | 3次      | 60秒     | 100    | 处理所有类型任务（包括邮件） |
| high     | 1      | 1秒      | 5次      | 60秒     | 200    | 处理高优先级紧急任务 |

## 快速开始

### 自动安装

```bash
cd /path/to/wp-content/plugins/g3/bin/supervisor
sudo ./install.sh
```

### 管理命令

```bash
# 启动所有队列
sudo g3-queue-manage start

# 停止所有队列
sudo g3-queue-manage stop

# 查看状态
g3-queue-manage status

# 查看日志
g3-queue-manage logs
```

## 手动安装

如需手动安装：

```bash
# 1. 安装 Supervisor
sudo apt-get install supervisor  # Ubuntu/Debian
sudo yum install supervisor      # CentOS/RHEL

# 2. 复制配置文件
sudo cp g3-queue-worker.conf /etc/supervisor/conf.d/

# 3. 更新路径（替换为实际路径）
sudo sed -i 's|/var/www/html|/your/wordpress/path|g' /etc/supervisor/conf.d/g3-queue-worker.conf

# 4. 重新加载配置
sudo supervisorctl reread
sudo supervisorctl update

# 5. 启动服务
sudo supervisorctl start g3-queue-workers:*
```

## 监控和管理

### Supervisor 命令

```bash
# 查看所有进程状态
sudo supervisorctl status

# 启动特定队列组
sudo supervisorctl start g3-queue-workers:*

# 停止特定队列
sudo supervisorctl stop g3-queue-default:*

# 重启所有队列
sudo supervisorctl restart g3-queue-workers:*

# 查看日志
sudo supervisorctl tail g3-queue-default
```

### 日志文件

```bash
# 标准输出日志
tail -f /var/log/supervisor/g3-queue-default.log
tail -f /var/log/supervisor/g3-queue-high.log

# 错误日志
tail -f /var/log/supervisor/g3-queue-default-error.log
tail -f /var/log/supervisor/g3-queue-high-error.log
```

## 配置调优

### 调整进程数量

编辑 `/etc/supervisor/conf.d/g3-queue-worker.conf`：

```ini
# 增加默认队列进程数
[program:g3-queue-default]
numprocs=4  # 从 2 改为 4

# 增加高优先级队列进程数
[program:g3-queue-high]
numprocs=2  # 从 1 改为 2
```

### 调整资源限制

```ini
# 在每个 [program:*] 段落中添加
environment=PHP_MEMORY_LIMIT="512M"
```

## 故障排除

### 常见问题

1. **服务启动失败**
   ```bash
   sudo supervisorctl tail g3-queue-default stderr
   ```

2. **权限问题**
   ```bash
   sudo chown www-data:www-data /var/www/html/wp-content/plugins/g3/bin/queue-worker.php
   sudo chmod +x /var/www/html/wp-content/plugins/g3/bin/queue-worker.php
   ```

3. **配置语法错误**
   ```bash
   sudo supervisorctl reread
   ```

### Web 界面

启用 Supervisor Web 界面（可选）：

```ini
# 在 /etc/supervisor/supervisord.conf 中添加
[inet_http_server]
port=127.0.0.1:9001
username=admin
password=your_password
```

访问：http://your-server:9001

## 性能优化

- **默认队列**：处理所有常规任务（包括邮件发送），2个进程平衡负载
- **高优先级队列**：处理紧急任务，1秒检查间隔，快速响应

## 安全配置

- 以 `www-data` 用户运行
- 限制文件权限 (umask=022)
- 独立的日志文件
- 自动重启机制

这个配置方案在中小规模网站中表现优异，提供了可靠性和性能的最佳平衡。