#!/bin/bash

# G3 Queue Worker 启动脚本
# 
# 这个脚本用于启动G3队列工作器，支持多种运行模式
# 
# 使用方法:
# ./start-worker.sh [mode] [options]
# 
# 模式:
# - daemon: 守护进程模式 (默认)
# - single: 单次运行模式
# - test: 测试模式
# 
# 示例:
# ./start-worker.sh daemon --queue=emails --verbose
# ./start-worker.sh single --stop-when-empty
# ./start-worker.sh test

# 获取脚本所在目录
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WORKER_SCRIPT="$SCRIPT_DIR/queue-worker.php"

# 检查PHP是否可用
if ! command -v php &> /dev/null; then
    echo "错误: PHP 未安装或不在 PATH 中"
    exit 1
fi

# 检查工作器脚本是否存在
if [ ! -f "$WORKER_SCRIPT" ]; then
    echo "错误: 找不到队列工作器脚本: $WORKER_SCRIPT"
    exit 1
fi

# 默认模式
MODE="daemon"

# 如果提供了第一个参数，将其作为模式
if [ $# -gt 0 ] && [[ "$1" =~ ^(daemon|single|test)$ ]]; then
    MODE="$1"
    shift
fi

# 根据模式设置参数
case "$MODE" in
    "daemon")
        echo "启动守护进程模式..."
        ARGS="--daemon --verbose $@"
        ;;
    "single")
        echo "启动单次运行模式..."
        ARGS="--stop-when-empty --verbose $@"
        ;;
    "test")
        echo "启动测试模式..."
        ARGS="--stop-when-empty --verbose --sleep=1 --tries=1 $@"
        ;;
    *)
        echo "未知模式: $MODE"
        echo "支持的模式: daemon, single, test"
        exit 1
        ;;
esac

# 显示启动信息
echo "工作器脚本: $WORKER_SCRIPT"
echo "运行模式: $MODE"
echo "参数: $ARGS"
echo "---"

# 启动工作器
php "$WORKER_SCRIPT" $ARGS