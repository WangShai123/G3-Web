# G3 Container - 依赖注入容器

G3 Container 是一个符合 PSR-11 标准的现代化依赖注入容器，支持门面模式、实例模式、自动装配、循环依赖检测，以及参数管理、服务装饰器、标签管理和扩展系统等高级功能。

## 核心特性

### 基础功能
- **PSR-11 兼容**：完全符合 PSR-11 容器接口标准
- **门面模式**：`Container::use()` 快速获取服务
- **实例模式**：独立容器实例，支持多容器场景
- **自动装配**：基于类型提示的依赖自动注入
- **循环依赖检测**：自动检测并防止循环依赖
- **智能缓存**：按需缓存，支持单例和原型模式

### 高级功能
- **参数管理**：支持 `%parameter%`、`%env(VAR)%`、`%const(CONST)%`、`%func(name:args)%` 语法
- **服务装饰器**：链式装饰器，支持优先级和条件装饰
- **标签管理**：服务标签分组，支持按标签查找和管理
- **扩展系统**：可插拔扩展架构，支持依赖解析和按序加载
- **配置文件支持**：支持 PHP 配置文件和 ContainerBuilder

## 快速开始

```php
use JEALER\G3\Container;
use JEALER\G3\Services\MailerService;

// 门面模式 - 快速获取服务
$mailer = Container::use(MailerService::class);

// 实例模式 - 创建独立容器
$container = new Container();
$mailer = $container->get(MailerService::class);
```

## 门面模式

### Container::use() - 快速服务获取

```php
// 获取单例（默认）
$service = Container::use(MailerService::class);
$service2 = Container::use(MailerService::class); // 同一个实例

// 获取新实例
$service = Container::use(MailerService::class, false);
```

### 自动类名解析

支持简化类名自动解析到对应命名空间：

```php
// 支持的类型映射
'Service'    => 'Services',      // MailerService → JEALER\G3\Services\MailerService
'Component'  => 'Components',    // PostComponent → JEALER\G3\Components\PostComponent
'Controller' => 'Controllers',   // ApiController → JEALER\G3\Controllers\ApiController
'Repository' => 'Repositories',  // UserRepository → JEALER\G3\Repositories\UserRepository
'Validator'  => 'Validators',    // EmailValidator → JEALER\G3\Validators\EmailValidator
'Handler'    => 'Handlers',      // EventHandler → JEALER\G3\Handlers\EventHandler
'Middleware' => 'Middleware',    // AuthMiddleware → JEALER\G3\Middleware\AuthMiddleware
'Provider'   => 'Providers',     // ConfigProvider → JEALER\G3\Providers\ConfigProvider
'Factory'    => 'Factories',     // UserFactory → JEALER\G3\Factories\UserFactory
'Builder'    => 'Builders',      // QueryBuilder → JEALER\G3\Builders\QueryBuilder

// 使用示例
$repository = Container::use('UserRepository');
$validator = Container::use('EmailValidator');
```

### 全局服务管理

```php
// 手动设置全局定义
Container::setGlobalDefinition('logger', new FactoryDefinition(LoggerService::class));

// 获取所有全局定义
$definitions = Container::getGlobalDefinitions();

// 清空全局定义（测试时有用）
Container::clearGlobalDefinitions();

// 重置门面容器
Container::reset();
```

## 实例模式

### 基本操作

```php
$container = new Container();

// 获取服务
$service = $container->get('MailerService');

// 检查服务是否存在
if ($container->has('MailerService')) {
    $service = $container->get('MailerService');
}

// 清空实例缓存
$container->destroy();
```

### 服务定义

支持多种定义类型：

```php
// 1. FactoryDefinition
$container->setRawDefinition('mailer', new FactoryDefinition(MailerService::class));

// 2. 闭包
$container->setRawDefinition('config', function($container) {
    return new Config(['debug' => true]);
});

// 3. 对象实例
$container->setRawDefinition('cache', new MemoryCache());

// 4. 字符串类名
$container->setRawDefinition('logger', LoggerService::class);

// 5. 服务引用
$container->setRawDefinition('email_logger', '@logger');

// 6. 数组配置
$container->setRawDefinition('database', [
    'class' => DatabaseService::class,
    'arguments' => ['localhost', 3306, '@config'],
    'singleton' => true
]);

// 7. 标量值
$container->setRawDefinition('app_name', 'G3 Plugin');
```

