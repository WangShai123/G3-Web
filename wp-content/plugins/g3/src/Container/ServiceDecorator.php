<?php
namespace JEALER\G3\Container;

/**
 * Service Decorator
 * 服务装饰器实现
 * 
 * 支持链式装饰器，按注册顺序应用装饰器
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class ServiceDecorator implements ServiceDecoratorInterface {
    /**
     * @var array<string, array<callable>> 装饰器存储
     */
    private array $decorators = [];

    /**
     * @var array<string, int> 装饰器优先级
     */
    private array $priorities = [];

    public function decorate(string $serviceId, callable $decorator): void
    {
        if (!isset($this->decorators[$serviceId])) {
            $this->decorators[$serviceId] = [];
        }

        $this->decorators[$serviceId][] = $decorator;
    }

    /**
     * 带优先级的装饰器注册
     * 
     * @param string $serviceId 服务ID
     * @param callable $decorator 装饰器
     * @param int $priority 优先级（数字越大优先级越高）
     * @return void
     */
    public function decorateWithPriority(string $serviceId, callable $decorator, int $priority = 0): void
    {
        if (!isset($this->decorators[$serviceId])) {
            $this->decorators[$serviceId] = [];
            $this->priorities[$serviceId] = [];
        }

        $this->decorators[$serviceId][] = $decorator;
        $this->priorities[$serviceId][] = $priority;

        // 按优先级排序
        $this->sortDecorators($serviceId);
    }

    public function getDecorators(string $serviceId): array
    {
        return $this->decorators[$serviceId] ?? [];
    }

    public function applyDecorators(string $serviceId, object $service): object
    {
        $decorators = $this->getDecorators($serviceId);

        if (empty($decorators)) {
            return $service;
        }

        $decoratedService = $service;

        foreach ($decorators as $decorator) {
            try {
                $result = $decorator($decoratedService, $serviceId);

                if (!is_object($result)) {
                    throw new \RuntimeException(
                        "Decorator for service '{$serviceId}' must return an object, " .
                        gettype($result) . " returned"
                    );
                }

                $decoratedService = $result;

            }
            catch (\Throwable $e) {
                throw new \RuntimeException(
                    "Error applying decorator to service '{$serviceId}': " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        return $decoratedService;
    }

    public function hasDecorators(string $serviceId): bool
    {
        return !empty($this->decorators[$serviceId]);
    }

    public function removeDecorators(string $serviceId): void
    {
        unset($this->decorators[$serviceId]);
        unset($this->priorities[$serviceId]);
    }

    public function getAllDecorators(): array
    {
        return $this->decorators;
    }

    /**
     * 移除特定装饰器
     * 
     * @param string $serviceId 服务ID
     * @param int $index 装饰器索引
     * @return bool 是否成功移除
     */
    public function removeDecorator(string $serviceId, int $index): bool
    {
        if (!isset($this->decorators[$serviceId][$index])) {
            return false;
        }

        unset($this->decorators[$serviceId][$index]);

        if (isset($this->priorities[$serviceId][$index])) {
            unset($this->priorities[$serviceId][$index]);
        }

        // 重新索引数组
        $this->decorators[$serviceId] = array_values($this->decorators[$serviceId]);

        if (isset($this->priorities[$serviceId])) {
            $this->priorities[$serviceId] = array_values($this->priorities[$serviceId]);
        }

        return true;
    }

    /**
     * 获取装饰器数量
     * 
     * @param string $serviceId 服务ID
     * @return int 装饰器数量
     */
    public function getDecoratorCount(string $serviceId): int
    {
        return count($this->decorators[$serviceId] ?? []);
    }

    /**
     * 清空所有装饰器
     * 
     * @return void
     */
    public function clearAll(): void
    {
        $this->decorators = [];
        $this->priorities = [];
    }

    /**
     * 按优先级排序装饰器
     * 
     * @param string $serviceId 服务ID
     * @return void
     */
    private function sortDecorators(string $serviceId): void
    {
        if (!isset($this->priorities[$serviceId])) {
            return;
        }

        $decorators = $this->decorators[$serviceId];
        $priorities = $this->priorities[$serviceId];

        // 创建索引数组
        $indices = array_keys($decorators);

        // 按优先级排序索引
        usort($indices, function ($a, $b) use ($priorities) {
            return ($priorities[$b] ?? 0) <=> ($priorities[$a] ?? 0);
        });

        // 重新排列装饰器
        $sortedDecorators = [];
        $sortedPriorities = [];

        foreach ($indices as $index) {
            $sortedDecorators[] = $decorators[$index];
            $sortedPriorities[] = $priorities[$index] ?? 0;
        }

        $this->decorators[$serviceId] = $sortedDecorators;
        $this->priorities[$serviceId] = $sortedPriorities;
    }

    /**
     * 批量注册装饰器
     * 
     * @param array $decoratorConfig 装饰器配置
     * @return void
     */
    public function registerDecorators(array $decoratorConfig): void
    {
        foreach ($decoratorConfig as $serviceId => $decorators) {
            if (!is_array($decorators)) {
                continue;
            }

            foreach ($decorators as $decoratorInfo) {
                if (is_callable($decoratorInfo)) {
                    $this->decorate($serviceId, $decoratorInfo);
                } elseif (is_array($decoratorInfo) && isset($decoratorInfo['decorator'])) {
                    $decorator = $decoratorInfo['decorator'];
                    $priority  = $decoratorInfo['priority'] ?? 0;

                    if (is_callable($decorator)) {
                        $this->decorateWithPriority($serviceId, $decorator, $priority);
                    }
                }
            }
        }
    }

    /**
     * 创建条件装饰器
     * 
     * @param string $serviceId 服务ID
     * @param callable $decorator 装饰器
     * @param callable $condition 条件函数
     * @return void
     */
    public function decorateIf(string $serviceId, callable $decorator, callable $condition): void
    {
        $conditionalDecorator = function ($service, $id) use ($decorator, $condition) {
            if ($condition($service, $id)) {
                return $decorator($service, $id);
            }
            return $service;
        };

        $this->decorate($serviceId, $conditionalDecorator);
    }

    /**
     * 创建缓存装饰器
     * 
     * @param string $serviceId 服务ID
     * @param callable $cacheKeyGenerator 缓存键生成器
     * @return void
     */
    public function decorateWithCache(string $serviceId, callable $cacheKeyGenerator = null): void
    {
        $cache = [];

        $cacheDecorator = function ($service, $id) use (&$cache, $cacheKeyGenerator) {
            $cacheKey = $cacheKeyGenerator ? $cacheKeyGenerator($service, $id) : $id;

            if (!isset($cache[$cacheKey])) {
                $cache[$cacheKey] = $service;
            }

            return $cache[$cacheKey];
        };

        $this->decorate($serviceId, $cacheDecorator);
    }

    /**
     * 获取装饰器统计信息
     * 
     * @return array 统计信息
     */
    public function getStats(): array
    {
        $stats = [
            'total_services'   => count($this->decorators),
            'total_decorators' => 0,
            'services'         => []
        ];

        foreach ($this->decorators as $serviceId => $decorators) {
            $count                          = count($decorators);
            $stats['total_decorators']     += $count;
            $stats['services'][$serviceId]  = $count;
        }

        return $stats;
    }

    /**
     * 导出装饰器配置
     * 
     * @return array 可序列化的装饰器配置
     */
    public function export(): array
    {
        return [
            'decorators' => array_map('count', $this->decorators),
            'stats'      => $this->getStats()
        ];
    }
}