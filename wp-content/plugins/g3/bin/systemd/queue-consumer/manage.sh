#!/bin/bash

# G3 Queue Worker systemd Management Script
# 简化版管理脚本 - 使用模板服务

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

# 预定义的队列类型
QUEUE_TYPES=("default" "high")

# 获取所有G3队列服务实例
get_active_instances() {
    systemctl list-units --type=service --state=active | grep "g3-queue-worker@" | awk '{print $1}' | sort
}

# 获取所有启用的服务实例
get_enabled_instances() {
    for queue in "${QUEUE_TYPES[@]}"; do
        if systemctl is-enabled --quiet "g3-queue-worker@${queue}.service" 2>/dev/null; then
            echo "g3-queue-worker@${queue}.service"
        fi
    done
}

# 显示使用说明
show_usage() {
    echo "G3 Queue Worker systemd Management Script"
    echo "========================================"
    echo
    echo "Usage: $0 <command> [queue_type]"
    echo
    echo "Commands:"
    echo "  start [queue]      - Start all enabled queues or specific queue"
    echo "  stop [queue]       - Stop all active queues or specific queue"
    echo "  restart [queue]    - Restart all enabled queues or specific queue"
    echo "  status [queue]     - Show status of all queues or specific queue"
    echo "  enable <queue>     - Enable and start a specific queue"
    echo "  disable <queue>    - Stop and disable a specific queue"
    echo "  logs [queue]       - Show logs for all queues or specific queue"
    echo "  follow <queue>     - Follow logs for specific queue"
    echo "  list               - List all available queue types and their status"
    echo "  health             - Perform health check on all active queues"
    echo
    echo "Queue Types:"
    echo "  default            - Default priority queue (handles all task types including emails)"
    echo "  high               - High priority queue (for urgent tasks)"
    echo
    echo "Examples:"
    echo "  $0 start                    - Start all enabled queues"
    echo "  $0 enable default           - Enable default queue"
    echo "  $0 logs default             - Show logs for default queue"
    echo "  $0 follow default           - Follow logs for default queue"
}

# 验证队列类型
validate_queue() {
    local queue="$1"
    
    if [[ -z "$queue" ]]; then
        return 0  # 允许空值，表示所有队列
    fi
    
    for valid_queue in "${QUEUE_TYPES[@]}"; do
        if [[ "$queue" == "$valid_queue" ]]; then
            return 0
        fi
    done
    
    log_error "Invalid queue type: $queue"
    log_info "Available queue types: ${QUEUE_TYPES[*]}"
    return 1
}

# 启动服务
start_services() {
    local target_queue="$1"
    
    if ! validate_queue "$target_queue"; then
        return 1
    fi
    
    if [[ -n "$target_queue" ]]; then
        local service="g3-queue-worker@${target_queue}.service"
        log_info "Starting $service..."
        systemctl start "$service"
        log_success "$service started successfully"
    else
        log_info "Starting all enabled G3 queue workers..."
        local started=0
        for service in $(get_enabled_instances); do
            log_info "Starting $service..."
            systemctl start "$service"
            started=$((started + 1))
        done
        
        if [[ $started -eq 0 ]]; then
            log_warning "No enabled services found. Use '$0 enable <queue>' to enable a queue."
        else
            log_success "$started services started successfully"
        fi
    fi
}

# 停止服务
stop_services() {
    local target_queue="$1"
    
    if ! validate_queue "$target_queue"; then
        return 1
    fi
    
    if [[ -n "$target_queue" ]]; then
        local service="g3-queue-worker@${target_queue}.service"
        log_info "Stopping $service..."
        systemctl stop "$service"
        log_success "$service stopped successfully"
    else
        log_info "Stopping all active G3 queue workers..."
        local stopped=0
        for service in $(get_active_instances); do
            log_info "Stopping $service..."
            systemctl stop "$service"
            stopped=$((stopped + 1))
        done
        
        if [[ $stopped -eq 0 ]]; then
            log_warning "No active services found"
        else
            log_success "$stopped services stopped successfully"
        fi
    fi
}

