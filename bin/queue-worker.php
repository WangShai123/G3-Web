<?php
/**
 * G3 Queue Worker
 * 
 * G3队列系统的消费者脚本
 * 
 * 使用方法:
 * php queue-worker.php [options]
 * 
 * 选项:
 * --queue=default     指定队列名称
 * --sleep=3          空队列时的休眠时间(秒)
 * --tries=3          任务最大重试次数
 * --timeout=60       任务执行超时时间(秒)
 * --daemon           以守护进程模式运行
 * --stop-when-empty  队列为空时停止
 * --verbose          显示详细输出
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
 * G3队列工作器类
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class G3QueueWorker {
    private array $options = [
        'queue'           => 'default',
        'sleep'           => 3,
        'tries'           => 3,
        'timeout'         => 60,
        'daemon'          => false,
        'stop_when_empty' => false,
        'verbose'         => false,
    ];

    private      $queue;
    private bool $shouldQuit     = false;
    private int  $processedJobs  = 0;
    private int  $cleanupCounter = 0; // 清理计数器

    public function __construct()
    {
        $this->parseArguments();
        $this->setupSignalHandlers();
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
                    if (in_array($key, ['sleep', 'tries', 'timeout'])) {
                        $value = (int) $value;
                    }
                    $this->options[$key] = $value;
                }
            }
        }
    }

    private function setupSignalHandlers(): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            pcntl_signal(SIGQUIT, [$this, 'handleSignal']);
        }
    }

    public function handleSignal(int $signal): void
    {
        $this->log("Received signal: {$signal}. Shutting down gracefully...");
        $this->shouldQuit = true;
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
        require_once dirname(__DIR__) . '/src/Core/Queue/QueueInterface.php';
        require_once dirname(__DIR__) . '/src/Core/Queue/Job.php';
        require_once dirname(__DIR__) . '/src/Core/Queue/DatabaseQueue.php';
        require_once dirname(__DIR__) . '/src/Core/Queue/RedisQueue.php';
        require_once dirname(__DIR__) . '/src/Utilities/System.php';
        require_once dirname(__DIR__) . '/src/Core/Queue/Queue.php';

        // 加载队列配置
        $configFile = dirname(__DIR__) . '/config/queue.php';
        $config     = file_exists($configFile) ? include $configFile : [];

        // 创建队列实例，使用配置中的驱动
        $this->queue = new \JEALER\G3\Core\Queue\Queue($config);

        // 获取驱动信息并记录
        $driverType = $config['driver'] ?? 'database';
        $this->log("Environment initialized successfully with {$driverType} driver.");
    }

    private function findWordPressRoot(): ?string
    {
        // 根据需要修改 向上 查找到 WordPress根目录 的级别，默认为 5
        $currentDir = dirname(__DIR__, 5);

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

    public function run(): void
    {
        $config     = $this->queue->getConfig();
        $driverType = $config['driver'] ?? 'database';

        // 使用CLI配置中的默认值（如果可用）
        if (isset($config['cli'])) {
            $cliConfig = $config['cli'];

            // 如果命令行没有指定，使用配置文件中的默认值
            if ($this->options['sleep'] === 3 && isset($cliConfig['sleep_seconds'])) {
                $this->options['sleep'] = $cliConfig['sleep_seconds'];
            }

            // 设置内存限制
            if (isset($cliConfig['memory_limit']) && $cliConfig['memory_limit'] > 0) {
                ini_set('memory_limit', $cliConfig['memory_limit'] . 'M');
            }

            // 设置执行时间限制
            if (isset($cliConfig['time_limit'])) {
                set_time_limit($cliConfig['time_limit']);
            }
        }

        $this->log("Starting G3 Queue Worker...");
        $this->log("Driver: {$driverType}");
        $this->log("Queue: {$this->options['queue']}");
        $this->log("Sleep: {$this->options['sleep']}s");
        $this->log("Max tries: {$this->options['tries']}");
        $this->log("Daemon mode: " . ($this->options['daemon'] ? 'Yes' : 'No'));
        $this->log("Auto cleanup: " . ($this->shouldAutoCleanup() ? 'Yes' : 'No'));

        do {
            // 检查信号
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            if ($this->shouldQuit) {
                break;
            }

            // 处理延迟任务（如果是Redis驱动）
            if ($driverType === 'redis') {
                $this->queue->processDelayedJobs();
            }

            // 处理任务
            $job = $this->getNextJob();

            if ($job) {
                $this->processJob($job);
                $this->processedJobs++;
            } else {
                if ($this->options['stop_when_empty']) {
                    $this->log("Queue is empty. Stopping worker.");
                    break;
                }

                $this->log("Queue is empty. Sleeping for {$this->options['sleep']} seconds...");
                sleep($this->options['sleep']);
            }

        } while ($this->options['daemon']);

        $this->log("Worker stopped. Processed {$this->processedJobs} jobs.");
    }

    private function getNextJob(): ?array
    {
        try {
            return $this->queue->pop($this->options['queue']);
        }
        catch (Exception $e) {
            $this->log("Error getting next job: " . $e->getMessage());
            return null;
        }
    }

    private function processJob(array $job): void
    {
        $jobClass = $job['job'] ?? 'Unknown';
        $jobData  = $job['data'] ?? [];
        $attempts = $job['attempts'] ?? 0;

        $this->log("Processing job: {$jobClass} (Attempt: " . ($attempts + 1) . ")");

        $startTime = microtime(true);
        $success   = false;
        $exception = null;

        try {
            if (!class_exists($jobClass)) {
                throw new Exception("Job class {$jobClass} does not exist");
            }

            $jobInstance = new $jobClass();

            if (!method_exists($jobInstance, 'handle')) {
                throw new Exception("Job class {$jobClass} does not have a handle method");
            }

            $jobInstance->handle($jobData);
            $success = true;

        }
        catch (Exception $e) {
            $exception = $e;
            $this->log("Job failed: " . $e->getMessage());
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        if ($success) {
            $this->log("Job completed successfully in {$duration}ms");

            // 删除已完成的任务（如果启用自动清除）
            if ($this->shouldAutoCleanup()) {
                $this->deleteCompletedJob($job);
            }
        } else {
            $this->handleFailedJob($job, $exception);
        }

        // 检查是否需要执行清理
        $this->checkAndPerformCleanup();
    }

    private function handleFailedJob(array $job, ?Exception $exception): void
    {
        $jobClass = $job['job'] ?? 'Unknown';
        $jobData  = $job['data'] ?? [];
        $attempts = ($job['attempts'] ?? 0) + 1;

        if ($attempts < $this->options['tries']) {
            $delay = pow(2, $attempts - 1) * 60; // 指数退避

            $this->log("Retrying job {$jobClass} in {$delay} seconds (Attempt {$attempts}/{$this->options['tries']})");

            try {
                // 重新入队，使用队列实例的push方法
                $this->queue->push($jobClass, $jobData, $delay, $this->options['queue']);

                // 删除原任务
                $this->deleteCompletedJob($job);
            }
            catch (Exception $e) {
                $this->log("Failed to retry job: " . $e->getMessage());
            }
        } else {
            $this->log("Job {$jobClass} failed permanently after {$attempts} attempts");

            try {
                if (class_exists($jobClass)) {
                    $jobInstance = new $jobClass();
                    if (method_exists($jobInstance, 'failed')) {
                        $jobInstance->failed($jobData, $exception);
                    }
                }

                // 删除失败的任务
                $this->deleteCompletedJob($job);
            }
            catch (Exception $e) {
                $this->log("Error handling failed job: " . $e->getMessage());
            }
        }
    }

    /**
     * Check if auto cleanup is enabled
     * 
     * 检查是否启用自动清除
     * 
     * @return bool
     */
    private function shouldAutoCleanup(): bool
    {
        $config = $this->queue->getConfig();

        // 优先使用CLI特定配置
        if (isset($config['cli']['auto_cleanup'])) {
            return $config['cli']['auto_cleanup'];
        }

        // 回退到全局配置
        return $config['auto_cleanup'] ?? true;
    }

    /**
     * Check and perform cleanup if needed
     * 
     * 检查并在需要时执行清理
     * 
     * @return void
     */
    private function checkAndPerformCleanup(): void
    {
        if (!$this->shouldAutoCleanup()) {
            return;
        }

        $config = $this->queue->getConfig();

        // 优先使用CLI特定配置
        $cleanupInterval = $config['cli']['cleanup_interval'] ??
            $config['cleanup']['cleanup_interval'] ?? 100;

        $this->cleanupCounter++;

        if ($this->cleanupCounter >= $cleanupInterval) {
            $this->performCleanup();
            $this->cleanupCounter = 0; // 重置计数器
        }
    }

    /**
     * Perform cleanup
     * 
     * 执行清理
     * 
     * @return void
     */
    private function performCleanup(): void
    {
        try {
            $cleanedUp = $this->queue->performCleanup();
            if ($cleanedUp > 0) {
                $this->log("Cleaned up {$cleanedUp} expired/old jobs");
            }
        }
        catch (Exception $e) {
            $this->log("Cleanup failed: " . $e->getMessage());
        }
    }

    /**
     * Delete completed job from database
     * 
     * 从数据库删除已完成的任务
     * 
     * @param array $job Job data
     * @return void
     */
    private function deleteCompletedJob(array $job): void
    {
        try {
            $driver = $this->queue->getDriver();

            // 如果是数据库驱动，并且有任务ID，则删除任务
            if ($driver instanceof \JEALER\G3\Core\Queue\DatabaseQueue) {
                $jobId = $job['database_info']['id'] ?? null;
                if ($jobId) {
                    $deleted = $driver->delete($jobId);
                    if ($deleted) {
                        $this->log("Deleted completed job ID: {$jobId}");
                    } else {
                        $this->log("Failed to delete job ID: {$jobId}");
                    }
                }
            }
            // Redis队列中任务pop后就已经被移除
        }
        catch (Exception $e) {
            $this->log("Error deleting completed job: " . $e->getMessage());
        }
    }

    private function log(string $message): void
    {
        if (!$this->options['verbose'] && !$this->options['daemon']) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $pid       = getmypid();
        $memory    = $this->formatBytes(memory_get_usage(true));

        echo "[{$timestamp}] [{$pid}] [{$memory}] {$message}" . PHP_EOL;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow   = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public static function showHelp(): void
    {
        echo "G3 Queue Worker - G3队列消费者脚本" . PHP_EOL;
        echo PHP_EOL;
        echo "使用方法:" . PHP_EOL;
        echo "  php queue-worker.php [options]" . PHP_EOL;
        echo PHP_EOL;
        echo "选项:" . PHP_EOL;
        echo "  --queue=default     指定队列名称 (默认: default)" . PHP_EOL;
        echo "  --sleep=3          空队列时的休眠时间(秒) (默认: 3)" . PHP_EOL;
        echo "  --tries=3          任务最大重试次数 (默认: 3)" . PHP_EOL;
        echo "  --timeout=60       任务执行超时时间(秒) (默认: 60)" . PHP_EOL;
        echo "  --daemon           以守护进程模式运行" . PHP_EOL;
        echo "  --stop-when-empty  队列为空时停止" . PHP_EOL;
        echo "  --verbose          显示详细输出" . PHP_EOL;
        echo "  --help             显示此帮助信息" . PHP_EOL;
        echo PHP_EOL;
        echo "示例:" . PHP_EOL;
        echo "  php queue-worker.php --stop-when-empty --verbose" . PHP_EOL;
        echo "  php queue-worker.php --daemon --verbose --sleep=5" . PHP_EOL;
        echo "  php queue-worker.php --queue=high --tries=5" . PHP_EOL;
        echo PHP_EOL;
    }
}

// 检查是否请求帮助
if (in_array('--help', $argv ?? [])) {
    G3QueueWorker::showHelp();
    exit(0);
}

// 创建并运行工作器
try {
    $worker = new G3QueueWorker();
    $worker->run();
}
catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
