<?php
namespace JEALER\G3\Queue;

/**
 * Base Job Class
 * 
 * 队列任务基类
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
abstract class Job {
    /**
     * Execute the job
     * 
     * 执行任务
     * 
     * @param array $data Job data
     * @return void
     */
    abstract public function handle(array $data): void;

    /**
     * Handle job failure
     * 
     * 处理任务失败
     * 
     * @param array $data Job data
     * @param \Throwable $exception Exception
     * @return void
     */
    public function failed(array $data, \Throwable $exception): void
    {
        error_log(sprintf(
            'Queue job %s failed: %s',
            static::class,
            $exception->getMessage()
        ));
    }
}