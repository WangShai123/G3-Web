<?php
namespace JEALER\G3\Core\Queue;
use JEALER\G3\Core\Container\Container;
use Throwable;

abstract class Job {
    protected Container $container;
    protected           $dep;

    public function __construct()
    {
        $this->container = Container::run();
        if ($this->dep === null && $this->container->has('loader')) {
            $this->dep = $this->container->get('loader')->admin();
        }
    }

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
     * @param Throwable $exception Exception
     * @return void
     */
    public function failed(array $data, Throwable $exception): void
    {
        error_log(sprintf(
            '[G3 Job] Queue job %s failed: %s',
            static::class,
            $exception->getMessage()
        ));
    }
}
