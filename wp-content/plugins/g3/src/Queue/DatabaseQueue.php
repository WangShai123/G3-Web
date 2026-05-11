<?php

namespace JEALER\G3\Queue;

use JEALER\G3\Queue\QueueInterface;
use DateTime;
use DateTimeZone;
use wpdb;
use Exception;

/**
 * Database Queue Implementation
 * 
 * 数据库队列实现 - 使用 UTC DateTime 存储时间以提高健壮性
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class DatabaseQueue implements QueueInterface {

    private wpdb $wpdb;
    protected string $table;

    public function __construct(array $config = [])
    {
        global $wpdb;
        $this->wpdb  = $wpdb;
        $this->table = $this->wpdb->prefix . ($config['table'] ?? 'g3_queue_jobs');

        $this->checkTable();
    }

    /**
     * Create jobs table if not exists
     * 
     * 如果不存在则创建任务表
     * 
     * @return void
     */
    protected function checkTable(): void
    {
        $charset = $this->wpdb->get_charset_collate();

        if ($this->wpdb->get_var("SHOW TABLES LIKE '$this->table'") !== $this->table) {
            $sql = "CREATE TABLE IF NOT EXISTS `$this->table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `queue` VARCHAR(255) NOT NULL DEFAULT 'default',
                `payload` LONGTEXT NOT NULL,
                `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `reserved_at` DATETIME NULL DEFAULT NULL,
                `available_at` DATETIME NOT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_queue_available (`queue`, `available_at`),
                KEY idx_reserved_at (`reserved_at`),
                KEY idx_created_at (`created_at`)
            ) ENGINE=InnoDB $charset COMMENT='queue jobs';";
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
        }
    }

    /**
     * Get current UTC datetime
     * 
     * 获取当前 UTC 时间
     * 
     * @return DateTime
     */
    protected function getCurrentUtcDateTime(): DateTime
    {
        return new DateTime('now', new DateTimeZone('UTC'));
    }

    /**
     * Format datetime for database storage
     * 
     * 格式化时间用于数据库存储
     * 
     * @param DateTime $dateTime
     * @return string
     */
    protected function formatDateTimeForDb(DateTime $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s');
    }

    /**
     * Create datetime from delay seconds
     * 
     * 从延迟秒数创建时间
     * 
     * @param int $delay Delay in seconds
     * @return DateTime
     */
    protected function createDelayedDateTime(int $delay): DateTime
    {
        $dateTime = $this->getCurrentUtcDateTime();
        if ($delay > 0) {
            $dateTime->modify("+{$delay} seconds");
        }
        return $dateTime;
    }

    /**
     * Parse database datetime to DateTime object
     * 
     * 解析数据库时间为 DateTime 对象
     * 
     * @param string|null $dbDateTime
     * @return DateTime|null
     */
    protected function parseDatabaseDateTime(?string $dbDateTime): ?DateTime
    {
        if (empty($dbDateTime) || $dbDateTime === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return new DateTime($dbDateTime, new DateTimeZone('UTC'));
        }
        catch (Exception $e) {
            error_log("[G3 DatabaseQueue] Failed to parse database datetime: {$dbDateTime}");
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function push(string $job, array $data = [], int $delay = 0, string $queue = 'default')
    {
        $now         = $this->getCurrentUtcDateTime();
        $availableAt = $this->createDelayedDateTime($delay);

        $payload = json_encode([
            'job'        => $job,
            'data'       => $data,
            'attempts'   => 0,
            'created_at' => $now->getTimestamp(),
        ]);

        $result = $this->wpdb->insert(
            $this->table,
            [
                'queue'        => $queue,
                'payload'      => $payload,
                'attempts'     => 0,
                'reserved_at'  => null,
                'available_at' => $this->formatDateTimeForDb($availableAt),
                'created_at'   => $this->formatDateTimeForDb($now),
                'updated_at'   => $this->formatDateTimeForDb($now),
            ],
            ['%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * {@inheritdoc}
     */
    public function pop(string $queue = 'default')
    {
        $now          = $this->getCurrentUtcDateTime();
        $nowFormatted = $this->formatDateTimeForDb($now);

        // 查找可用的任务
        $job = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} 
                WHERE queue = %s 
                AND available_at <= %s 
                AND reserved_at IS NULL 
                ORDER BY available_at ASC, id ASC 
                LIMIT 1",
                $queue,
                $nowFormatted
            ),
            ARRAY_A
        );

        if ($job) {
            // 标记任务为已保留
            $updated = $this->wpdb->update(
                $this->table,
                [
                    'reserved_at' => $nowFormatted,
                    'updated_at'  => $nowFormatted
                ],
                [
                    'id'          => $job['id'],
                    'reserved_at' => null // 确保只更新未被保留的任务
                ],
                ['%s', '%s'],
                ['%d', '%s']
            );

            // 如果更新成功，返回任务数据
            if ($updated) {
                $payload = json_decode($job['payload'], true);

                // 添加数据库中的时间信息
                $payload['database_info'] = [
                    'id'           => $job['id'],
                    'queue'        => $job['queue'],
                    'attempts'     => $job['attempts'],
                    'available_at' => $job['available_at'],
                    'created_at'   => $job['created_at'],
                    'reserved_at'  => $nowFormatted,
                ];

                return $payload;
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function size(string $queue = 'default'): int
    {
        $now = $this->formatDateTimeForDb($this->getCurrentUtcDateTime());

        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} 
                WHERE queue = %s 
                AND available_at <= %s 
                AND reserved_at IS NULL",
                $queue,
                $now
            )
        );
    }

    /**
     * Get total queue size (including reserved jobs)
     * 
     * 获取队列总大小（包括已保留的任务）
     * 
     * @param string $queue Queue name
     * @return int
     */
    public function totalSize(string $queue = 'default'): int
    {
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE queue = %s",
                $queue
            )
        );
    }

    /**
     * Get reserved jobs count
     * 
     * 获取已保留任务数量
     * 
     * @param string $queue Queue name
     * @return int
     */
    public function reservedSize(string $queue = 'default'): int
    {
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} 
                WHERE queue = %s AND reserved_at IS NOT NULL",
                $queue
            )
        );
    }

    /**
     * Get delayed jobs count
     * 
     * 获取延迟任务数量
     * 
     * @param string $queue Queue name
     * @return int
     */
    public function delayedSize(string $queue = 'default'): int
    {
        $now = $this->formatDateTimeForDb($this->getCurrentUtcDateTime());

        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} 
                WHERE queue = %s 
                AND available_at > %s 
                AND reserved_at IS NULL",
                $queue,
                $now
            )
        );
    }

    /**
     * Release reserved job back to queue
     * 
     * 释放保留的任务回到队列
     * 
     * @param int $jobId Job ID
     * @param int $delay Additional delay in seconds
     * @return bool
     */
    public function release(int $jobId, int $delay = 0): bool
    {
        $now         = $this->getCurrentUtcDateTime();
        $availableAt = $this->createDelayedDateTime($delay);

        $result = $this->wpdb->update(
            $this->table,
            [
                'reserved_at'  => null,
                'available_at' => $this->formatDateTimeForDb($availableAt),
                'updated_at'   => $this->formatDateTimeForDb($now),
            ],
            ['id' => $jobId],
            ['%s', '%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Delete job from queue
     * 
     * 从队列删除任务
     * 
     * @param mixed $jobId Job ID
     * @return bool
     */
    public function delete($jobId): bool
    {
        $result = $this->wpdb->delete(
            $this->table,
            ['id' => $jobId],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Clean up expired/old jobs
     * 
     * 清理过期/旧任务
     * 
     * @param array $options Cleanup options
     * @return int Number of jobs cleaned up
     */
    public function cleanup(array $options = []): int
    {
        $reservedTimeout = $options['reserved_timeout'] ?? 60; // 分钟
        $oldJobsDays     = $options['old_jobs_days'] ?? 7; // 天数

        $cleanedUp = 0;

        // 清理超时的保留任务
        $cleanedUp += $this->cleanupReservedJobs($reservedTimeout);

        // 清理旧任务
        $cleanedUp += $this->cleanupOldJobs($oldJobsDays);

        return $cleanedUp;
    }

    /**
     * Increment job attempts
     * 
     * 增加任务尝试次数
     * 
     * @param int $jobId Job ID
     * @return bool
     */
    public function incrementAttempts(int $jobId): bool
    {
        $now = $this->formatDateTimeForDb($this->getCurrentUtcDateTime());

        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table} 
                SET attempts = attempts + 1, updated_at = %s 
                WHERE id = %d",
                $now,
                $jobId
            )
        );

        return $result !== false;
    }

    /**
     * Clean up old reserved jobs
     * 
     * 清理长时间保留的任务
     * 
     * @param int $timeoutMinutes Timeout in minutes (default: 60)
     * @return int Number of jobs released
     */
    public function cleanupReservedJobs(int $timeoutMinutes = 60): int
    {
        $timeoutDateTime = $this->getCurrentUtcDateTime();
        $timeoutDateTime->modify("-{$timeoutMinutes} minutes");
        $timeoutFormatted = $this->formatDateTimeForDb($timeoutDateTime);

        $now = $this->formatDateTimeForDb($this->getCurrentUtcDateTime());

        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table} 
                SET reserved_at = NULL, updated_at = %s 
                WHERE reserved_at IS NOT NULL 
                AND reserved_at < %s",
                $now,
                $timeoutFormatted
            )
        );

        return $result !== false ? $result : 0;
    }

    /**
     * Clean up old completed jobs
     * 
     * 清理旧的已完成任务
     * 
     * @param int $daysOld Days old (default: 7)
     * @return int Number of jobs deleted
     */
    public function cleanupOldJobs(int $daysOld = 7): int
    {
        $cutoffDateTime = $this->getCurrentUtcDateTime();
        $cutoffDateTime->modify("-{$daysOld} days");
        $cutoffFormatted = $this->formatDateTimeForDb($cutoffDateTime);

        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table} 
                WHERE created_at < %s 
                AND reserved_at IS NULL",
                $cutoffFormatted
            )
        );

        return $result !== false ? $result : 0;
    }

    /**
     * Get queue statistics
     * 
     * 获取队列统计信息
     * 
     * @param string $queue Queue name
     * @return array
     */
    public function getQueueStats(string $queue = 'default'): array
    {
        $now = $this->formatDateTimeForDb($this->getCurrentUtcDateTime());

        $stats = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN reserved_at IS NULL AND available_at <= %s THEN 1 ELSE 0 END) as available,
                    SUM(CASE WHEN reserved_at IS NOT NULL THEN 1 ELSE 0 END) as reserved,
                    SUM(CASE WHEN reserved_at IS NULL AND available_at > %s THEN 1 ELSE 0 END) as `delayed`,
                    AVG(attempts) as avg_attempts,
                    MAX(attempts) as max_attempts
                FROM {$this->table} 
                WHERE queue = %s",
                $now,
                $now,
                $queue
            ),
            ARRAY_A
        );

        return [
            'queue'        => $queue,
            'total'        => (int) ($stats['total'] ?? 0),
            'available'    => (int) ($stats['available'] ?? 0),
            'reserved'     => (int) ($stats['reserved'] ?? 0),
            'delayed'      => (int) ($stats['delayed'] ?? 0),
            'avg_attempts' => round((float) ($stats['avg_attempts'] ?? 0), 2),
            'max_attempts' => (int) ($stats['max_attempts'] ?? 0),
            'timestamp'    => $now,
        ];
    }

    /**
     * Get all queue names
     * 
     * 获取所有队列名称
     * 
     * @return array
     */
    public function getAllQueueNames(): array
    {
        $results = $this->wpdb->get_col(
            "SELECT DISTINCT queue FROM {$this->table} ORDER BY queue"
        );

        return $results ?: [];
    }
}