## 依赖注入

### 构造函数自动装配

```php
class EmailService {
    public function __construct(
        private MailerService $mailer,
        private LoggerService $logger
    ) {}
}

// 自动注入依赖
$email = Container::use(EmailService::class);
```

### #[Inject] 属性注入

```php
use JEALER\G3\Attributes\Inject;

class NotificationService {
    public function __construct(
        #[Inject('main_logger')] private LoggerService $logger,
        #[Inject] private MailerService $mailer,
        private string $appName = 'G3'
    ) {}
}
```

## 配置文件支持

### 使用 ContainerBuilder

```php
use JEALER\G3\Container\ContainerBuilder;

$builder = new ContainerBuilder();

// 加载配置文件
$builder->addConfigFile('config/container.php');

// 手动添加定义
$builder->set('custom_service', [
    'class' => CustomService::class,
    'arguments' => ['param1', '@logger'],
    'singleton' => true
]);

$container = $builder->build();
```

## 配置文件支持

### 使用 ContainerBuilder

```php
use JEALER\G3\Container\ContainerBuilder;

$builder = new ContainerBuilder();

// 加载配置文件
$builder->addConfigFile('config/container.php');

// 手动添加定义
$builder->set('custom_service', [
    'class' => CustomService::class,
    'arguments' => ['param1', '@logger'],
    'singleton' => true
]);

$container = $builder->build();
```

### 配置文件格式

```php
// config/container.php
return [
    'parameters' => [
        'db.host' => 'localhost',
        'db.port' => 3306,
        'app.debug' => true,
        'app.env' => '%env(APP_ENV:development)%',
    ],
    
    'services' => [
        'logger' => [
            'class' => LoggerService::class,
            'arguments' => ['%app.debug%'],
            'singleton' => true,
            'tags' => ['service', 'logging']
        ],
        
        'database' => [
            'class' => DatabaseService::class,
            'arguments' => ['%db.host%', '%db.port%', '@logger'],
            'singleton' => true,
            'tags' => ['service', 'database']
        ],
    ],
    
    'decorators' => [
        'logger' => [
            [
                'decorator' => function($logger) { 
                    return new LoggerDecorator($logger); 
                },
                'priority' => 100
            ]
        ]
    ]
];
```

## 循环依赖检测

自动检测并防止循环依赖：

```php
class ServiceA {
    public function __construct(ServiceB $b) {}
}

class ServiceB {
    public function __construct(ServiceA $a) {}
}

try {
    $service = Container::use(ServiceA::class);
} catch (ContainerExceptionInterface $e) {
    echo $e->getMessage(); // "Circular dependency detected: ServiceA -> ServiceB -> ServiceA"
}
```

## 定义优先级

服务解析按以下优先级：

1. **本地定义** - `$container->setRawDefinition()`
2. **全局定义** - `Container::setGlobalDefinition()` 或门面注册
3. **自动装配** - 根据类名自动创建

## 智能缓存

```php
// FactoryDefinition 根据 singleton 设置缓存
$factory = new FactoryDefinition(MailerService::class);
$factory->singleton(true);  // 缓存实例
$factory->singleton(false); // 每次创建新实例

// 其他定义类型默认缓存
// 自动装配的类默认单例缓存
```

## 调试功能

```php
// 获取本地注册的服务
$localServices = $container->getRegisteredServiceIds();

// 获取全局注册的服务
$globalServices = array_keys(Container::getGlobalDefinitions());

// 输出到日志
$container->logServices('Container Debug');

// 获取参数管理器统计
$paramStats = $container->getParameterManager()->export();

// 获取装饰器统计
$decoratorStats = $container->getServiceDecorator()->getStats();

// 获取标签统计
$tagStats = $container->getTagManager()->getTagStats();

// 获取扩展统计
$extensionStats = $container->getExtensionManager()->getStats();
```

## 参数管理

### 基本参数操作

