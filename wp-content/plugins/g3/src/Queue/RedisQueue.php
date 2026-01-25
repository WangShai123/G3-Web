<?php
namespace JEALER\G3\Queue;

use Redis;

/**
 * Redis Queue Implementation
 * 
 * Redis队列实现
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class RedisQueue implements QueueInterface {
    protected Redis $redis;
    protected string $prefix;

    /**
     * Constructor
     * 
     * 构造函数
     * 
     * @param array $config Configuration
     */
    public function __construct(array $config = [])
    {
        $this->redis = new Redis();
        $host        = $config['host'] ?? '127.0.0.1';
        $port        = $config['port'] ?? 6379;
        $timeout     = $config['timeout'] ?? 5;

        $this->redis->connect($host, $port, $timeout);

        if (isset($config['password']) && $config['password']) {
            $this->redis->auth($config['password']);
        }

        if (isset($config['database'])) {
            $this->redis->select($config['database']);
        }

        $this->prefix = $config['prefix'] ?? 'g3_queue:';
    }

    /**
     * {@inheritdoc}
     */
    public function push(string $job, array $data = [], int $delay = 0, string $queue = 'default')
    {
        $payload = json_encode([
            'job'        => $job,
            'data'       => $data,
            'attempts'   => 0,
            'created_at' => time(),
        ]);

        $queueKey = $this->prefix . $queue;

        if ($delay > 0) {
            // 延迟队列实现
            $executeTime  = time() + $delay;
            $delayedQueue = $this->prefix . 'delayed';

            // 为延迟任务生成唯一ID
            $delayedJobId = $this->generateDelayedJobId($queue, $executeTime);

            $this->redis->zAdd($delayedQueue, $executeTime, json_encode([
                'payload' => $payload,
                'queue'   => $queue,
                'job_id'  => $delayedJobId
            ]));

            return $delayedQueue . ':' . $delayedJobId;
        } else {
            // 推送到队列并获取新的长度（即索引位置）
            $newLength = $this->redis->lPush($queueKey, $payload);

            // 返回格式：queuekey:index
            return $queue . ':' . $newLength;
        }
    }

    /**
     * Generate unique ID for delayed job
     * 
     * 为延迟任务生成唯一ID
     * 
     * @param string $queue Queue name
     * @param int $executeTime Execute timestamp
     * @return string
     */
    protected function generateDelayedJobId(string $queue, int $executeTime): string
    {
        // 使用时间戳和微秒生成唯一ID
        $microtime    = microtime(true);
        $microseconds = sprintf("%06d", ($microtime - floor($microtime)) * 1000000);
        return $executeTime . '_' . $microseconds . '_' . substr(md5($queue . $microtime), 0, 6);
    }

    /**
     * {@inheritdoc}
     */
    public function pop(string $queue = 'default')
    {
        $queue   = $this->prefix . $queue;
        $payload = $this->redis->rPop($queue);

        if ($payload) {
            return json_decode($payload, true);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function size(string $queue = 'default'): int
    {
        $queue = $this->prefix . $queue;
        return $this->redis->lLen($queue);
    }

    /**
     * Process delayed jobs
     * 
     * 处理延迟任务
     * 
     * @return void
     */
    public function processDelayedJobs(): void
    {
        $delayedQueue = $this->prefix . 'delayed';
        $currentTime  = time();

        // 获取所有到期的延迟任务
        $dueJobs = $this->redis->zRangeByScore($delayedQueue, 0, $currentTime);

        foreach ($dueJobs as $job) {
            // 移除延迟队列中的任务
            $this->redis->zRem($delayedQueue, $job);

            // 解析延迟任务数据
            $delayedData = json_decode($job, true);

            if (isset($delayedData['payload']) && isset($delayedData['queue'])) {
                // 新格式：包含队列信息
                $targetQueue = $this->prefix . $delayedData['queue'];
                $this->redis->lPush($targetQueue, $delayedData['payload']);
            } else {
                // 旧格式：直接是payload，添加到默认队列
                $this->redis->lPush($this->prefix . 'default', $job);
            }
        }
    }

    /**
     * Delete a job from the queue
     * 
     * 从队列删除任务
     * 
     * @param mixed $jobId Job identifier (for Redis, this is the payload)
     * @return bool
     */
    public function delete($jobId): bool
    {
        // Redis队列中，任务一旦被pop就已经从队列中移除了
        // 这个方法主要是为了接口兼容性
        return true;
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
        $cleanedUp = 0;

        // 清理过期的延迟任务（超过24小时的）
        $delayedQueue = $this->prefix . 'delayed';
        $expiredTime  = time() - (24 * 60 * 60); // 24小时前

        // 获取过期的延迟任务
        $expiredJobs = $this->redis->zRangeByScore($delayedQueue, 0, $expiredTime);

        foreach ($expiredJobs as $job) {
            $this->redis->zRem($delayedQueue, $job);
            $cleanedUp++;
        }

        // 清理空的队列键（可选）
        $this->cleanupEmptyQueues();

        return $cleanedUp;
    }

    /**
     * Clean up empty queue keys
     * 
     * 清理空的队列键
     * 
     * @return void
     */
    protected function cleanupEmptyQueues(): void
    {
        $pattern = $this->prefix . '*';
        $keys    = $this->redis->keys($pattern);

        foreach ($keys as $key) {
            // 跳过延迟队列
            if (strpos($key, 'delayed') !== false) {
                continue;
            }

            // 如果是列表且为空，删除键
            if ($this->redis->type($key) === Redis::REDIS_LIST && $this->redis->lLen($key) === 0) {
                $this->redis->del($key);
            }
        }
    }

    /**
     * Get Redis connection statistics
     * 
     * 获取Redis连接统计信息
     * 
     * @param string $queue Queue name
     * @return array
     */
    public function getStats(string $queue = 'default'): array
    {
        $queueKey     = $this->prefix . $queue;
        $delayedQueue = $this->prefix . 'delayed';

        return [
            'queue_size'   => $this->redis->lLen($queueKey),
            'delayed_jobs' => $this->redis->zCard($delayedQueue),
            'memory_usage' => $this->redis->info('memory')['used_memory_human'] ?? 'N/A',
        ];
    }
}