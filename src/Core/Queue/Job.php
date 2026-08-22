<?php
namespace JEALER\G3\Core\Queue;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Core\Container\FactoryDefinition;
use JEALER\G3\Services\LogService;
use Psr\Log\LoggerInterface;
use Throwable;

abstract class Job {
    protected Container        $container;
    protected LoggerInterface $logger;
    protected                 $dep;

    public function __construct()
    {
        $this->container = Container::run();
        $this->logger    = $this->resolveLogger();
        if ($this->dep === null && $this->container->has('loader')) {
            $this->dep = $this->container->get('loader')->admin();
        }
    }

    private function resolveLogger(): LoggerInterface
    {
        if (!$this->container->has(LoggerInterface::class)) {
            $logger = new FactoryDefinition(LogService::class);
            $logger->singleton();
            $this->container->setRawDefinition(LoggerInterface::class, $logger);
        }

        return $this->container->get(LoggerInterface::class);
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
        $this->logger->error('Queue job failed.', [
            'module'    => 'queue',
            'job'       => static::class,
            'data'      => $data,
            'exception' => $exception,
        ]);
    }
}