```php
// 设置参数
$container->setParameter('app.name', 'G3 Plugin');
$container->setParameter('db.host', 'localhost');

// 获取参数
$appName = $container->getParameter('app.name');
$dbHost = $container->getParameter('db.host', 'default_host');

// 批量设置参数
$container->getParameterManager()->setParameters([
    'app.debug' => true,
    'app.version' => '1.0.0',
    'db.port' => 3306
]);
```

### 高级参数语法

```php
// 环境变量：%env(VAR_NAME)%
$container->setParameter('db.password', '%env(DB_PASSWORD)%');

// 带默认值的环境变量：%env(VAR_NAME:default)%
$container->setParameter('debug', '%env(APP_DEBUG:false)%');

// 常量：%const(CONST_NAME)%
$container->setParameter('upload_dir', '%const(ABSPATH)%/uploads');

// 函数调用：%func(function_name:arg1,arg2)%
$container->setParameter('current_time', '%func(date:Y-m-d H:i:s)%');

// 嵌套参数：%parameter%
$container->setParameter('env', 'production');
$container->setParameter('db.%env%.host', 'prod-db-server');
```

### 参数验证和依赖检测

```php
$paramManager = $container->getParameterManager();

// 检查循环依赖
$errors = $paramManager->validateParameters();
if (!empty($errors)) {
    foreach ($errors as $error) {
        echo "Parameter error: $error\n";
    }
}

// 获取参数依赖关系
$deps = $paramManager->getParameterDependencies('db.host');
```

## 服务装饰器

### 基本装饰器

```php
// 简单装饰器
$container->decorateService('logger', function($logger, $serviceId) {
    return new LoggerDecorator($logger);
});

// 带优先级的装饰器
$container->getServiceDecorator()->decorateWithPriority(
    'mailer', 
    function($mailer) { return new MailerCache($mailer); }, 
    100
);
```

### 条件装饰器

```php
// 条件装饰器
$container->getServiceDecorator()->decorateIf(
    'database',
    function($db) { return new DatabaseProfiler($db); },
    function($db, $id) { return $container->getParameter('app.debug'); }
);

// 缓存装饰器
$container->getServiceDecorator()->decorateWithCache('expensive_service');
```

### 装饰器管理

```php
$decorator = $container->getServiceDecorator();

// 获取装饰器统计
$stats = $decorator->getStats();

// 移除特定装饰器
$decorator->removeDecorator('service_id', 0);

// 清空所有装饰器
$decorator->clearAll();
```

## 标签管理

### 服务标签

```php
// 为服务添加标签
$container->tagService('mailer', 'service', 'notification');
$container->tagService('sms', 'service', 'notification');
$container->tagService('logger', 'service', 'logging');

// 按标签获取服务
$notificationServices = $container->getServicesByTag('notification');
foreach ($notificationServices as $serviceId => $service) {
    // 使用通知服务...
}
```

### 高级标签操作

```php
$tagManager = $container->getTagManager();

// 多标签查询（交集）
$services = $tagManager->getByTags('service', 'notification');

// 任意标签查询（并集）
$services = $tagManager->getByAnyTag('logging', 'notification');

// 获取标签统计
$stats = $tagManager->getTagStats();

// 查找相似标签
$similar = $tagManager->findSimilarTags('notif', 0.7);

// 获取标签层次结构
$hierarchy = $tagManager->getTagHierarchy('.');
```

### 批量标签管理

```php
// 批量标记服务
$tagManager->batchTag([
    'mailer' => ['service', 'notification'],
    'sms' => ['service', 'notification'],
    'logger' => ['service', 'logging']
]);

// 获取孤立服务（没有标签的服务）
$allServices = $container->getRegisteredServiceIds();
$orphans = $tagManager->getOrphanServices($allServices);
```

## 扩展系统

### 创建扩展

```php
use JEALER\G3\Container\ContainerExtensionInterface;

class LoggingExtension implements ContainerExtensionInterface {
    public function getName(): string {
        return 'logging';
    }
    
    public function getDescription(): string {
        return 'Logging services extension';
    }
    
    public function getVersion(): string {
        return '1.0.0';
    }
    
    public function getDependencies(): array {
        return []; // 依赖的其他扩展
    }
    
    public function isEnabled(): bool {
        return true;
    }
    
    public function load(Container $container): void {
        // 注册服务、参数、装饰器等
        $container->setRawDefinition('logger', LoggerService::class);
        $container->tagService('logger', 'logging');
    }
}
```