# 重启服务
restart_services() {
    local target_queue="$1"
    
    if ! validate_queue "$target_queue"; then
        return 1
    fi
    
    if [[ -n "$target_queue" ]]; then
        local service="g3-queue-worker@${target_queue}.service"
        log_info "Restarting $service..."
        systemctl restart "$service"
        log_success "$service restarted successfully"
    else
        log_info "Restarting all enabled G3 queue workers..."
        local restarted=0
        for service in $(get_enabled_instances); do
            log_info "Restarting $service..."
            systemctl restart "$service"
            restarted=$((restarted + 1))
        done
        
        if [[ $restarted -eq 0 ]]; then
            log_warning "No enabled services found"
        else
            log_success "$restarted services restarted successfully"
        fi
    fi
}

# 显示服务状态
show_status() {
    local target_queue="$1"
    
    if ! validate_queue "$target_queue"; then
        return 1
    fi
    
    if [[ -n "$target_queue" ]]; then
        local service="g3-queue-worker@${target_queue}.service"
        systemctl status "$service" --no-pager -l
    else
        echo "G3 Queue Workers Status"
        echo "======================"
        
        for queue in "${QUEUE_TYPES[@]}"; do
            local service="g3-queue-worker@${queue}.service"
            echo
            echo "=== $queue Queue ==="
            
            if systemctl is-enabled --quiet "$service" 2>/dev/null; then
                if systemctl is-active --quiet "$service" 2>/dev/null; then
                    echo -e "Status: ${GREEN}Active (Running)${NC}"
                else
                    echo -e "Status: ${RED}Inactive (Stopped)${NC}"
                fi
                echo "Enabled: Yes"
            else
                echo -e "Status: ${YELLOW}Disabled${NC}"
                echo "Enabled: No"
            fi
            
            # 显示简要状态
            systemctl status "$service" --no-pager -l --lines=3 2>/dev/null || true
        done
    fi
}

# 启用服务
enable_service() {
    local queue="$1"
    
    if [[ -z "$queue" ]]; then
        log_error "Please specify queue type: ${QUEUE_TYPES[*]}"
        return 1
    fi
    
    if ! validate_queue "$queue"; then
        return 1
    fi
    
    local service="g3-queue-worker@${queue}.service"
    log_info "Enabling $service..."
    systemctl enable "$service"
    systemctl start "$service"
    log_success "$service enabled and started successfully"
}

# 禁用服务
disable_service() {
    local queue="$1"
    
    if [[ -z "$queue" ]]; then
        log_error "Please specify queue type: ${QUEUE_TYPES[*]}"
        return 1
    fi
    
    if ! validate_queue "$queue"; then
        return 1
    fi
    
    local service="g3-queue-worker@${queue}.service"
    log_info "Disabling $service..."
    systemctl stop "$service"
    systemctl disable "$service"
    log_success "$service stopped and disabled successfully"
}

# 显示日志
show_logs() {
    local target_queue="$1"
    local lines="${2:-50}"
    
    if ! validate_queue "$target_queue"; then
        return 1
    fi
    
    if [[ -n "$target_queue" ]]; then
        local service="g3-queue-worker@${target_queue}.service"
        log_info "Showing last $lines lines of logs for $service:"
        journalctl -u "$service" -n "$lines" --no-pager
    else
        log_info "Showing logs for all G3 queue workers:"
        for queue in "${QUEUE_TYPES[@]}"; do
            local service="g3-queue-worker@${queue}.service"
            if systemctl is-enabled --quiet "$service" 2>/dev/null; then
                echo
                echo "=== $queue Queue ==="
                journalctl -u "$service" -n 20 --no-pager 2>/dev/null || echo "No logs available"
            fi
        done
    fi
}

# 跟踪日志
follow_logs() {
    local queue="$1"
    
    if [[ -z "$queue" ]]; then
        log_error "Please specify queue type: ${QUEUE_TYPES[*]}"
        return 1
    fi
    
    if ! validate_queue "$queue"; then
        return 1
    fi
    
    local service="g3-queue-worker@${queue}.service"
    log_info "Following logs for $service (Press Ctrl+C to stop):"
    journalctl -u "$service" -f
}

