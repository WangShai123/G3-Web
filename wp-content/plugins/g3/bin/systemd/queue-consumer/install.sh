#!/bin/bash

# G3 Queue Worker Systemd Installation Script
# Systemd 安装和配置脚本

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 日志函数
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# 检查是否为root用户
check_root() {
    if [[ $EUID -ne 0 ]]; then
        log_error "This script must be run as root"
        exit 1
    fi
}

# 检测操作系统和systemd
detect_system() {
    if [[ -f /etc/os-release ]]; then
        . /etc/os-release
        OS=$NAME
        VER=$VERSION_ID
    else
        log_error "Cannot detect operating system"
        exit 1
    fi
    
    # 检查systemd是否可用
    if ! command -v systemctl &> /dev/null; then
        log_error "systemd is not available on this system"
        exit 1
    fi
    
    log_info "Detected OS: $OS $VER"
    log_info "systemd version: $(systemctl --version | head -n1)"
}

# 检查依赖
check_dependencies() {
    log_info "Checking dependencies..."
    
    # 检查PHP
    if ! command -v php &> /dev/null; then
        log_error "PHP is not installed"
        exit 1
    fi
    
    # 检查MySQL/MariaDB
    if ! systemctl is-active --quiet mysql && ! systemctl is-active --quiet mariadb; then
        log_warning "MySQL/MariaDB service is not running"
        log_info "Please ensure database service is properly configured"
    fi
    
    log_success "Dependencies check completed"
}

# 创建必要的目录和用户
create_system_resources() {
    log_info "Creating system resources..."
    
    # 确保www-data用户存在
    if ! id "www-data" &>/dev/null; then
        log_info "Creating www-data user..."
        useradd -r -s /bin/false www-data
    fi
    
    # 创建日志目录
    mkdir -p /www/wwwlogs/g3/consumer
    chown www-data:www-data /www/wwwlogs/g3/consumer
    chmod 755 /www/wwwlogs/g3/consumer
    
    # 创建运行时目录
    mkdir -p /run/g3-queue
    chown www-data:www-data /run/g3-queue
    chmod 755 /run/g3-queue
    
    log_success "System resources created successfully"
}

# 复制和配置服务文件
install_service_files() {
    log_info "Installing systemd service files..."
    
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    
    # 复制模板服务文件
    cp "$SCRIPT_DIR/g3-queue-worker@.service" /etc/systemd/system/

    # 设置权限
    chmod 644 /etc/systemd/system/g3-queue-worker@.service
    chown root:root /etc/systemd/system/g3-queue-worker@.service
    
    log_success "Service file installed successfully"
}

# 更新配置文件中的路径
update_service_paths() {
    log_info "Updating service file paths..."
    
    # 获取WordPress根目录
    read -p "Enter WordPress root directory [/var/www/html]: " WP_ROOT
    WP_ROOT=${WP_ROOT:-/var/www/html}
    
    # 获取G3插件路径
    G3_PATH="$WP_ROOT/wp-content/plugins/g3"
    
    if [[ ! -d "$G3_PATH" ]]; then
        log_error "G3 plugin directory not found: $G3_PATH"
        exit 1
    fi
    
    if [[ ! -f "$G3_PATH/bin/queue-worker.php" ]]; then
        log_error "queue-worker.php not found: $G3_PATH/bin/queue-worker.php"
        exit 1
    fi
    
    # 更新服务文件中的路径
    sed -i "s|WorkingDirectory=/var/www/html|WorkingDirectory=$WP_ROOT|g" /etc/systemd/system/g3-queue-worker@.service
    sed -i "s|/var/www/html/wp-content/plugins/g3/bin/queue-worker.php|$G3_PATH/bin/queue-worker.php|g" /etc/systemd/system/g3-queue-worker@.service
    
    # 更新可写路径
    sed -i "s|ReadWritePaths=/var/www/html/wp-content/uploads|ReadWritePaths=$WP_ROOT/wp-content/uploads|g" /etc/systemd/system/g3-queue-worker@.service
    
    log_success "Service paths updated successfully"
}

# 重新加载systemd配置
reload_systemd() {
    log_info "Reloading systemd configuration..."
    
    systemctl daemon-reload
    
    log_success "systemd configuration reloaded successfully"
}