### 注册和管理扩展

```php
// 注册扩展
$container->registerExtension(new LoggingExtension());

// 获取扩展信息
$extensionManager = $container->getExtensionManager();
$info = $extensionManager->getExtensionInfo('logging');

// 获取所有扩展统计
$stats = $extensionManager->getStats();

// 验证扩展依赖
$errors = $extensionManager->validateDependencies();
```

## 异常处理

```php
try {
    $service = $container->get('unknown_service');
} catch (Psr\Container\NotFoundExceptionInterface $e) {
    // 服务未找到
} catch (Psr\Container\ContainerExceptionInterface $e) {
    // 容器错误：循环依赖、无效定义等
}
```

## 最佳实践

### 1. 选择合适的模式

```php
// 简单场景：使用门面模式
$service = Container::use(SimpleService::class);

// 复杂场景：使用实例模式
$container = new Container();
$container->setRawDefinition('complex_service', $complexDefinition);
```

### 2. 参数管理最佳实践

```php
// ✅ 推荐：使用环境变量配置
$container->setParameter('db.host', '%env(DB_HOST:localhost)%');

// ✅ 推荐：使用嵌套参数
$container->setParameter('env', '%env(APP_ENV:development)%');
$container->setParameter('cache.%env%.ttl', 3600);

// ❌ 避免：硬编码敏感信息
$container->setParameter('db.password', 'hardcoded_password');
```

### 3. 服务标签组织

```php
// ✅ 推荐：使用层次化标签
$container->tagService('email_service', 'service', 'notification', 'notification.email');
$container->tagService('sms_service', 'service', 'notification', 'notification.sms');

// ✅ 推荐：按功能分组
$container->tagService('mysql_repo', 'repository', 'database');
$container->tagService('redis_cache', 'cache', 'database');
```

### 4. 装饰器使用

```php
// ✅ 推荐：使用优先级控制装饰顺序
$container->getServiceDecorator()->decorateWithPriority(
    'logger', 
    function($logger) { return new LoggerCache($logger); }, 
    100  // 高优先级，先执行
);

$container->getServiceDecorator()->decorateWithPriority(
    'logger', 
    function($logger) { return new LoggerFormatter($logger); }, 
    50   // 低优先级，后执行
);
```

### 5. 扩展开发

```php
// ✅ 推荐：明确扩展依赖关系
class DatabaseExtension implements ContainerExtensionInterface {
    public function getDependencies(): array {
        return ['logging']; // 依赖日志扩展
    }
    
    public function load(Container $container): void {
        // 确保依赖的服务存在
        if (!$container->has('logger')) {
            throw new RuntimeException('Logger service required');
        }
        
        // 注册数据库服务
        $container->setRawDefinition('database', [
            'class' => DatabaseService::class,
            'arguments' => ['@logger']
        ]);
    }
}
```

### 6. 测试中的容器管理

```php
class ContainerTest extends PHPUnit\Framework\TestCase {
    protected function setUp(): void {
        Container::reset();
        Container::clearGlobalDefinitions();
    }
    
    public function testServiceResolution(): void {
        $container = new Container();
        
        // 设置测试参数
        $container->setParameter('test.mode', true);
        
        // 注册测试服务
        $container->setRawDefinition('test_service', TestService::class);
        
        $service = $container->get('test_service');
        $this->assertInstanceOf(TestService::class, $service);
    }
}
```

### 7. 避免循环依赖

```php
// ❌ 避免
class ServiceA {
    public function __construct(ServiceB $b) {}
}
class ServiceB {
    public function __construct(ServiceA $a) {}
}

// ✅ 推荐：使用接口解耦
interface ServiceBInterface {}
class ServiceA {
    public function __construct(ServiceBInterface $b) {}
}
```

## 性能优化

### 1. 延迟加载

```php
// 使用工厂定义延迟创建重型服务
$container->setRawDefinition('heavy_service', function($container) {
    return new HeavyService($container->get('dependency'));
});
```

