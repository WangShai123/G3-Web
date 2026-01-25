#!/bin/bash

# G3 Queue Worker Supervisor Installation Script
# Supervisor 安装和配置脚本

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

# 检测操作系统
detect_os() {
    if [[ -f /etc/os-release ]]; then
        . /etc/os-release
        OS=$NAME
        VER=$VERSION_ID
    else
        log_error "Cannot detect operating system"
        exit 1
    fi
    log_info "Detected OS: $OS $VER"
}

# 安装Supervisor
install_supervisor() {
    log_info "Installing Supervisor..."
    
    if [[ "$OS" == *"Ubuntu"* ]] || [[ "$OS" == *"Debian"* ]]; then
        apt-get update
        apt-get install -y supervisor
    elif [[ "$OS" == *"CentOS"* ]] || [[ "$OS" == *"Red Hat"* ]] || [[ "$OS" == *"Rocky"* ]]; then
        yum install -y epel-release
        yum install -y supervisor
    elif [[ "$OS" == *"Amazon Linux"* ]]; then
        yum install -y supervisor
    else
        log_error "Unsupported operating system: $OS"
        exit 1
    fi
    
    log_success "Supervisor installed successfully"
}

# 创建必要的目录
create_directories() {
    log_info "Creating necessary directories..."
    
    # 创建日志目录
    mkdir -p /var/log/supervisor
    chown root:root /var/log/supervisor
    chmod 755 /var/log/supervisor
    
    # 创建配置目录
    mkdir -p /etc/supervisor/conf.d
    
    log_success "Directories created successfully"
}

# 复制配置文件
copy_configs() {
    log_info "Copying configuration files..."
    
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    
    # 复制唯一的配置文件
    cp "$SCRIPT_DIR/g3-queue-worker.conf" /etc/supervisor/conf.d/
    
    # 设置权限
    chmod 644 /etc/supervisor/conf.d/g3-queue-worker.conf
    chown root:root /etc/supervisor/conf.d/g3-queue-worker.conf
    
    log_success "Configuration file copied successfully"
}

# 更新配置文件中的路径
update_config_paths() {
    log_info "Updating configuration file paths..."
    
    # 获取WordPress根目录
    read -p "Enter WordPress root directory [/var/www/html]: " WP_ROOT
    WP_ROOT=${WP_ROOT:-/var/www/html}
    
    # 获取G3插件路径
    G3_PATH="$WP_ROOT/wp-content/plugins/g3"
    
    if [[ ! -d "$G3_PATH" ]]; then
        log_error "G3 plugin directory not found: $G3_PATH"
        exit 1
    fi
    
    # 更新配置文件中的路径
    sed -i "s|%(here)s/../queue-worker.php|$G3_PATH/bin/queue-worker.php|g" /etc/supervisor/conf.d/g3-queue-worker.conf
    sed -i "s|directory=/var/www/html|directory=$WP_ROOT|g" /etc/supervisor/conf.d/g3-queue-worker.conf
    
    log_success "Configuration paths updated successfully"
}

# 启动和启用Supervisor
start_supervisor() {
    log_info "Starting and enabling Supervisor..."
    
    # 启动Supervisor服务
    if command -v systemctl &> /dev/null; then
        systemctl enable supervisor
        systemctl start supervisor
        systemctl reload supervisor
    elif command -v service &> /dev/null; then
        service supervisor start
        chkconfig supervisor on
    else
        log_error "Cannot start Supervisor service"
        exit 1
    fi
    
    log_success "Supervisor started and enabled successfully"
}

# 重新加载配置
reload_config() {
    log_info "Reloading Supervisor configuration..."
    
    supervisorctl reread
    supervisorctl update
    
    log_success "Configuration reloaded successfully"
}

# 显示状态
show_status() {
    log_info "Current Supervisor status:"
    supervisorctl status
}

# 创建管理脚本
create_management_script() {
    log_info "Creating management script..."
    
    cat > /usr/local/bin/g3-queue-manage << 'EOF'
#!/bin/bash

# G3 Queue Worker Management Script

case "$1" in
    start)
        echo "Starting G3 Queue Workers..."
        supervisorctl start g3-queue-workers:*
        ;;
    stop)
        echo "Stopping G3 Queue Workers..."
        supervisorctl stop g3-queue-workers:*
        ;;
    restart)
        echo "Restarting G3 Queue Workers..."
        supervisorctl restart g3-queue-workers:*
        ;;
    status)
        echo "G3 Queue Workers Status:"
        supervisorctl status | grep g3-queue
        ;;
    logs)
        echo "Recent logs:"
        tail -n 50 /var/log/supervisor/g3-queue-*.log
        ;;
    errors)
        echo "Recent errors:"
        tail -n 50 /var/log/supervisor/g3-queue-*-error.log
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|status|logs|errors}"
        exit 1
        ;;
esac
EOF

    chmod +x /usr/local/bin/g3-queue-manage
    
    log_success "Management script created at /usr/local/bin/g3-queue-manage"
}

# 主函数
main() {
    log_info "Starting G3 Queue Worker Supervisor installation..."
    
    check_root
    detect_os
    install_supervisor
    create_directories
    copy_configs
    update_config_paths
    start_supervisor
    reload_config
    create_management_script
    show_status
    
    log_success "Installation completed successfully!"
    echo
    log_info "Management commands:"
    echo "  g3-queue-manage start    - Start all workers"
    echo "  g3-queue-manage stop     - Stop all workers"
    echo "  g3-queue-manage restart  - Restart all workers"
    echo "  g3-queue-manage status   - Show worker status"
    echo "  g3-queue-manage logs     - Show recent logs"
    echo "  g3-queue-manage errors   - Show recent errors"
    echo
    log_info "Web interface: http://your-server:9001 (if enabled)"
}

# 运行主函数
main "$@"