<?php
namespace JEALER\G3\Container;

use JEALER\G3\Attributes\Inject;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use LogicException;

/**
 * Define how to create an instance of a class
 * 
 * Supports:
 * - 显式构造参数（值或服务引用）
 * - #[Inject("id")] 属性注入（仅限构造函数参数）
 * - 自动装配（基于单一非内置类型参数）
 * - 默认参数回退
 */
class FactoryDefinition implements DefinitionInterface {
    private bool $singleton = true;
    private array $arguments = [];
    private ?array $resolvedArguments = null;

    public function __construct(private string $class)
    {
        // error_log("FactoryDefinition: " . $this->class);
    }

    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * 设置构造函数参数（按位置或名称）
     *
     * @param mixed ...$args
     * @return self
     */
    public function constructor(...$args): self
    {
        $this->arguments         = $args;
        $this->resolvedArguments = null;
        return $this;
    }

    /**
     * 按参数名设置构造参数
     *
     * @param string $name 参数名
     * @param mixed $value 值、Reference 或 '@service' 字符串
     * @return self
     */
    public function argument(string $name, mixed $value): self
    {
        $this->arguments[$name]  = $value;
        $this->resolvedArguments = null;
        return $this;
    }

    /**
     * 设置是否为单例（默认 true）
     */
    public function singleton(bool $flag = true): self
    {
        $this->singleton = $flag;
        return $this;
    }

    public function isSingleton(): bool
    {
        return $this->singleton;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function resolve(ContainerInterface $container): object
    {
        if ($this->resolvedArguments === null) {
            $this->resolveConstructorParameters($container);
        }

        $reflector = new ReflectionClass($this->class);
        return $reflector->newInstanceArgs($this->resolvedArguments);
    }

    private function resolveConstructorParameters(ContainerInterface $container): void
    {
        $reflector   = new \ReflectionClass($this->class);
        $constructor = $reflector->getConstructor();

        if (!$constructor) {
            $this->resolvedArguments = [];
            return;
        }

        $parameters = $constructor->getParameters();
        $args       = [];

        foreach ($parameters as $index => $param) {
            $paramName = $param->getName();

            // 1. 显式参数（by index or name）
            if (array_key_exists($index, $this->arguments)) {
                $args[] = $this->resolveArgument($this->arguments[$index], $container);
                continue;
            }
            if (isset($this->arguments[$paramName])) {
                $args[] = $this->resolveArgument($this->arguments[$paramName], $container);
                continue;
            }

            // 2. 使用 #[Inject] 或 #[Inject('id')] 属性
            $injectAttributes = $param->getAttributes(Inject::class);
            if (!empty($injectAttributes)) {
                /** @var Inject $injectInstance */
                $injectInstance = $injectAttributes[0]->newInstance();

                if ($injectInstance->value !== null) {
                    // 按 ID 注入
                    $args[] = $container->get($injectInstance->value);
                } else {
                    // 按类型注入：使用参数的类型全名作为服务 ID
                    if ($className = $this->getInjectableClassName($param)) {
                        $args[] = $container->get($className);
                    } else {
                        throw new LogicException("Cannot auto-inject parameter \${$param->getName()} in {$this->class}: no type hint or not a class.");
                    }
                }
                continue;
            }

            // 3. 自动装配：基于类型提示（仅支持单一、非内置命名类型）
            if ($className = $this->getInjectableClassName($param)) {
                if ($container->has($className)) {
                    $args[] = $container->get($className);
                    continue;
                }
            }

            // 4. 使用默认值（如果存在）
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            // 5. 无法解析：抛出异常
            $typeDesc = $param->hasType() ? (string) $param->getType() : 'mixed';
            throw new LogicException(
                "Cannot resolve required parameter \${$paramName} (type: {$typeDesc}) in {$this->class}. " .
                "Provide via constructor(), #[Inject('service_id')], or bind the type explicitly."
            );
        }

        $this->resolvedArguments = $args;
    }

    private function resolveArgument(mixed $arg, ContainerInterface $container): mixed
    {
        if ($arg instanceof DefinitionInterface) {
            return $arg->resolve($container);
        }

        if (is_string($arg) && str_starts_with($arg, '@')) {
            // 兼容旧风格 '@logger' → get('logger')
            return $container->get(substr($arg, 1));
        }

        return $arg;
    }

    /**
     * 从参数中提取可注入的类名（仅支持单一、非内置命名类型）
     * @return string|null 返回类名，或 null（如果是标量、联合类型、交集类型等）
     */
    private function getInjectableClassName(ReflectionParameter $param)
    {
        if (!$param->hasType()) {
            return null;
        }

        $type = $param->getType();

        // 仅处理命名类型（排除 union / intersection）
        if (!$type instanceof ReflectionNamedType) {
            return null;
        }

        // 排除内置类型（int, string, bool, array 等）
        if ($type->isBuiltin()) {
            return null;
        }

        return $type->getName();
    }

}
