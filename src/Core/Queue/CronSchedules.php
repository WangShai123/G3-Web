<?php
namespace JEALER\G3\Core\Queue;
use JEALER\G3\Utilities\System;

/**
 * Cron Schedules and Events Management
 * 
 * Cron计划和事件管理类。负责G3队列的cron计划注册和cron事件管理
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class CronSchedules {

    /**
     * Initialize cron schedules and events (only when needed)
     * 
     * 初始化cron计划和事件（仅在需要时）
     * 
     * @return void
     */
    public static function init(): void
    {
        // 注册cron计划过滤器
        add_filter('cron_schedules', [self::class, 'addSchedules'], 5);

        // 注册cron处理钩子
        add_action('g3_process_queue', [self::class, 'processQueue']);

        error_log('[G3 CronSchedules] Cron schedules and hooks registered.');
    }

    /**
     * Initialize if needed based on queue configuration
     * 
     * 根据队列配置按需初始化
     * 
     * @return void
     */
    public static function initIfNeeded(): void
    {
        try {
            $queueConfig = System::config('queue');

            // 检查consumer是否为cron
            if (isset($queueConfig['consumer']) && $queueConfig['consumer'] === 'cron') {
                self::init();

                // 调度初始cron事件
                $intervalMinutes = $queueConfig['cron']['interval_minutes'] ?? 1;
                self::scheduleCron($intervalMinutes);

                error_log('[G3 CronSchedules] Initialized for cron consumer.');
            }
        }
        catch (\Exception $e) {
            error_log('[G3 CronSchedules] Failed to initialize: ' . $e->getMessage());
        }
    }

    /**
     * Add G3 cron schedules
     * 
     * 添加G3 cron计划
     * 
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public static function addSchedules(array $schedules): array
    {
        // 每分钟执行一次
        $schedules['g3_every_minute'] = [
            'interval' => 60,
            'display'  => __('Every Minute (G3)', 'g3')
        ];

        // 每2分钟执行一次
        $schedules['g3_every_two_minutes'] = [
            'interval' => 120,
            'display'  => __('Every 2 Minutes (G3)', 'g3')
        ];

        // 每5分钟执行一次
        $schedules['g3_every_five_minutes'] = [
            'interval' => 300,
            'display'  => __('Every 5 Minutes (G3)', 'g3')
        ];

        return $schedules;
    }

    /**
     * Schedule cron event
     * 调度cron事件
     * 
     * @param int $intervalMinutes Interval in minutes
     * @return bool Success status
     */
    public static function scheduleCron(int $intervalMinutes = 1): bool
    {
        $schedule = self::getScheduleName($intervalMinutes);

        // 检查计划是否存在
        if (!self::scheduleExists($schedule)) {
            error_log("[G3 CronSchedules] Schedule {$schedule} not found");
            return false;
        }

        // 检查是否已经调度
        if (wp_next_scheduled('g3_process_queue')) {
            error_log("[G3 CronSchedules] Cron already scheduled");
            return true;
        }

        // 调度新的cron事件
        $scheduled = wp_schedule_event(time(), $schedule, 'g3_process_queue');

        if ($scheduled !== false) {
            error_log("[G3 CronSchedules] Cron scheduled successfully with interval: {$schedule}");
            return true;
        } else {
            error_log("[G3 CronSchedules] Failed to schedule cron");
            return false;
        }
    }

    /**
     * Unschedule cron event
     * 
     * 取消调度cron事件
     * 
     * @return bool Success status
     */
    public static function unscheduleCron(): bool
    {
        $timestamp = wp_next_scheduled('g3_process_queue');
        if ($timestamp) {
            $result = wp_unschedule_event($timestamp, 'g3_process_queue');
            if ($result) {
                error_log("[G3 CronSchedules] Cron unscheduled successfully");
                return true;
            } else {
                error_log("[G3 CronSchedules] Failed to unschedule cron");
                return false;
            }
        }

        // 没有调度的任务也算成功
        return true;
    }

    /**
     * Reschedule cron event with new interval
     * 
     * 重新调度cron事件（新间隔）
     * 
     * @param int $intervalMinutes New interval in minutes
     * @return bool Success status
     */
    public static function rescheduleCron(int $intervalMinutes): bool
    {
        // 先取消现有的调度
        self::unscheduleCron();

        // 调度新的事件
        return self::scheduleCron($intervalMinutes);
    }

    /**
     * Process queue (cron hook callback)
     * 
     * 处理队列（cron钩子回调）
     * 
     * @return void
     */
    public static function processQueue(): void
    {
        try {
            error_log("[G3 CronSchedules] Processing queue via cron");

            // 加载队列配置
            $queueConfig = System::config('queue');

            // 创建处理器并执行
            require_once __DIR__ . '/QueueCronProcessor.php';
            $processor = new QueueCronProcessor($queueConfig);
            $results   = $processor->process();

            error_log("[G3 CronSchedules] Queue processing completed - Jobs: {$results['processed_jobs']}, Time: {$results['execution_time']}ms");

        }
        catch (\Exception $e) {
            error_log("[G3 CronSchedules] Queue processing failed: " . $e->getMessage());
        }
    }

    /**
     * Get schedule name based on interval minutes
     * 
     * 根据间隔分钟数获取计划名称
     * 
     * @param int $minutes Interval in minutes
     * @return string Schedule name
     */
    public static function getScheduleName(int $minutes): string
    {
        return match ($minutes) {
            1       => 'g3_every_minute',
            2       => 'g3_every_two_minutes',
            5       => 'g3_every_five_minutes',
            default => 'g3_every_minute'
        };
    }

    /**
     * Check if a schedule exists
     * 
     * 检查计划是否存在
     * 
     * @param string $schedule Schedule name
     * @return bool
     */
    public static function scheduleExists(string $schedule): bool
    {
        $schedules = wp_get_schedules();
        return isset($schedules[$schedule]);
    }

    /**
     * Get schedule interval
     * 
     * 获取计划间隔
     * 
     * @param string $schedule Schedule name
     * @return int|null Interval in seconds, null if not found
     */
    public static function getScheduleInterval(string $schedule): ?int
    {
        $schedules = wp_get_schedules();
        return $schedules[$schedule]['interval'] ?? null;
    }

    /**
     * Check if cron is currently scheduled
     * 
     * 检查cron是否当前已调度
     * 
     * @return bool
     */
    public static function isCronScheduled(): bool
    {
        return wp_next_scheduled('g3_process_queue') !== false;
    }

    /**
     * Get next scheduled time
     * 
     * 获取下次调度时间
     * 
     * @return array
     */
    public static function getNextScheduledTime(): array
    {
        $timestamp = wp_next_scheduled('g3_process_queue');

        return [
            'timestamp' => $timestamp,
            'human'     => $timestamp ? date('Y-m-d H:i:s', $timestamp) : 'Not scheduled'
        ];
    }

    /**
     * Queue cron processor
     * 
     * 队列cron处理器
     * 
     * @return void
     */
    public static function queueCronProcessor()
    {
        try {
            $processor = new QueueCronProcessor();
            $results   = $processor->process();

        }
        catch (\Exception $e) {
            error_log("[G3 CronSchedules] Queue cron hook failed: " . $e->getMessage());
        }
    }
}