# 启用服务
enable_services() {
    log_info "Enabling G3 Queue Worker services..."
    
    # 启用默认队列服务
    systemctl enable g3-queue-worker@default.service
    systemctl start g3-queue-worker@default.service
    log_success "Default queue service enabled and started"
    
    # 询问是否启用高优先级队列
    read -p "Enable high priority queue service? [y/N]: " ENABLE_HIGH
    if [[ $ENABLE_HIGH =~ ^[Yy]$ ]]; then
        systemctl enable g3-queue-worker@high.service
        systemctl start g3-queue-worker@high.service
        log_success "High priority queue service enabled and started"
    fi
}

# 启动服务
start_services() {
    log_info "Starting G3 Queue Worker services..."
    
    # 启动默认队列
    systemctl start g3-queue-worker@default.service
    
    # 启动已启用的其他服务
    if systemctl is-enabled --quiet g3-queue-worker@high.service 2>/dev/null; then
        systemctl start g3-queue-worker@high.service
    fi
    
    log_success "Services started successfully"
}

# 显示服务状态
show_status() {
    log_info "Current service status:"
    echo
    systemctl status g3-queue-worker@default.service --no-pager -l
    
    if systemctl is-enabled --quiet g3-queue-worker@high.service 2>/dev/null; then
        echo
        systemctl status g3-queue-worker@high.service --no-pager -l
    fi
}

# 创建管理脚本
create_management_script() {
    log_info "Creating management script..."
    
    cat > /usr/local/bin/g3-queue-manage << 'EOF'
#!/bin/bash

# G3 Queue Worker Management Script for systemd

show_usage() {
    echo "Usage: $0 {start|stop|restart|status|logs|enable|disable|reload}"
    echo
    echo "Commands:"
    echo "  start    - Start all enabled G3 queue workers"
    echo "  stop     - Stop all G3 queue workers"
    echo "  restart  - Restart all enabled G3 queue workers"
    echo "  status   - Show status of all G3 queue workers"
    echo "  logs     - Show recent logs for all workers"
    echo "  enable   - Enable and start a specific queue worker"
    echo "  disable  - Disable and stop a specific queue worker"
    echo "  reload   - Reload systemd configuration"
    echo
    echo "Examples:"
    echo "  $0 enable email    - Enable email queue worker"
    echo "  $0 disable high    - Disable high priority queue worker"
    echo "  $0 logs default    - Show logs for default queue worker"
}

get_services() {
    systemctl list-unit-files | grep "g3-queue-worker" | awk '{print $1}'
}

case "$1" in
    start)
        echo "Starting G3 Queue Workers..."
        for service in $(get_services); do
            if systemctl is-enabled --quiet "$service"; then
                systemctl start "$service"
                echo "Started: $service"
            fi
        done
        ;;
    stop)
        echo "Stopping G3 Queue Workers..."
        for service in $(get_services); do
            if systemctl is-active --quiet "$service"; then
                systemctl stop "$service"
                echo "Stopped: $service"
            fi
        done
        ;;
    restart)
        echo "Restarting G3 Queue Workers..."
        for service in $(get_services); do
            if systemctl is-enabled --quiet "$service"; then
                systemctl restart "$service"
                echo "Restarted: $service"
            fi
        done
        ;;
    status)
        echo "G3 Queue Workers Status:"
        echo "========================"
        for service in $(get_services); do
            echo
            systemctl status "$service" --no-pager -l
        done
        ;;
    logs)
        if [[ -n "$2" ]]; then
            service="g3-queue-worker-$2.service"
            if systemctl list-unit-files | grep -q "$service"; then
                journalctl -u "$service" -n 50 --no-pager
            else
                echo "Service not found: $service"
                exit 1
            fi
        else
            echo "Recent logs for all G3 Queue Workers:"
            echo "===================================="
            for service in $(get_services); do
                echo
                echo "=== $service ==="
                journalctl -u "$service" -n 20 --no-pager
            done
        fi
        ;;
    enable)
        if [[ -n "$2" ]]; then
            service="g3-queue-worker-$2.service"
            if systemctl list-unit-files | grep -q "$service"; then
                systemctl enable "$service"
                systemctl start "$service"
                echo "Enabled and started: $service"
            else
                echo "Service not found: $service"
                exit 1
            fi
        else
            echo "Please specify queue type: default, high, email"
            exit 1
        fi
        ;;
    disable)
        if [[ -n "$2" ]]; then
            service="g3-queue-worker-$2.service"
            if systemctl list-unit-files | grep -q "$service"; then
                systemctl stop "$service"
                systemctl disable "$service"
                echo "Stopped and disabled: $service"
            else
                echo "Service not found: $service"
                exit 1
            fi
        else
            echo "Please specify queue type: default, high, email"
            exit 1
        fi
        ;;
    reload)
        echo "Reloading systemd configuration..."
        systemctl daemon-reload
        echo "Configuration reloaded"
        ;;
    *)
        show_usage
        exit 1
        ;;
