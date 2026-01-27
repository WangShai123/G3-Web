# G3 AOP (Aspect-Oriented Programming) 系统

G3 AOP 系统提供了面向切面编程功能，允许你在不修改原有代码的情况下，通过切面来增强类的功能。

## 核心概念

### 切面 (Aspect)
切面是横切关注点的模块化，如日志记录、性能监控、权限检查等。

### 连接点 (Join Point)
程序执行过程中能够插入切面的点，如方法调用、属性访问等。

### 切点 (Pointcut)
定义在何处应用切面的表达式，支持通配符匹配。

### 通知 (Advice)
在特定连接点执行的代码，包括：
- `before`: 方法执行前
- `after`: 方法执行后
- `after_throw`: 方法抛出异常后
- `before_get`: 属性读取前
- `after_get`: 属性读取后
- `before_set`: 属性设置前
- `after_set`: 属性设置后
- `before_create`: 对象创建前
- `after_create`: 对象创建后

## 使用方式

### 1. 配置文件方式

在 `/config/aop.php` 中配置切面：

```php
<?php
return [
    [
        'type'     => 'method',           // 切面类型：method/property/construct
        'class'    => 'App\\Service\\*',  // 类名模式（支持通配符）
        'method'   => 'save*',           // 方法名模式（支持通配符）
        'advice'   => 'before',          // 通知类型
        'callback' => function($target, $method, $args) {
            error_log("调用方法: " . get_class($target) . "::{$method}");
        }
    ],
    [
        'type'     => 'property',
        'class'    => 'App\\Model\\*',
        'prop'     => 'password',
        'advice'   => 'before_set',
        'callback' => function($target, $prop, $value) {
            // 密码加密
            $reflection = new ReflectionObject($target);
            $property = $reflection->getProperty($prop);
            $property->setAccessible(true);
            $property->setValue($target, password_hash($value, PASSWORD_DEFAULT));
        }
    ]
];
```

### 2. 注解方式

使用 `#[Aop]` 注解直接在类、方法或属性上定义切面：

```php
<?php
use JEALER\G3\Attributes\Aop;

#[Aop('method', 'before', '*', function($target, $method, $args) {
    error_log("类方法调用: " . get_class($target) . "::{$method}");
})]
class UserService 
{
    #[Aop('method', 'before', callback: function($target, $method, $args) {
        error_log("保存用户数据，参数: " . json_encode($args));
    })]
    public function save($data) 
    {
        // 保存逻辑
    }
    
    #[Aop('property', 'before_set', callback: function($target, $prop, $value) {
        error_log("设置属性 {$prop} = {$value}");
    })]
    private $status;
}
```

## API 参考

### Aop 类

#### 静态方法

##### `run(): Aop`
获取 AOP 框架单例实例。

```php
$aop = \JEALER\G3\Aop::run();
```

##### `create(string $className, ...$args): object`
创建对象并织入切面。

```php
$userService = $aop->create(UserService::class, $config);
```

##### `wrap(object $target): object`
包装现有对象，使其支持 AOP。

```php
$wrappedService = $aop->wrap($userService);
```

#### 实例方法

##### `getConfig(): array`
获取当前 AOP 配置。

##### `invoke(object $target, string $method, array $args)`
调用方法并织入切面。

##### `get(object $target, string $prop)`
获取属性值并织入切面。

##### `set(object $target, string $prop, $val)`
设置属性值并织入切面。

### Aop 注解

#### 构造函数参数

- `string $type`: 切面类型 (`method`/`property`/`construct`)
- `string $advice`: 通知类型
- `string $target`: 目标名称（支持通配符，默认 `*`）
- `callable $callback`: 回调函数

## 配置结构

### 配置数组格式

```php
[
    'type'     => 'method|property|construct',  // 必需：切面类型
    'class'    => 'ClassName|Pattern',          // 必需：类名或模式
    'method'   => 'methodName|Pattern',         // 可选：方法名或模式
    'prop'     => 'propertyName|Pattern',       // 可选：属性名或模式
    'advice'   => 'before|after|...',           // 必需：通知类型
    'callback' => callable                      // 必需：回调函数
]
```

### 通配符支持

- `*`: 匹配任意字符
- `?`: 匹配单个字符
- `[abc]`: 匹配字符集中的任意字符
- `[!abc]`: 匹配不在字符集中的字符

