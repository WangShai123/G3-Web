<?php
namespace JEALER\G3;

use JEALER\G3\Container\DefinitionInterface;
use JEALER\G3\Container\FactoryDefinition;
use JEALER\G3\Container\Reference;
use JEALER\G3\Container\ValueDefinition;
use JEALER\G3\Container\ParameterManagerInterface;
use JEALER\G3\Container\ParameterManager;
use JEALER\G3\Container\ServiceDecoratorInterface;
use JEALER\G3\Container\ServiceDecorator;
use JEALER\G3\Container\TagManagerInterface;
use JEALER\G3\Container\TagManager;
use JEALER\G3\Container\ExtensionManagerInterface;
use JEALER\G3\Container\ExtensionManager;
use JEALER\G3\Container\ContainerExtensionInterface;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use Exception;
use InvalidArgumentException;

/**
 * Simple Container
 * Service Container for managing instances that conforms to PSR-11 standard.
 * @author Wang Shai
 * @since 1.0.0
 */
class Container implements PsrContainerInterface {
    /**
     * @var array<string, object> instances cache
     */
    private array $instances = [];

    private array $definitions = [];

    /**
     * @var ?self singleton facade
     */
    private static ?self $singleton = null;

    /**
     * @var array<string, DefinitionInterface> 全局服务定义注册表
     */
    private static array $globalDefinitions = [];

    /**
     * @var array<string, bool> 循环依赖检测
     */
    private array $resolving = [];

    /**
     * @var ParameterManagerInterface|null 参数管理器
     */
    private ?ParameterManagerInterface $parameterManager = null;

    /**
     * @var ServiceDecoratorInterface|null 服务装饰器
     */
    private ?ServiceDecoratorInterface $serviceDecorator = null;

    /**
     * @var TagManagerInterface|null 标签管理器
     */
    private ?TagManagerInterface $tagManager = null;

    /**
     * @var ExtensionManagerInterface|null 扩展管理器
     */
    private ?ExtensionManagerInterface $extensionManager = null;

    public function __construct()
    {
        $this->init();
    }

    public function init()
    {
        // 初始化扩展管理器并加载扩展
        $this->getExtensionManager()->loadExtensions();
    }

    // —————— Static Facade ———————
    public static function run(): Container|null
    {
        if (self::$singleton === null) {
            self::$singleton = new self();
        }
        return self::$singleton;
    }

    /**
     * @param string $className
     * @param bool $singleton
     * @return object Instance of the requested class
     */
    public static function use(string $className, bool $singleton = true): object
    {
        // Only valid class names are allowed, to prevent arbitrary string injection
        if (!class_exists($className)) {
            throw new InvalidArgumentException("Class '$className' does not exist.");
        }

        $container = self::run();

        // Auto register FactoryDefinition
        if (!isset(self::$globalDefinitions[$className]) && !isset($container->definitions[$className])) {
            $factory = new FactoryDefinition($className);
            $factory->singleton($singleton);

            self::$globalDefinitions[$className] = $factory;
        }

        return $container->get($className);
    }

    public static function reset(): void
    {
        self::$singleton = null;
    }

    // —————— API ——————

    /**
     * Set raw definition for Builder
     * @param string $id
     * @param mixed $definition
     */
    public function setRawDefinition(string $id, mixed $definition): void
    {
        $this->definitions[$id] = $definition;
    }

    public function destroy(): void
    {
        $this->instances = [];
    }

    /**
     * Get instance by id or class name. PSR-11 API
     * @param string $id id of instance or class name
     * @return object 
     * @throws Exception
     */
    public function get(string $id): object
    {
        // 1. 检查实例缓存
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // 2. 获取定义（优先级：本地定义 > 全局定义 > 自动装配）
        $definition = $this->getDefinition($id);

        // 3. 解析实例
        $instance = $this->resolve($definition, $id);

        // 4. 应用装饰器
        if ($this->serviceDecorator && $this->serviceDecorator->hasDecorators($id)) {
            $instance = $this->serviceDecorator->applyDecorators($id, $instance);
        }

        // 5. 缓存策略
        if ($this->shouldCache($definition, $id)) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }

    private function getDefinition(string $id): mixed
    {
        // 优先使用本地定义
        if (isset($this->definitions[$id])) {
            return $this->definitions[$id];
        }

        // 其次使用全局定义
        if (isset(self::$globalDefinitions[$id])) {
            return self::$globalDefinitions[$id];
        }

        // 最后尝试自动装配
        if (class_exists($id)) {
            return new FactoryDefinition($id);
        }

        throw new class ($id) extends Exception implements NotFoundExceptionInterface {
            public function __construct(string $id)
            {
                parent::__construct("Service or class [$id] not found.");
            }
        };
    }


    private function shouldCache($definition, string $id): bool
    {
        // 对于FactoryDefinition，检查是否为单例
        if ($definition instanceof FactoryDefinition) {
            return $definition->isSingleton();
        }

        // 对于其他定义类型，默认缓存
        if ($definition instanceof DefinitionInterface) {
            return true;
        }

        // 自动装配的类默认作为单例缓存
        return true;
    }