esac
EOF

    chmod +x /usr/local/bin/g3-queue-manage
    
    log_success "Management script created at /usr/local/bin/g3-queue-manage"
}

# 创建日志轮转配置
create_logrotate_config() {
    log_info "Creating logrotate configuration..."
    
    cat > /etc/logrotate.d/g3-queue << 'EOF'
/www/wwwlogs/g3/consumer/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        systemctl reload g3-queue-worker-default.service >/dev/null 2>&1 || true
        systemctl reload g3-queue-worker-high.service >/dev/null 2>&1 || true
        systemctl reload g3-queue-worker-email.service >/dev/null 2>&1 || true
    endscript
}
EOF
    
    log_success "Logrotate configuration created"
}

# 创建监控脚本
create_monitoring_script() {
    log_info "Creating monitoring script..."
    
    cat > /usr/local/bin/g3-queue-monitor << 'EOF'
#!/bin/bash

# G3 Queue Worker Monitoring Script

check_service() {
    local service=$1
    if systemctl is-enabled --quiet "$service" 2>/dev/null; then
        if systemctl is-active --quiet "$service"; then
            echo "✓ $service: Running"
        else
            echo "✗ $service: Stopped (should be running)"
            return 1
        fi
    else
        echo "- $service: Disabled"
    fi
    return 0
}

check_logs_for_errors() {
    local service=$1
    local errors=$(journalctl -u "$service" --since "1 hour ago" -p err --no-pager -q | wc -l)
    if [[ $errors -gt 0 ]]; then
        echo "⚠ $service: $errors errors in the last hour"
        return 1
    fi
    return 0
}

main() {
    echo "G3 Queue Worker Health Check"
    echo "============================"
    echo "Timestamp: $(date)"
    echo
    
    local failed=0
    local services=(
        "g3-queue-worker-default.service"
        "g3-queue-worker-high.service"
        "g3-queue-worker-email.service"
    )
    
    echo "Service Status:"
    for service in "${services[@]}"; do
        if ! check_service "$service"; then
            failed=$((failed + 1))
        fi
    done
    
    echo
    echo "Error Check (last hour):"
    for service in "${services[@]}"; do
        if systemctl is-enabled --quiet "$service" 2>/dev/null; then
            if ! check_logs_for_errors "$service"; then
                failed=$((failed + 1))
            fi
        fi
    done
    
    echo
    if [[ $failed -eq 0 ]]; then
        echo "✓ All checks passed"
        exit 0
    else
        echo "✗ $failed issues found"
        exit 1
    fi
}

main "$@"
EOF

    chmod +x /usr/local/bin/g3-queue-monitor
    
    log_success "Monitoring script created at /usr/local/bin/g3-queue-monitor"
}

# 主函数
main() {
    log_info "Starting G3 Queue Worker systemd installation..."
    
    check_root
    detect_system
    check_dependencies
    create_system_resources
    install_service_files
    update_service_paths
    reload_systemd
    enable_services
    start_services
    create_management_script
    create_logrotate_config
    create_monitoring_script
    show_status
    
    log_success "Installation completed successfully!"
    echo
    log_info "Management commands:"
    echo "  g3-queue-manage start      - Start all workers"
    echo "  g3-queue-manage stop       - Stop all workers"
    echo "  g3-queue-manage restart    - Restart all workers"
    echo "  g3-queue-manage status     - Show worker status"
    echo "  g3-queue-manage logs       - Show recent logs"
    echo "  g3-queue-manage enable     - Enable specific worker"
    echo "  g3-queue-manage disable    - Disable specific worker"
    echo
    log_info "Monitoring command:"
    echo "  g3-queue-monitor           - Health check all workers"
    echo
    log_info "systemd commands:"
    echo "  systemctl status g3-queue-worker-default.service"
    echo "  journalctl -u g3-queue-worker-default.service -f"
}

# 运行主函数
main "$@"