<?php
/**
 * G3 Queue Manager
 * 
 * G3队列系统的管理脚本，用于查看状态、清理队列等
 * 
 * 使用方法:
 * php queue-manager.php [action] [options]
 * 
 * 动作:
 * - status: 查看队列状态
 * - info: 查看驱动信息
 * - clear: 清空队列
 * 
 * 选项:
 * --queue=default 队列名称
 * 
 * @since 1.0.0
 * @author Wang Shai
 */

// 确保只能通过CLI运行
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.' . PHP_EOL);
}

// 抑制 WordPress 在 CLI 环境中的 HTML 输出
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', false);
}
if (!defined('WP_DEBUG_DISPLAY')) {
    define('WP_DEBUG_DISPLAY', false);
}

// 设置错误报告级别，忽略废弃警告
error_reporting(E_ERROR | E_WARNING | E_PARSE);

/**
 * G3队列管理器类
 */
class G3QueueManager {
    private array $options = [
        'queue' => 'default',
    ];

    private $queue;

    public function __construct()
    {
        $this->parseArguments();
        $this->initializeEnvironment();
    }

    private function parseArguments(): void
    {
        global $argv;

        if (!isset($argv)) {
            return;
        }

        foreach ($argv as $arg) {
            if (strpos($arg, '--') === 0) {
                $parts = explode('=', substr($arg, 2), 2);
                $key   = $parts[0];
                $value = $parts[1] ?? true;

                if (array_key_exists($key, $this->options)) {
                    $this->options[$key] = $value;
                }
            }
        }
    }

    private function initializeEnvironment(): void
    {
        // 查找WordPress根目录
        $wpRoot = $this->findWordPressRoot();
        if (!$wpRoot) {
            die('Could not find WordPress installation.' . PHP_EOL);
        }

        // 加载WordPress配置
        require_once $wpRoot . '/wp-config.php';

        // 定义必要的常量
        if (!defined('WPINC')) {
            define('WPINC', 'wp-includes');
        }

        // 设置 WordPress 环境为 CLI
        if (!defined('WP_CLI')) {
            define('WP_CLI', true);
        }

        // 手动加载必要的WordPress文件
        require_once ABSPATH . WPINC . '/class-wpdb.php';

        // 初始化全局变量
        global $wpdb;
        if (!isset($wpdb)) {
            // 临时抑制错误输出
            $originalErrorReporting = error_reporting(0);

            $wpdb = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);

            // 设置字符集，避免数据库错误
            if (defined('DB_CHARSET') && DB_CHARSET) {
                $wpdb->set_charset($wpdb->dbh, DB_CHARSET);
            }

            // 恢复错误报告
            error_reporting($originalErrorReporting);
        }

        // 加载G3插件的自动加载器
        $g3AutoloadFile = dirname(__DIR__) . '/vendor/autoload.php';
        if (file_exists($g3AutoloadFile)) {
            require_once $g3AutoloadFile;
        }

        // 直接加载队列相关的类
        require_once dirname(__DIR__) . '/src/Queue/QueueInterface.php';
        require_once dirname(__DIR__) . '/src/Queue/Job.php';
        require_once dirname(__DIR__) . '/src/Queue/DatabaseQueue.php';
        require_once dirname(__DIR__) . '/src/Queue/RedisQueue.php';
        require_once dirname(__DIR__) . '/src/Utilities/System.php';
        require_once dirname(__DIR__) . '/src/Queue.php';

        // 加载队列配置
        $configFile = dirname(__DIR__) . '/config/queue.php';
        $config     = file_exists($configFile) ? include $configFile : [];

