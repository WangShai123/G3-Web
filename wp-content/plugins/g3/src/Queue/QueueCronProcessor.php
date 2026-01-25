<?php

namespace JEALER\G3\Queue;

/**
 * G3 Queue Cron Processor
 * G3队列定时任务处理器
 * 
 * 这个脚本专门用于WordPress cron系统处理队列任务
 * 包含自动清除和超时过期清除功能
 * 
 * @since 1.0.0
 * @author Wang Shai
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

use JEALER\G3\Queue;
use JEALER\G3\Queue\RedisQueue;
use JEALER\G3\Queue\DatabaseQueue;

/**
 * G3 Queue Cron Processor Class
 * G3队列定时任务处理器类
 */
class QueueCronProcessor {
    protected Queue $queue;
    protected array $config;
    protected static int $cronRunCount = 0;

    /**
     * Constructor
     * 
     * @param array $config Queue configuration
     */
    public function __construct(array $config = [])
    {
        $this->queue  = new Queue($config);
        $this->config = $this->queue->getConfig();
    }

    /**
     * Process queue jobs in cron context
     * 在cron上下文中处理队列任务
     * 
     * @return array Processing results
     */
    public function process(): array
    {
        $startTime = microtime(true);
        $results   = [
            'processed_jobs'    => 0,
            'failed_jobs'       => 0,
            'delayed_processed' => 0,
            'cleaned_up'        => 0,
            'execution_time'    => 0,
            'memory_usage'      => 0,
            'errors'            => [],
        ];

        try {
            // 1. 处理延迟任务（如果启用）
            if ($this->shouldProcessDelayed()) {
                $results['delayed_processed'] = $this->processDelayedJobs();
            }

            // 2. 处理常规队列任务
            $jobsPerRun                = $this->config['cron']['jobs_per_run'] ?? 10;
            $results['processed_jobs'] = $this->processRegularJobs($jobsPerRun);

            // 3. 执行清理（如果需要）
            if ($this->shouldPerformCleanup()) {
                $results['cleaned_up'] = $this->performCleanup();
            }

        }
        catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
            error_log("[G3] Queue cron processing error: " . $e->getMessage());
        }

        // 记录执行统计
        $results['execution_time'] = round((microtime(true) - $startTime) * 1000, 2);
        $results['memory_usage']   = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        // 增加cron运行计数
        self::$cronRunCount++;

        // 记录处理结果
        $this->logResults($results);

