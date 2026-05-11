<?php

namespace JEALER\G3\Queue;

/**
 * Queue Interface
 * 
 * 队列接口
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
interface QueueInterface {

    /**
     * Push a job to the queue
     * 
     * 推送任务到队列
     * 
     * @param string $job Job class name
     * @param array $data Job data
     * @param int $delay Delay in seconds
     * @param string $queue Queue name
     * @return mixed
     */
    public function push(string $job, array $data = [], int $delay = 0, string $queue = 'default');

    /**
     * Pop a job from the queue
     * 
     * 从队列取出任务
     * 
     * @param string $queue Queue name
     * @return mixed
     */
    public function pop(string $queue = 'default');

    /**
     * Get queue size
     * 
     * 获取队列大小
     * 
     * @param string $queue Queue name
     * @return int
     */
    public function size(string $queue = 'default'): int;

    /**
     * Delete a job from the queue
     * 
     * 从队列删除任务
     * 
     * @param mixed $jobId Job identifier
     * @return bool
     */
    public function delete($jobId): bool;

    /**
     * Clean up expired/old jobs
     * 
     * 清理过期/旧任务
     * 
     * @param array $options Cleanup options
     * @return int Number of jobs cleaned up
     */
    public function cleanup(array $options = []): int;
}