    // 全局定义管理
    public static function setGlobalDefinition(string $id, mixed $definition): void
    {
        self::$globalDefinitions[$id] = $definition;
    }

    public static function getGlobalDefinitions(): array
    {
        return self::$globalDefinitions;
    }

    public static function clearGlobalDefinitions(): void
    {
        self::$globalDefinitions = [];
    }


    /**
     * Check if the container has a given definition. PSR-11 API
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        // 检查本地定义
        if (isset($this->definitions[$id])) {
            return true;
        }

        // 检查全局定义
        if (isset(self::$globalDefinitions[$id])) {
            return true;
        }

        // 检查自动装配
        $className = $this->resolveClassName($id);
        return class_exists($className);
    }

    // —————— Utilities ——————

    /**
     * 解析定义为实例
     * 
     * @param mixed $definition 服务定义
     * @param string $id 服务ID（用于错误信息和循环依赖检测）
     * @return mixed 解析后的实例
     * @throws ContainerExceptionInterface
     */
    private function resolve(mixed $definition, string $id): mixed
    {
        // 循环依赖检测
        if (isset($this->resolving[$id])) {
            $chain   = array_keys($this->resolving);
            $chain[] = $id;
            throw new class ('Circular dependency detected: ' . implode(' -> ', $chain)) extends Exception implements ContainerExceptionInterface {
            };
        }

        $this->resolving[$id] = true;

        try {
            $instance = $this->doResolve($definition, $id);
        }
        finally {
            unset($this->resolving[$id]);
        }

        return $instance;
    }

    /**
     * 实际的解析逻辑
     * 
     * @param mixed $definition 服务定义
     * @param string $id 服务ID
     * @return mixed 解析后的实例
     * @throws ContainerExceptionInterface
     */
    private function doResolve(mixed $definition, string $id): mixed
    {
        // 1. DefinitionInterface 实现
        if ($definition instanceof DefinitionInterface) {
            return $definition->resolve($this);
        }

        // 2. 可调用对象（闭包、函数）
        if (is_callable($definition)) {
            return $definition($this);
        }

        // 3. 已实例化的对象
        if (is_object($definition)) {
            return $definition;
        }

        // 4. 字符串类名
        if (is_string($definition)) {
            return $this->resolveStringDefinition($definition, $id);
        }

        // 5. 数组配置
        if (is_array($definition)) {
            return $this->resolveArrayDefinition($definition, $id);
        }

        // 6. 标量值（直接返回）
        if (is_scalar($definition) || is_null($definition)) {
            return $definition;
        }

        // 7. 不支持的定义类型
        throw new class ("Invalid definition type for service [$id]: " . gettype($definition)) extends Exception implements ContainerExceptionInterface {
        };
    }

    /**
     * 解析字符串定义
     * 
     * @param string $definition 类名或服务引用
     * @param string $id 服务ID
     * @return object
     * @throws ContainerExceptionInterface
     */
    private function resolveStringDefinition(string $definition, string $id): object
    {
        // 服务引用：@service_name
        if (str_starts_with($definition, '@')) {
            $serviceId = substr($definition, 1);
            return $this->get($serviceId);
        }

        // 类名解析
        $className = $this->resolveClassName($definition);

        if (!class_exists($className)) {
            throw new class ("Class [$className] not found for service [$id]") extends Exception implements NotFoundExceptionInterface {
            };
        }

        // 创建工厂定义并解析
        $factory = new FactoryDefinition($className);
        return $factory->resolve($this);
    }

    /**
     * 解析数组配置定义
     * 
     * @param array $definition 配置数组
     * @param string $id 服务ID
     * @return object
     * @throws ContainerExceptionInterface
     */
    private function resolveArrayDefinition(array $definition, string $id): object
    {
        // 必须包含 class 键
        if (!isset($definition['class'])) {
            throw new class ("Array definition for service [$id] must contain 'class' key") extends Exception implements ContainerExceptionInterface {
            };
        }

        $className = $definition['class'];

        if (!class_exists($className)) {
            throw new class ("Class [$className] not found for service [$id]") extends Exception implements NotFoundExceptionInterface {
            };
        }

        // 创建工厂定义
        $factory = new FactoryDefinition($className);

        // 设置构造参数
        if (isset($definition['arguments'])) {
            $resolvedArgs = $this->resolveArguments($definition['arguments']);
            $factory->constructor(...$resolvedArgs);
        }

        // 设置单例模式
        if (isset($definition['singleton'])) {
            $factory->singleton($definition['singleton']);
        }

        return $factory->resolve($this);
    }

    /**
     * 解析参数数组
     * 
     * @param array $arguments 参数配置
     * @return array 解析后的参数
     */
    private function resolveArguments(array $arguments): array
    {
        $resolved = [];

        foreach ($arguments as $arg) {
            $resolved[] = $this->resolveArgument($arg);
        }

        return $resolved;
    }