        return $results;
    }

    /**
     * Process delayed jobs
     * 处理延迟任务
     * 
     * @return int Number of delayed jobs processed
     */
    protected function processDelayedJobs(): int
    {
        $driver = $this->queue->getDriver();

        if ($driver instanceof RedisQueue) {
            $driver->processDelayedJobs();
            return 1; // Redis延迟任务处理不返回具体数量
        }

        return 0;
    }

    /**
     * Process regular queue jobs
     * 处理常规队列任务
     * 
     * @param int $limit Maximum number of jobs to process
     * @return int Number of jobs processed
     */
    protected function processRegularJobs(int $limit): int
    {
        $processed = 0;

        for ($i = 0; $i < $limit; $i++) {
            $job = $this->queue->pop('default');
            if (!$job) {
                break; // 队列为空
            }

            try {
                $this->executeJob($job);
                $processed++;
            }
            catch (\Exception $e) {
                error_log("[G3] Cron job execution failed: " . $e->getMessage());
                // 继续处理其他任务
            }
        }

        return $processed;
    }

    /**
     * Execute a single job
     * 执行单个任务
     * 
     * @param array $job Job data
     * @return void
     * @throws \Exception
     */
    protected function executeJob(array $job): void
    {
        $jobClass = $job['job'] ?? null;
        $jobData  = $job['data'] ?? [];
        $attempts = $job['attempts'] ?? 0;

        if (!$jobClass || !class_exists($jobClass)) {
            throw new \Exception("Job class {$jobClass} does not exist");
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
                $delay = pow(2, $attempts) * 60; // 指数退避，以分钟为单位
                $this->queue->push($jobClass, $jobData, $delay);
                error_log("[G3] Cron job failed, retrying in {$delay} seconds: " . $e->getMessage());
            } else {
                // 超过最大尝试次数，调用失败处理
                if ($jobInstance && method_exists($jobInstance, 'failed')) {
                    $jobInstance->failed($jobData, $e);
                }
                error_log("[G3] Cron job failed permanently: " . $e->getMessage());
            }

            throw $e;
        }

        // 如果启用自动清除，任务成功完成后删除
        if ($success && $this->shouldAutoCleanup()) {
            $this->deleteCompletedJob($job);
        }
    }

    /**
     * Delete completed job
     * 删除已完成的任务
     * 
     * @param array $job Job data
     * @return void
     */
    protected function deleteCompletedJob(array $job): void
    {
        try {
            $driver = $this->queue->getDriver();

            // 对于数据库驱动，使用任务ID删除
            if ($driver instanceof DatabaseQueue) {
                $jobId = $job['database_info']['id'] ?? null;
                if ($jobId) {
                    $driver->delete($jobId);
                }
            }
            // 对于Redis驱动，任务已经在pop时被移除了
        }
        catch (\Exception $e) {
            error_log("[G3] Failed to delete completed job in cron: " . $e->getMessage());
        }
    }

    /**
     * Perform cleanup operations
     * 执行清理操作
     * 
     * @return int Number of items cleaned up
     */
    protected function performCleanup(): int
    {
        try {
            $cronConfig = $this->config['cron'] ?? [];

            // 构建清理选项，优先使用cron特定配置
            $cleanupOptions = [
                'reserved_timeout' => $cronConfig['reserved_timeout'] ??
                    $this->config['cleanup']['reserved_timeout'] ?? 60,
                'old_jobs_days'    => $cronConfig['old_jobs_days'] ??
                    $this->config['cleanup']['old_jobs_days'] ?? 7,
            ];

            $cleaned = $this->queue->getDriver()->cleanup($cleanupOptions);

            if ($cleaned > 0) {
                error_log("[G3] Cron cleanup completed: {$cleaned} items cleaned up");
            }

            return $cleaned;
        }
        catch (\Exception $e) {
            error_log("[G3] Cron cleanup failed: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check if should process delayed jobs
     * 检查是否应该处理延迟任务
     * 
     * @return bool
     */
    protected function shouldProcessDelayed(): bool
    {
        return $this->config['cron']['process_delayed'] ?? true;
    }

    /**
     * Check if should perform cleanup
     * 检查是否应该执行清理
     * 
     * @return bool
     */
    protected function shouldPerformCleanup(): bool
    {
        $cronConfig = $this->config['cron'] ?? [];

        if (!($cronConfig['auto_cleanup'] ?? true)) {
            return false;
        }

        $cleanupInterval = $cronConfig['cron_cleanup_interval'] ??
            $this->config['cleanup']['cron_cleanup_interval'] ?? 5;

        return (self::$cronRunCount % $cleanupInterval) === 0;
    }

    /**
     * Check if auto cleanup is enabled
     * 检查是否启用自动清除
     * 
     * @return bool
     */
    protected function shouldAutoCleanup(): bool
    {
        $cronConfig = $this->config['cron'] ?? [];

        if (isset($cronConfig['auto_cleanup'])) {
            return $cronConfig['auto_cleanup'];
        }

        return true;
    }

    /**
     * Log processing results
     * 记录处理结果
     * 
     * @param array $results Processing results
     * @return void
     */
    protected function logResults(array $results): void
    {
        if ($results['processed_jobs'] > 0 || $results['cleaned_up'] > 0 || !empty($results['errors'])) {
            $message = sprintf(
                "[G3] Cron run #%d completed - Jobs: %d, Cleaned: %d, Time: %sms, Memory: %sMB",
                self::$cronRunCount,
                $results['processed_jobs'],
                $results['cleaned_up'],
                $results['execution_time'],
                $results['memory_usage']
            );

            if (!empty($results['errors'])) {
                $message .= " - Errors: " . count($results['errors']);
            }

            error_log($message);
        }
    }

    /**
     * Get queue statistics
     * 获取队列统计信息
     * 
     * @return array
     */
    public function getStats(): array
    {
        $driver = $this->queue->getDriver();
        $stats  = [
            'queue_size'  => $this->queue->size('default'),
            'cron_runs'   => self::$cronRunCount,
            'driver_type' => get_class($driver),
            'config'      => $this->config,
        ];

        // 获取驱动特定的统计信息
        if ($driver instanceof DatabaseQueue) {
            $stats['database_stats'] = $driver->getQueueStats('default');
        } elseif ($driver instanceof RedisQueue) {
            $stats['redis_stats'] = $driver->getStats('default');
        }

        return $stats;
    }

    public static function queueProcessor()
    {
        try {
            $processor = new QueueCronProcessor();
            $results   = $processor->process();

        }
        catch (\Exception $e) {
            error_log("[G3] Queue cron hook failed: " . $e->getMessage());
        }
    }
}