        // 创建队列实例，使用配置中的驱动
        $this->queue = new \JEALER\G3\Queue($config);
    }

    private function findWordPressRoot(): ?string
    {
        // 从 /wp-content/plugins/g3/bin 向上4级到WordPress根目录
        $currentDir = dirname(__DIR__, 4);

        if (file_exists($currentDir . '/wp-config.php')) {
            return $currentDir;
        }

        // 备用查找方法
        $currentDir = __DIR__;
        $maxDepth   = 10;
        $depth      = 0;

        while ($depth < $maxDepth) {
            if (file_exists($currentDir . '/wp-config.php')) {
                return $currentDir;
            }

            $parentDir = dirname($currentDir);
            if ($parentDir === $currentDir) {
                break;
            }

            $currentDir = $parentDir;
            $depth++;
        }

        return null;
    }

    public function showStatus(): void
    {
        $queue = $this->options['queue'];

        try {
            // 获取队列驱动和配置信息
            $driver     = $this->queue->getDriver();
            $config     = $this->queue->getConfig();
            $driverType = $config['driver'] ?? 'unknown';

            echo "Queue Status:" . PHP_EOL;
            echo "Queue Name: {$queue}" . PHP_EOL;
            echo "Driver: {$driverType}" . PHP_EOL;

            // 获取基本队列大小
            $size = $this->queue->size($queue);
            echo "Available Jobs: {$size}" . PHP_EOL;

            // 根据驱动类型显示详细信息
            if ($driverType === 'database') {
                $this->showDatabaseQueueStatus($queue);
            } elseif ($driverType === 'redis') {
                $this->showRedisQueueStatus($queue);
            }

        }
        catch (Exception $e) {
            echo "Error getting queue status: " . $e->getMessage() . PHP_EOL;
        }
    }

    private function showDatabaseQueueStatus(string $queue): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'g3_jobs';

        // 检查表是否存在
        $tableExists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
        if (!$tableExists) {
            echo "Database table {$table} does not exist." . PHP_EOL;
            return;
        }

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE queue = %s",
            $queue
        ));

        $now       = date('Y-m-d H:i:s');
        $available = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE queue = %s AND available_at <= %s AND reserved_at IS NULL",
            $queue,
            $now
        ));

        $reserved = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE queue = %s AND reserved_at IS NOT NULL",
            $queue
        ));

        $delayed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE queue = %s AND available_at > %s AND reserved_at IS NULL",
            $queue,
            $now
        ));

        echo "Total Jobs: {$total}" . PHP_EOL;
        echo "Reserved Jobs: {$reserved}" . PHP_EOL;
        echo "Delayed Jobs: {$delayed}" . PHP_EOL;

        // 显示最近的任务
        if ($total > 0) {
            echo PHP_EOL . "Recent Jobs:" . PHP_EOL;
            $recentJobs = $wpdb->get_results($wpdb->prepare(
                "SELECT id, payload, attempts, created_at, available_at, reserved_at 
                 FROM {$table} 
                 WHERE queue = %s 
                 ORDER BY created_at DESC 
                 LIMIT 5",
                $queue
            ), ARRAY_A);

            foreach ($recentJobs as $job) {
                $payload   = json_decode($job['payload'], true);
                $jobClass  = basename($payload['job'] ?? 'Unknown');
                $status    = $job['reserved_at'] ? 'Reserved' :
                    ($job['available_at'] > $now ? 'Delayed' : 'Available');
                $createdAt = $job['created_at'];
                echo "  - ID: {$job['id']}, Job: {$jobClass}, Status: {$status}, Created: {$createdAt}" . PHP_EOL;
            }
        }
    }

    private function showRedisQueueStatus(string $queue): void
    {
        $driver = $this->queue->getDriver();

        echo "Redis Queue Details:" . PHP_EOL;

        // 尝试获取Redis连接信息
        $config      = $this->queue->getConfig();
        $redisConfig = $config['redis'] ?? [];
        $host        = $redisConfig['host'] ?? 'localhost';
        $port        = $redisConfig['port'] ?? 6379;
        $database    = $redisConfig['database'] ?? 0;

        echo "Redis Host: {$host}:{$port}" . PHP_EOL;
        echo "Redis Database: {$database}" . PHP_EOL;

        // 如果Redis驱动有额外的统计方法，可以调用
        if (method_exists($driver, 'getStats')) {
            $stats = $driver->getStats($queue);
            foreach ($stats as $key => $value) {
                echo ucfirst($key) . ": {$value}" . PHP_EOL;
            }
        }
    }

    public function showDriverInfo(): void
    {
        try {
            // 开始输出缓冲，捕获可能的 WordPress HTML 错误
            ob_start();

            $config     = $this->queue->getConfig();
            $driver     = $this->queue->getDriver();
            $driverType = $config['driver'] ?? 'unknown';

            // 清理输出缓冲区中的 HTML 错误
            $buffer = ob_get_clean();

            echo "Queue Driver Information:" . PHP_EOL;
            echo "========================" . PHP_EOL;
            echo "Driver Type: {$driverType}" . PHP_EOL;
            echo "Driver Class: " . get_class($driver) . PHP_EOL;

            if ($driverType === 'database') {
                $dbConfig = $config['database'] ?? [];
                $table    = $dbConfig['table'] ?? 'g3_jobs';
                global $wpdb;
                $fullTableName = (isset($wpdb) ? $wpdb->prefix : 'wp_') . $table;
                echo "Database Table: {$fullTableName}" . PHP_EOL;
            } elseif ($driverType === 'redis') {
                $redisConfig = $config['redis'] ?? [];
                echo "Redis Host: " . ($redisConfig['host'] ?? 'localhost') . PHP_EOL;
                echo "Redis Port: " . ($redisConfig['port'] ?? 6379) . PHP_EOL;
                echo "Redis Database: " . ($redisConfig['database'] ?? 0) . PHP_EOL;
                echo "Redis Prefix: " . ($redisConfig['prefix'] ?? 'g3_queue:') . PHP_EOL;
            }

            echo "Consumer Type: " . ($config['consumer'] ?? 'cli') . PHP_EOL;
            echo PHP_EOL;

        }
        catch (Exception $e) {
            // 清理可能的输出缓冲
            if (ob_get_level()) {
                ob_end_clean();
            }
            echo "Error getting driver info: " . $e->getMessage() . PHP_EOL;
        }
    }

    public function clearQueue(): void
    {
        $queue = $this->options['queue'];

        echo "Clearing queue '{$queue}'..." . PHP_EOL;

        try {
            $driver     = $this->queue->getDriver();
            $config     = $this->queue->getConfig();
            $driverType = $config['driver'] ?? 'unknown';

            if ($driverType === 'database') {
                $this->clearDatabaseQueue($queue);
            } elseif ($driverType === 'redis') {
                $this->clearRedisQueue($queue);
            } else {
                echo "Unknown driver type: {$driverType}" . PHP_EOL;
            }

        }
        catch (Exception $e) {
            echo "Error clearing queue: " . $e->getMessage() . PHP_EOL;
        }
    }

    private function clearDatabaseQueue(string $queue): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'g3_jobs';

        $deleted = $wpdb->delete($table, ['queue' => $queue], ['%s']);
        echo "Deleted {$deleted} jobs from the database queue." . PHP_EOL;
    }

    private function clearRedisQueue(string $queue): void
    {
        $driver = $this->queue->getDriver();

        // 如果Redis驱动有清空方法，使用它
        if (method_exists($driver, 'clear')) {
            $deleted = $driver->clear($queue);
            echo "Cleared Redis queue. Deleted jobs: {$deleted}" . PHP_EOL;
        } else {
            // 否则通过循环pop来清空
            $deleted = 0;
            while ($this->queue->pop($queue)) {
                $deleted++;
            }
            echo "Cleared Redis queue by popping all jobs. Deleted: {$deleted}" . PHP_EOL;
        }
    }

    public function performCleanup(): void
    {
        echo "Performing queue cleanup..." . PHP_EOL;

        try {
            $config         = $this->queue->getConfig();
            $cleanupOptions = $config['cleanup'] ?? [];

            $driver    = $this->queue->getDriver();
            $cleanedUp = $driver->cleanup($cleanupOptions);

            echo "Cleaned up {$cleanedUp} expired/old jobs." . PHP_EOL;

            // 显示清理后的统计信息
            if ($driver instanceof \JEALER\G3\Queue\DatabaseQueue) {
                $stats = $driver->getQueueStats($this->options['queue']);
                echo "Queue statistics after cleanup:" . PHP_EOL;
                echo "  Total jobs: {$stats['total']}" . PHP_EOL;
                echo "  Available jobs: {$stats['available']}" . PHP_EOL;
                echo "  Reserved jobs: {$stats['reserved']}" . PHP_EOL;
                echo "  Delayed jobs: {$stats['delayed']}" . PHP_EOL;
            }

        }
        catch (Exception $e) {
            echo "Error performing cleanup: " . $e->getMessage() . PHP_EOL;
        }
    }

    public static function showHelp(): void
    {
        echo "G3 Queue Manager - G3队列管理脚本" . PHP_EOL;
        echo PHP_EOL;
        echo "使用方法:" . PHP_EOL;
        echo "  php queue-manager.php [action] [options]" . PHP_EOL;
        echo PHP_EOL;
        echo "动作:" . PHP_EOL;
        echo "  status     查看队列状态" . PHP_EOL;
        echo "  info       查看驱动信息" . PHP_EOL;
        echo "  clear      清空队列" . PHP_EOL;
        echo "  cleanup    清理过期/旧任务" . PHP_EOL;
        echo PHP_EOL;
        echo "选项:" . PHP_EOL;
        echo "  --queue=default  队列名称 (默认: default)" . PHP_EOL;
        echo "  --help           显示此帮助信息" . PHP_EOL;
        echo PHP_EOL;
        echo "示例:" . PHP_EOL;
        echo "  php queue-manager.php status" . PHP_EOL;
        echo "  php queue-manager.php info" . PHP_EOL;
        echo "  php queue-manager.php status --queue=high" . PHP_EOL;
        echo "  php queue-manager.php clear --queue=default" . PHP_EOL;
        echo "  php queue-manager.php cleanup" . PHP_EOL;
        echo PHP_EOL;
    }
}

// 检查是否请求帮助
if (in_array('--help', $argv ?? [])) {
    G3QueueManager::showHelp();
    exit(0);
}

// 获取动作
$action = $argv[1] ?? 'status';

// 创建管理器实例
try {
    $manager = new G3QueueManager();

    switch ($action) {
        case 'status':
            $manager->showStatus();
            break;
        case 'info':
            $manager->showDriverInfo();
            break;
        case 'clear':
            $manager->clearQueue();
            break;
        case 'cleanup':
            $manager->performCleanup();
            break;
        default:
            echo "Unknown action: {$action}" . PHP_EOL;
            echo "Supported actions: status, info, clear, cleanup" . PHP_EOL;
            echo "Use --help for more information." . PHP_EOL;
            exit(1);
    }

}
catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}