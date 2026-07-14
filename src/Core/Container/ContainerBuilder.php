<?php
namespace JEALER\G3\Core\Container;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Core\Container\ConfigLoader;
use JEALER\G3\Core\Container\FactoryDefinition;
use JEALER\G3\Core\Container\Reference;
use Psr\Container\ContainerInterface;

class ContainerBuilder {

    private array $definitions = [];

    private array $parameters = [];

    private array $tagConfig = [];

    private array $decoratorConfig = [];

    public function addDefinitions(array $definitions): self
    {
        foreach ($definitions as $id => $definition) {
            $this->set($id, $definition);
        }
        return $this;
    }

    public function addParameters(array $parameters): self
    {
        $this->parameters = array_merge($this->parameters, $parameters);
        return $this;
    }

    public function addConfigFile(string $configFile): self
    {
        $config = ConfigLoader::load($configFile);
        if (isset($config['parameters'])) {
            $this->addParameters($config['parameters']);
        }
        if (isset($config['services'])) {
            $this->addServicesFromConfig($config['services']);
        }
        if (isset($config['tags'])) {
            $this->addTags($config['tags']);
        }
        if (isset($config['decorators'])) {
            $this->addDecorators($config['decorators']);
        }
        return $this;
    }

    private function addServicesFromConfig(array $services): void
    {
        foreach ($services as $id => $config) {
            if (!isset($config['class'])) {
                continue;
            }

            $factory = new FactoryDefinition($config['class']);

            if (isset($config['arguments'])) {
                $resolvedArgs = $this->resolveConfigArguments($config['arguments']);
                $factory->constructor(...$resolvedArgs);
            }

            if (isset($config['singleton'])) {
                $factory->singleton($config['singleton']);
            }

            $this->definitions[$id] = $factory;
        }
    }

    private function resolveConfigArguments(array $args): array
    {
        $resolved = [];
        foreach ($args as $arg) {
            if (is_string($arg) && str_starts_with($arg, '%') && str_ends_with($arg, '%')) {
                // 参数引用: %db.host% → $this->parameters['db.host']
                $key        = substr($arg, 1, -1);
                $resolved[] = $this->parameters[$key] ?? $arg;
            } elseif (is_string($arg) && str_starts_with($arg, '@')) {
                $resolved[] = new Reference(substr($arg, 1));
            } else {
                $resolved[] = $arg;
            }
        }
        return $resolved;
    }

    public function set(string $id, mixed $concrete): self
    {
        if (is_array($concrete) && isset($concrete['class'])) {
            $factory = new FactoryDefinition($concrete['class']);
            if (isset($concrete['arguments'])) {
                $factory->constructor(...$concrete['arguments']);
            }
            if (isset($concrete['singleton'])) {
                $factory->singleton($concrete['singleton']);
            }
            $this->definitions[$id] = $factory;
        } else {
            $this->definitions[$id] = $concrete;
        }
        return $this;
    }

    public function build(): ContainerInterface
    {
        $container = new Container();

        // 设置服务定义
        foreach ($this->definitions as $id => $def) {
            $container->setRawDefinition($id, $def);
        }

        // 设置参数
        if (!empty($this->parameters)) {
            $container->getParameterManager()->setParameters($this->parameters);
        }

        return $container;
    }

    /**
     * 添加标签配置
     * 
     * @param array $tagConfig 标签配置
     * @return self
     */
    public function addTags(array $tagConfig): self
    {
        $this->tagConfig = array_merge($this->tagConfig ?? [], $tagConfig);
        return $this;
    }

    /**
     * 添加装饰器配置
     * 
     * @param array $decoratorConfig 装饰器配置
     * @return self
     */
    public function addDecorators(array $decoratorConfig): self
    {
        $this->decoratorConfig = array_merge($this->decoratorConfig ?? [], $decoratorConfig);
        return $this;
    }

    /**
     * 构建增强版容器
     * 
     * @return ContainerInterface
     */
    public function buildEnhanced(): ContainerInterface
    {
        $container = $this->build();

        // 应用标签配置 
        if (!empty($this->tagConfig)) {
            $container->getTagManager()->batchTag($this->tagConfig);
        }

        // 应用装饰器配置
        if (!empty($this->decoratorConfig)) {
            $container->getServiceDecorator()->registerDecorators($this->decoratorConfig);
        }

        return $container;
    }
}
