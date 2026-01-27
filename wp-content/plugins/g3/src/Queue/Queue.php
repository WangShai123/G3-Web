<?php
namespace JEALER\G3\Queue;

use JEALER\G3\Queue\QueueInterface;
use JEALER\G3\Queue\RedisQueue;
use JEALER\G3\Queue\DatabaseQueue;
use JEALER\G3\Utilities\System;

/**
 * Queue Manager
 * 
 * 队列管理器 - 支持门面模式和实例化模式
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class Queue {
    protected static ?QueueInterface $driver = null;
    protected static array $config = [];

    // 实例模式属性
    protected ?QueueInterface $instanceDriver = null;
    protected array $instanceConfig = [];
    // 已处理任务计数器
    protected int $processedJobsCount = 0;

    /**
     * Constructor for instance mode
     * 
     * 实例模式构造函数
     * 
     * @param array $config Configuration array
     */
    public function __construct(array $config = [])
    {
        $this->instanceConfig = empty($config) ? System::config('queue') : $config;
        $this->instanceDriver = $this->createDriver($this->instanceConfig);
    }

    /**
     * Create a new Queue instance with specific configuration
     * 
     * 使用特定配置创建新的队列实例
     * 
     * @param array $config Configuration array
     * @return static
     */
    public static function connect(array $config = []): static
    {
        return new static($config);
    }

    /**
     * Create a new Queue instance with database driver
     * 
     * 创建使用数据库驱动的队列实例
     * 
     * @param array $config Database configuration
     * @return static
     */
    public static function database(array $config = []): static
    {
        $dbConfig = array_merge(System::config('queue', []), [
            'driver'   => 'database',
            'database' => array_merge(
                System::config('queue', [])['database'] ?? [],
                $config
            )
        ]);

        return new static($dbConfig);
    }

    /**
     * Create a new Queue instance with Redis driver
     * 
     * 创建使用Redis驱动的队列实例
     * 
     * @param array $config Redis configuration
     * @return static
     */
    public static function redis(array $config = []): static
    {
        $redisConfig = array_merge(System::config('queue', []), [
            'driver' => 'redis',
            'redis'  => array_merge(
                System::config('queue', [])['redis'] ?? [],
                $config
            )
        ]);

        return new static($redisConfig);
    }

    /**
     * Initialize queue manager (facade mode)
     * 
     * 初始化队列管理器（门面模式）
     * 
     * @param array $config Configuration
     * @return void
     */
    public static function init(array $config = []): void
    {
        self::$config = empty($config) ? System::config('queue', []) : $config;
        self::$driver = self::createDriverStatic(self::$config);
    }

    /**
     * Get queue driver (facade mode)
     * 
     * 获取队列驱动（门面模式）
     * 
     * @return QueueInterface
     */
    public static function driver(): QueueInterface
    {
        if (!self::$driver) {
            self::init();
        }

        return self::$driver;
    }

    /**
     * Process queue jobs (static method for facade)
     * 
     * 处理队列任务（门面模式静态方法）
     * 
     * @return void
     */
    public static function processStatic(): void
    {
        // 处理延迟任务（如果是Redis队列）
        if (self::$driver instanceof RedisQueue) {
            self::$driver->processDelayedJobs();
        }

        // 尝试处理最多10个任务
        for ($i = 0; $i < 10; $i++) {
            $job = self::$driver->pop();
            if (!$job) {
                break; // 队列为空
            }

            self::executeJob($job);
        }
    }

    /**
     * Process queue jobs (instance method)
     * 
     * 处理队列任务（实例方法）
     * 
     * @return void
     */
    public function process(): void
    {
        // 处理延迟任务（如果是Redis队列）
        if ($this->instanceDriver instanceof RedisQueue) {
            $this->instanceDriver->processDelayedJobs();
        }

        // 尝试处理最多10个任务
        for ($i = 0; $i < 10; $i++) {
            $job = $this->instanceDriver->pop();
            if (!$job) {
                break; // 队列为空
            }

            $this->executeJobInstance($job);
        }
    }

    /**
     * Execute a job (static method for facade)
     * 
     * 执行任务（门面模式静态方法）
     * 
     * @param array $job Job data
     * @return void
     */
    protected static function executeJob(array $job): void
    {
        $jobClass = $job['job'] ?? null;
        $jobData  = $job['data'] ?? [];
        $attempts = $job['attempts'] ?? 0;

        if (!$jobClass || !class_exists($jobClass)) {
            error_log("[G3 Debug][Queue] job class {$jobClass} does not exist");
            return;
        }

        try {
            $jobInstance = new $jobClass();
            if (method_exists($jobInstance, 'handle')) {
                $jobInstance->handle($jobData);
            }
        }
        catch (\Exception $e) {
            $maxAttempts = 3; // 最大尝试次数
            if ($attempts < $maxAttempts) {
                // 重新加入队列（带延迟）
                self::$driver->push($jobClass, $jobData, pow(2, $attempts)); // 指数退避
            } else {
                // 超过最大尝试次数，调用失败处理
                if (method_exists($jobInstance, 'failed')) {
                    $jobInstance->failed($jobData, $e);
                }
            }

            error_log("[G3 Debug][Queue] job failed: " . $e->getMessage());
        }
    }

    /**
     * Execute a job (instance method)
     * 
     * 执行任务（实例方法）
     * 
     * @param array $job Job data
     * @return void
     */
    protected function executeJobInstance(array $job): void
    {
        $jobClass = $job['job'] ?? null;
        $jobData  = $job['data'] ?? [];
        $attempts = $job['attempts'] ?? 0;

        if (!$jobClass || !class_exists($jobClass)) {
            error_log("[G3 Debug][Queue] job class {$jobClass} does not exist");
            return;
        }

        $success     = false;
        $jobInstance = null;

        try {
            $jobInstance = new $jobClass();
            if (method_exists($jobInstance, 'handle')) {
                $jobInstance->handle($jobData);
                $success = true;
            }
        }
        catch (\Exception $e) {
            $maxAttempts = 3; // 最大尝试次数
            if ($attempts < $maxAttempts) {
                // 重新加入队列（带延迟）
                $this->instanceDriver->push($jobClass, $jobData, pow(2, $attempts)); // 指数退避
            } else {
                // 超过最大尝试次数，调用失败处理
                if ($jobInstance && method_exists($jobInstance, 'failed')) {
                    $jobInstance->failed($jobData, $e);
                }
            }

            error_log("[G3 Debug][Queue] job failed: " . $e->getMessage());
        }

        // 如果启用自动清除，任务成功完成后删除
        if ($success && $this->shouldAutoCleanup()) {
            $this->deleteCompletedJob($job);
        }

        // 增加处理计数器并检查是否需要清理
        $this->processedJobsCount++;
        $this->checkAndPerformCleanup();
    }

    /**
     * Check if auto cleanup is enabled
     * 
     * 检查是否启用自动清除
     * 
     * @return bool
     */
    protected function shouldAutoCleanup(): bool
    {
        $consumer = $this->instanceConfig['consumer'] ?? 'cli';

        // 优先使用消费者特定配置
        if (isset($this->instanceConfig[$consumer]['auto_cleanup'])) {
            return $this->instanceConfig[$consumer]['auto_cleanup'];
        }

        // 回退到全局配置
        return $this->instanceConfig['auto_cleanup'] ?? true;
    }

    /**
     * Delete completed job
     * 
     * 删除已完成的任务
     * 
     * @param array $job Job data
     * @return void
     */
    protected function deleteCompletedJob(array $job): void
    {
        try {
            // 对于数据库驱动，使用任务ID删除
            if ($this->instanceDriver instanceof DatabaseQueue) {
                $jobId = $job['database_info']['id'] ?? null;
                if ($jobId) {
                    $this->instanceDriver->delete($jobId);
                }
            }
            // 对于Redis驱动，任务已经在pop时被移除了
            elseif ($this->instanceDriver instanceof RedisQueue) {
                // Redis队列中任务pop后就已经被移除，无需额外操作
                return;
            }
        }
        catch (\Exception $e) {
            error_log("[G3 Debug][Queue] Failed to delete completed job: " . $e->getMessage());
        }
    }

    /**
     * Check and perform cleanup if needed
     * 
     * 检查并在需要时执行清理
     * 
     * @return void
     */
    protected function checkAndPerformCleanup(): void
    {
        if (!$this->shouldAutoCleanup()) {
            return;
        }

        $consumer = $this->instanceConfig['consumer'] ?? 'cli';

        // 获取消费者特定的清理间隔
        $cleanupInterval = $this->getConsumerConfig($consumer, 'cleanup_interval', 100);

        if ($this->processedJobsCount % $cleanupInterval === 0) {
            $this->performCleanup();
        }
    }

    /**
     * Perform cleanup
     * 
     * 执行清理
     * 
     * @return int Number of jobs cleaned up
     */
    public function performCleanup(): int
    {
        try {
            $consumer = $this->instanceConfig['consumer'] ?? 'cli';

            // 构建清理选项，优先使用消费者特定配置
            $cleanupOptions = [
                'reserved_timeout' => $this->getConsumerConfig($consumer, 'reserved_timeout', 60),
                'old_jobs_days'    => $this->getConsumerConfig($consumer, 'old_jobs_days', 7),
            ];

            return $this->instanceDriver->cleanup($cleanupOptions);
        }
        catch (\Exception $e) {
            error_log("[G3 Debug][Queue] cleanup failed: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get consumer-specific configuration value
     * 
     * 获取消费者特定的配置值
     * 
     * @param string $consumer Consumer type (cli, cron, supervisor)
     * @param string $key Configuration key
     * @param mixed $default Default value
     * @return mixed
     */
    protected function getConsumerConfig(string $consumer, string $key, $default = null)
    {
        // 优先使用消费者特定配置
        if (isset($this->instanceConfig[$consumer][$key])) {
            return $this->instanceConfig[$consumer][$key];
        }

        // 回退到全局cleanup配置
        if (isset($this->instanceConfig['cleanup'][$key])) {
            return $this->instanceConfig['cleanup'][$key];
        }

        return $default;
    }

    /**
     * Unregister cron jobs (typically called during deactivation)
     * 
     * 取消注册定时任务（通常在停用时调用）
     * 
     * @return void
     */
    public static function unregisterCron(): void
    {
        // 取消新的统一cron任务
        $timestamp = wp_next_scheduled('g3_process_queue');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'g3_process_queue');
            error_log("[G3 Debug][Queue] cron unscheduled successfully");
        }

        // 取消智能cron检查任务
        $checkTimestamp = wp_next_scheduled('g3_check_queue_cron');
        if ($checkTimestamp) {
            wp_unschedule_event($checkTimestamp, 'g3_check_queue_cron');
            error_log("[G3 Debug][Queue] Smart cron check unscheduled successfully");
        }

        // 取消旧的门面模式定时任务（向后兼容）
        $oldTimestamp = wp_next_scheduled('g3_process_queue');
        if ($oldTimestamp) {
            wp_unschedule_event($oldTimestamp, 'g3_process_queue');
        }

        // 取消旧的实例模式定时任务（向后兼容）
        $instanceTimestamp = wp_next_scheduled('g3_process_queue_instance');
        if ($instanceTimestamp) {
            wp_unschedule_event($instanceTimestamp, 'g3_process_queue_instance');
        }

        // 清理智能cron状态
        delete_option('g3_queue_empty_runs');
        delete_option('g3_queue_cron_status');
        delete_option('g3_queue_last_check');
    }

    /**
     * Create queue driver based on configuration (static method for facade)
     * 
     * 根据配置创建队列驱动（门面模式的静态方法）
     * 
     * @param array $config Configuration
     * @return QueueInterface
     */
    protected static function createDriverStatic(array $config): QueueInterface
    {
        $driver = $config['driver'] ?? 'database';

        switch ($driver) {
            case 'redis':
                return new RedisQueue($config['redis'] ?? []);
            case 'database':
            default:
                return new DatabaseQueue($config['database'] ?? []);
        }
    }

    /**
     * Create queue driver based on configuration (instance method)
     * 
     * 根据配置创建队列驱动（实例方法）
     * 
     * @param array $config Configuration
     * @return QueueInterface
     */
    protected function createDriver(array $config): QueueInterface
    {
        $driver = $config['driver'] ?? 'database';

        switch ($driver) {
            case 'redis':
                return new RedisQueue($config['redis'] ?? []);
            case 'database':
            default:
                return new DatabaseQueue($config['database'] ?? []);
        }
    }

    /**
     * Get queue driver (instance mode)
     * 
     * 获取队列驱动（实例模式）
     * 
     * @return QueueInterface
     */
    public function getDriver(): QueueInterface
    {
        return $this->instanceDriver;
    }

    /**
     * Get configuration (instance mode)
     * 
     * 获取配置（实例模式）
     * 
     * @return array
     */
    public function getConfig(): array
    {
        return $this->instanceConfig;
    }

    // ========== 实例模式方法 ==========

    /**
     * Push a job to the queue (instance mode)
     * 
     * 推送任务到队列（实例模式）
     * 
     * @param string $job Job class name
     * @param array $data Job data
     * @param int $delay Delay in seconds
     * @param string $queue Queue name
     * @return mixed
     */
    public function push(string $job, array $data = [], int $delay = 0, string $queue = 'default')
    {
        return $this->instanceDriver->push($job, $data, $delay, $queue);
    }

    /**
     * Pop a job from the queue (instance mode)
     * 
     * 从队列取出任务（实例模式）
     * 
     * @param string $queue Queue name
     * @return mixed
     */
    public function pop(string $queue = 'default')
    {
        return $this->instanceDriver->pop($queue);
    }

    /**
     * Get queue size (instance mode)
     * 
     * 获取队列大小（实例模式）
     * 
     * @param string $queue Queue name
     * @return int
     */
    public function size(string $queue = 'default'): int
    {
        return $this->instanceDriver->size($queue);
    }

    /**
     * Process delayed jobs (instance mode)
     * 
     * 处理延迟任务（实例模式）
     * 
     * @return void
     */
    public function processDelayedJobs(): void
    {
        if ($this->instanceDriver instanceof RedisQueue) {
            $this->instanceDriver->processDelayedJobs();
        }
    }

    /**
     * Process a single job from the queue (instance mode)
     * 
     * 处理队列中的单个任务（实例模式）
     * 
     * @param string $queue Queue name
     * @return bool True if a job was processed, false otherwise
     */
    public function processJob(string $queue = 'default'): bool
    {
        $job = $this->pop($queue);
        if (!$job) {
            return false;
        }

        $this->executeJobInstance($job);
        return true;
    }

    /**
     * Process multiple jobs from the queue (instance mode)
     * 
     * 处理队列中的多个任务（实例模式）
     * 
     * @param int $limit Maximum number of jobs to process
     * @param string $queue Queue name
     * @return int Number of jobs processed
     */
    public function processJobs(int $limit = 10, string $queue = 'default'): int
    {
        $processed = 0;

        for ($i = 0; $i < $limit; $i++) {
            if ($this->processJob($queue)) {
                $processed++;
            } else {
                break; // 队列为空
            }
        }

        return $processed;
    }
}