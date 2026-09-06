<?php
namespace JEALER\G3\Core\Service;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Core\Container\FactoryDefinition;
use JEALER\G3\Services\LogService;
use JEALER\G3\Utilities\Type;
use Psr\Log\LoggerInterface;
use wpdb;
use Redis;

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
        $this->onInit();
    }

    protected function onInit(): void {}

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

    protected function z(): bool
    {
        try {
            return $this->container->get('loader')->admin();
        }
        catch (Throwable) {
            return false;
        }
    }

    protected function x(): bool
    {
        try {
            return $this->container->get('loader')->x();
        }
        catch (Throwable) {
            return false;
        }
    }

    protected function getArrayOption(string $key, array $default = []): array
    {
        return Type::arrayOption(get_option($key, $default));
    }

    protected function getOptionKV(string $key, string $keyname, mixed $defaultKV = ''): mixed
    {
        return get_option($key)[$keyname] ?? $defaultKV;
    }
}