    /**
     * 解析单个参数
     * 
     * @param mixed $arg 参数值
     * @return mixed 解析后的参数
     */
    private function resolveArgument(mixed $arg): mixed
    {
        // Reference 对象
        if ($arg instanceof Reference) {
            return $this->get($arg->getId());
        }

        // DefinitionInterface 实现
        if ($arg instanceof DefinitionInterface) {
            return $arg->resolve($this);
        }

        // 服务引用字符串：@service_name
        if (is_string($arg) && str_starts_with($arg, '@')) {
            $serviceId = substr($arg, 1);
            return $this->get($serviceId);
        }

        // 参数引用字符串：%parameter_name%
        if (is_string($arg) && str_starts_with($arg, '%') && str_ends_with($arg, '%')) {
            return $this->getParameterManager()->resolve($arg);
        }

        // 普通值直接返回
        return $arg;
    }


    /**
     * Resolve class name from id
     * @param string $id id
     * @return string class name
     */
    private function resolveClassName(string $id): string
    {
        if (strpos($id, '\\') !== false) {
            return $id;
        }

        $mappings = [
            'Service'    => 'Services',
            'Component'  => 'Components',
            'Controller' => 'Controllers',
            'Repository' => 'Repositories',
            'Validator'  => 'Validators',
            'Handler'    => 'Handlers',
            'Middleware' => 'Middleware',
            'Provider'   => 'Providers',
            'Factory'    => 'Factories',
            'Builder'    => 'Builders',
        ];

        foreach ($mappings as $suffix => $namespace) {
            if (str_ends_with($id, $suffix)) {
                return __NAMESPACE__ . "\\{$namespace}\\{$id}";
            }
        }

        return $id;
    }

    /**
     * 调试用：获取所有已注册的服务 ID
     * @return string[]
     */
    public function getRegisteredServiceIds(): array
    {
        return array_keys($this->definitions);
    }

    /**
     * 调试用：将所有服务 ID 写入 error_log
     */
    public function logServices(string $prefix = 'Container Services'): void
    {
        $ids = $this->getRegisteredServiceIds();
        error_log("$prefix: " . json_encode($ids, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    // —————— 管理器访问方法 ——————

    /**
     * 获取参数管理器
     * 
     * @return ParameterManagerInterface
     */
    public function getParameterManager(): ParameterManagerInterface
    {
        if ($this->parameterManager === null) {
            $this->parameterManager = new ParameterManager();
        }
        return $this->parameterManager;
    }

    /**
     * 获取服务装饰器
     * 
     * @return ServiceDecoratorInterface
     */
    public function getServiceDecorator(): ServiceDecoratorInterface
    {
        if ($this->serviceDecorator === null) {
            $this->serviceDecorator = new ServiceDecorator();
        }
        return $this->serviceDecorator;
    }

    /**
     * 获取标签管理器
     * 
     * @return TagManagerInterface
     */
    public function getTagManager(): TagManagerInterface
    {
        if ($this->tagManager === null) {
            $this->tagManager = new TagManager();
        }
        return $this->tagManager;
    }

    /**
     * 获取扩展管理器
     * 
     * @return ExtensionManagerInterface
     */
    public function getExtensionManager(): ExtensionManagerInterface
    {
        if ($this->extensionManager === null) {
            $this->extensionManager = new ExtensionManager($this);
        }
        return $this->extensionManager;
    }

    // —————— 便捷方法 ——————

    /**
     * 根据标签获取服务实例
     * 
     * @param string $tag 标签名
     * @return array 服务实例数组 [serviceId => instance]
     */
    public function getServicesByTag(string $tag): array
    {
        $serviceIds = $this->getTagManager()->getByTag($tag);
        $services   = [];

        foreach ($serviceIds as $serviceId) {
            $services[$serviceId] = $this->get($serviceId);
        }

        return $services;
    }

    /**
     * 设置参数
     * 
     * @param string $name 参数名
     * @param mixed $value 参数值
     * @return void
     */
    public function setParameter(string $name, mixed $value): void
    {
        $this->getParameterManager()->set($name, $value);
    }

    /**
     * 获取参数
     * 
     * @param string $name 参数名
     * @param mixed $default 默认值
     * @return mixed 参数值
     */
    public function getParameter(string $name, mixed $default = null): mixed
    {
        return $this->getParameterManager()->get($name, $default);
    }

    /**
     * 为服务添加标签
     * 
     * @param string $serviceId 服务ID
     * @param string ...$tags 标签列表
     * @return void
     */
    public function tagService(string $serviceId, string ...$tags): void
    {
        $this->getTagManager()->tag($serviceId, ...$tags);
    }

    /**
     * 装饰服务
     * 
     * @param string $serviceId 服务ID
     * @param callable $decorator 装饰器函数
     * @return void
     */
    public function decorateService(string $serviceId, callable $decorator): void
    {
        $this->getServiceDecorator()->decorate($serviceId, $decorator);
    }

    /**
     * 注册扩展
     * 
     * @param ContainerExtensionInterface $extension 扩展实例
     * @return void
     */
    public function registerExtension(ContainerExtensionInterface $extension): void
    {
        $this->getExtensionManager()->register($extension);
    }

}