### 2. 缓存策略

```php
// 对于无状态服务，使用单例模式
$factory = new FactoryDefinition(StatelessService::class);
$factory->singleton(true);

// 对于有状态服务，使用原型模式
$factory = new FactoryDefinition(StatefulService::class);
$factory->singleton(false);
```

### 3. 参数缓存

```php
// 参数管理器自动缓存解析结果
$paramManager = $container->getParameterManager();

// 手动清除缓存（在参数变更后）
$paramManager->clearCache();
```

## 性能特点

- **延迟加载**：服务按需创建，支持工厂模式
- **智能缓存**：多层缓存策略，避免重复实例化
- **循环依赖检测**：轻量级检测机制，防止无限递归
- **全局定义共享**：减少重复定义，提高内存效率
- **参数解析缓存**：参数表达式解析结果自动缓存
- **装饰器优化**：按需应用装饰器，支持条件装饰
- **标签索引**：双向索引提高标签查询性能
- **扩展依赖解析**：拓扑排序确保正确加载顺序

## 架构设计

### 高内聚、低耦合

- **核心容器**：专注于服务解析和依赖注入
- **功能管理器**：参数、装饰器、标签、扩展各自独立
- **接口契约**：所有管理器通过接口定义交互
- **延迟初始化**：管理器按需创建，不影响核心性能

### 可插拔架构

```php
// 所有高级功能都是可选的
$container = new Container(); // 仅核心功能

// 按需使用高级功能
$container->getParameterManager(); // 参数管理
$container->getServiceDecorator(); // 服务装饰
$container->getTagManager();       // 标签管理
$container->getExtensionManager(); // 扩展系统
```

## 与其他框架集成

### WordPress 集成

```php
add_action('init', function() {
    $container = Container::run();
    
    // 设置 WordPress 相关参数
    $container->setParameter('wp.upload_dir', wp_upload_dir()['basedir']);
    $container->setParameter('wp.site_url', site_url());
    
    // 注册 WordPress 服务
    $container->setRawDefinition('wp_mailer', function($container) {
        return new WP_Mail_Service($container->getParameter('wp.site_url'));
    });
    
    $mailer = $container->get('wp_mailer');
});
```

### PSR-11 兼容

```php
function useContainer(ContainerInterface $container) {
    return $container->get('my_service');
}

useContainer(new Container()); // 完全兼容
```

## 错误处理和调试

### 异常类型

```php
try {
    $service = $container->get('unknown_service');
} catch (Psr\Container\NotFoundExceptionInterface $e) {
    // 服务未找到
    error_log("Service not found: " . $e->getMessage());
} catch (Psr\Container\ContainerExceptionInterface $e) {
    // 容器错误：循环依赖、无效定义等
    error_log("Container error: " . $e->getMessage());
}
```

### 调试信息

```php
// 获取完整的容器状态
$debugInfo = [
    'services' => $container->getRegisteredServiceIds(),
    'global_definitions' => array_keys(Container::getGlobalDefinitions()),
    'parameters' => $container->getParameterManager()->export(),
    'decorators' => $container->getServiceDecorator()->export(),
    'tags' => $container->getTagManager()->export(),
    'extensions' => $container->getExtensionManager()->export()
];

error_log('Container Debug: ' . json_encode($debugInfo, JSON_PRETTY_PRINT));
```

## 版本历史

### v1.0.0 - 当前版本
- ✅ PSR-11 兼容的核心容器
- ✅ 门面模式和实例模式
- ✅ 自动装配和循环依赖检测
- ✅ 参数管理系统（支持环境变量、常量、函数调用）
- ✅ 服务装饰器（支持优先级、条件装饰）
- ✅ 标签管理系统（支持层次化标签、相似标签查找）
- ✅ 扩展系统（支持依赖解析、按序加载）
- ✅ 配置文件支持和 ContainerBuilder
- ✅ 完整的调试和统计功能

---

G3 Container 提供了现代 PHP 应用所需的完整依赖注入解决方案，在保持简单易用的同时提供了企业级的功能和性能。通过模块化设计，您可以根据需要选择使用核心功能或完整的高级特性。