示例：
- `App\\Service\\*`: 匹配 App\\Service 命名空间下的所有类
- `save*`: 匹配以 save 开头的所有方法
- `get?`: 匹配 get 后跟一个字符的方法

## 实际应用场景

### 1. 日志记录

```php
// 记录所有 Service 类的方法调用
[
    'type'     => 'method',
    'class'    => '*Service',
    'method'   => '*',
    'advice'   => 'before',
    'callback' => function($target, $method, $args) {
        error_log(sprintf(
            "[AOP] %s::%s called with args: %s",
            get_class($target),
            $method,
            json_encode($args)
        ));
    }
]
```

### 2. 性能监控

```php
// 监控方法执行时间
[
    'type'     => 'method',
    'class'    => 'App\\Controller\\*',
    'method'   => '*',
    'advice'   => 'before',
    'callback' => function($target, $method, $args) {
        $GLOBALS['aop_start_time'] = microtime(true);
    }
],
[
    'type'     => 'method',
    'class'    => 'App\\Controller\\*',
    'method'   => '*',
    'advice'   => 'after',
    'callback' => function($target, $method, $args, $result) {
        $duration = microtime(true) - $GLOBALS['aop_start_time'];
        error_log(sprintf(
            "[PERF] %s::%s took %.4f seconds",
            get_class($target),
            $method,
            $duration
        ));
    }
]
```

### 3. 权限检查

```php
// 检查管理员权限
[
    'type'     => 'method',
    'class'    => 'App\\Admin\\*',
    'method'   => '*',
    'advice'   => 'before',
    'callback' => function($target, $method, $args) {
        if (!current_user_can('manage_options')) {
            throw new Exception('权限不足');
        }
    }
]
```

### 4. 缓存管理

```php
// 自动缓存方法结果
[
    'type'     => 'method',
    'class'    => 'App\\Repository\\*',
    'method'   => 'find*',
    'advice'   => 'before',
    'callback' => function($target, $method, $args) {
        $cacheKey = get_class($target) . '::' . $method . '::' . md5(serialize($args));
        $cached = wp_cache_get($cacheKey);
        if ($cached !== false) {
            return $cached; // 返回缓存结果
        }
    }
],
[
    'type'     => 'method',
    'class'    => 'App\\Repository\\*',
    'method'   => 'find*',
    'advice'   => 'after',
    'callback' => function($target, $method, $args, $result) {
        $cacheKey = get_class($target) . '::' . $method . '::' . md5(serialize($args));
        wp_cache_set($cacheKey, $result, '', 3600); // 缓存1小时
    }
]
```

### 5. 数据验证

```php
// 属性设置时自动验证
[
    'type'     => 'property',
    'class'    => 'App\\Model\\User',
    'prop'     => 'email',
    'advice'   => 'before_set',
    'callback' => function($target, $prop, $value) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('无效的邮箱地址');
        }
    }
]
```

## 最佳实践

### 1. 配置组织
- 将相关的切面配置分组
- 使用描述性的注释
- 考虑性能影响，避免过度使用

### 2. 回调函数设计
- 保持回调函数简洁
- 避免在回调中执行耗时操作
- 合理处理异常

### 3. 通配符使用
- 精确匹配优于通配符匹配
- 避免过于宽泛的匹配模式
- 测试匹配规则的正确性

### 4. 调试技巧
- 使用日志记录切面执行
- 提供开关控制切面启用/禁用
- 监控切面对性能的影响

## 注意事项

1. **性能考虑**: AOP 会增加方法调用的开销，在高频调用的方法上谨慎使用
2. **调试复杂性**: 切面可能使调试变得复杂，建议提供详细的日志
3. **配置管理**: 复杂的切面配置需要良好的文档和测试
4. **异常处理**: 切面中的异常可能影响主业务逻辑
5. **循环依赖**: 避免切面回调中调用被切面拦截的方法

## 扩展开发

如需扩展 AOP 功能，可以：

1. 继承 `Aop` 类添加新的通知类型
2. 实现自定义的匹配规则
3. 添加切面配置的动态加载
4. 集成第三方 AOP 框架

## 相关文件

- `/src/Aop.php`: AOP 核心实现
- `/src/Attributes/Aop.php`: AOP 注解定义
- `/config/aop.php`: AOP 配置文件
- `/src/Aspects/tests/`: AOP 测试文件