# 列出所有服务
list_services() {
    echo "G3 Queue Worker Services"
    echo "======================="
    printf "%-15s %-10s %-10s %-30s\n" "Queue Type" "Enabled" "Status" "Service Name"
    printf "%-15s %-10s %-10s %-30s\n" "----------" "-------" "------" "------------"
    
    for queue in "${QUEUE_TYPES[@]}"; do
        local service="g3-queue-worker@${queue}.service"
        local enabled="No"
        local status="Inactive"
        
        if systemctl is-enabled --quiet "$service" 2>/dev/null; then
            enabled="Yes"
        fi
        
        if systemctl is-active --quiet "$service" 2>/dev/null; then
            status="Active"
        fi
        
        printf "%-15s %-10s %-10s %-30s\n" "$queue" "$enabled" "$status" "$service"
    done
}

# 健康检查
health_check() {
    log_info "Performing health check on G3 queue workers..."
    
    local issues=0
    local total=0
    
    for queue in "${QUEUE_TYPES[@]}"; do
        local service="g3-queue-worker@${queue}.service"
        total=$((total + 1))
        echo
        echo "Checking $queue queue ($service)..."
        
        # 检查服务是否启用
        if systemctl is-enabled --quiet "$service" 2>/dev/null; then
            echo "  ✓ Service is enabled"
            
            # 检查服务是否运行
            if systemctl is-active --quiet "$service" 2>/dev/null; then
                echo "  ✓ Service is running"
                
                # 检查最近的错误
                local errors=$(journalctl -u "$service" --since "1 hour ago" -p err --no-pager -q 2>/dev/null | wc -l)
                if [[ $errors -eq 0 ]]; then
                    echo "  ✓ No errors in the last hour"
                else
                    echo "  ⚠ $errors errors in the last hour"
                    issues=$((issues + 1))
                fi
                
                # 检查内存使用
                local pid=$(systemctl show "$service" --property=MainPID --value)
                if [[ "$pid" != "0" ]] && [[ -n "$pid" ]]; then
                    local memory=$(ps -o rss= -p "$pid" 2>/dev/null | awk '{print int($1/1024)}')
                    if [[ -n "$memory" ]]; then
                        echo "  ✓ Memory usage: ${memory}MB"
                        if [[ $memory -gt 512 ]]; then
                            echo "  ⚠ High memory usage detected"
                            issues=$((issues + 1))
                        fi
                    fi
                fi
            else
                echo "  ✗ Service is not running"
                issues=$((issues + 1))
            fi
        else
            echo "  - Service is disabled"
        fi
    done
    
    echo
    echo "Health Check Summary"
    echo "==================="
    echo "Total queues: $total"
    echo "Issues found: $issues"
    
    if [[ $issues -eq 0 ]]; then
        log_success "All checks passed!"
        return 0
    else
        log_warning "$issues issues found"
        return 1
    fi
}

# 主函数
main() {
    local command="$1"
    local arg1="$2"
    local arg2="$3"
    
    case "$command" in
        start)
            start_services "$arg1"
            ;;
        stop)
            stop_services "$arg1"
            ;;
        restart)
            restart_services "$arg1"
            ;;
        status)
            show_status "$arg1"
            ;;
        enable)
            enable_service "$arg1"
            ;;
        disable)
            disable_service "$arg1"
            ;;
        logs)
            show_logs "$arg1" "$arg2"
            ;;
        follow)
            follow_logs "$arg1"
            ;;
        list)
            list_services
            ;;
        health)
            health_check
            ;;
        *)
            show_usage
            exit 1
            ;;
    esac
}

# 检查是否有足够的权限
if [[ $EUID -ne 0 ]] && [[ "$1" != "status" ]] && [[ "$1" != "logs" ]] && [[ "$1" != "list" ]] && [[ "$1" != "health" ]]; then
    log_error "This command requires root privileges"
    exit 1
fi

# 运行主函数
main "$@"