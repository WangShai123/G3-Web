<?php
namespace JEALER\G3\Core\Service;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Core\Container\FactoryDefinition;
use JEALER\G3\Services\LogService;
use Psr\Log\LoggerInterface;
use wpdb;

abstract class Service {
    protected LoggerInterface $logger;
    protected Container       $container;
    protected wpdb            $wpdb;
    protected array           $cache     = [];
    public function __construct()
    {
        $this->container = Container::run();
        $this->logger    = $this->resolveLogger();
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    private function resolveLogger(): LoggerInterface
    {
        if ($this instanceof LoggerInterface) {
            return $this;
        }

        if (!$this->container->has(LoggerInterface::class)) {
            $logger = new FactoryDefinition(LogService::class);
            $logger->singleton();
            $this->container->setRawDefinition(LoggerInterface::class, $logger);
        }

        return $this->container->get(LoggerInterface::class);
    }
    public function cache(): array
    {
        return $this->cache;
    }
}
