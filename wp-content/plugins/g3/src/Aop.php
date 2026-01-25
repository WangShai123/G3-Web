<?php
namespace JEALER\G3;

use ReflectionClass;
use ReflectionObject;
use Throwable;
use JEALER\G3\Attributes\Aop as AopAttr;

class Aop {
    protected static ?Aop $instance = null;
    protected array $config = [];

    /**
     * Run AOP Framework
     * 
     * AOP 框架运行器
     * 
     * @return Aop
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function run(): Aop
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 构造函数：加载配置 + 预置注解扫描缓存
     */
    private function __construct()
    {
        $this->config = $this->initConfig();
    }

    /**
     * Initialize AOP Configuration
     * 
     * 初始化AOP配置，加载插件和主题的AOP配置文件
     * 
     * @return array AOP配置数组
     * @since 1.0.0
     * @author Wang Shai
     */
    private function initConfig(): array
    {
        $pluginConfig    = require_once WP_PLUGIN_DIR . '/g3/config/aop.php' ?: [];
        $themeConfigFile = get_stylesheet_directory() . '/config/aop.php';
        if (file_exists($themeConfigFile)) {
            $themeConfig = require_once $themeConfigFile;
            return array_merge($pluginConfig, is_array($themeConfig) ? $themeConfig : []);
        }
        return $pluginConfig;
    }

    /**
     * Get AOP Configuration
     * 
     * 获取AOP配置
     * 
     * @return array AOP配置数组
     * @since 1.0.0
     * @author Wang Shai
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Create Object with AOP Aspects
     * 
     * 创建对象时织入切面（支持注解扫描）
     * 
     * @param string $className 类名
     * @param mixed ...$args 构造函数参数
     * @return object 创建的对象
     * @since 1.0.0
     * @author Wang Shai
     */
    public function create(string $className, ...$args): object
    {
        // [修改]：扫描注解
        $this->collectAttributes($className);

        $this->trigger('construct', $className, 'before_create', $args);
        $ref = new ReflectionClass($className);
        $obj = $ref->newInstanceArgs($args);
        $this->trigger('construct', $className, 'after_create', $args, $obj);

        return $this->wrap($obj);
    }

    /**
     * Wrap Object with AOP Aspects
     * 
     * 包装对象，拦截方法 & 属性访问
     * 
     * @param object $target 目标对象
     * @return object 包装后的对象
     * @since 1.0.0
     * @author Wang Shai
     */
    public function wrap(object $target): object
    {
        // [修改]：扫描对象注解
        $this->collectAttributes($target);

        $aop = $this;
        return new class ($target, $aop) {
            private $target;
            private $aop;
            public function __construct($target, $aop)
            {
                $this->target = $target;
                $this->aop    = $aop;
            }
            public function __call($m, $a)
            {
                return $this->aop->invoke($this->target, $m, $a);
            }
            public function __get($n)
            {
                return $this->aop->get($this->target, $n);
            }
            public function __set($n, $v)
            {
                return $this->aop->set($this->target, $n, $v);
            }
        };
    }

    /**
     * Invoke Method with AOP Aspects
     * 
     * 调用方法时织入切面
     * 
     * @param object $target 目标对象
     * @param string $method 方法名
     * @param array $args 方法参数
     * @return mixed 方法返回值
     * @since 1.0.0
     * @author Wang Shai
     */
    public function invoke(object $target, string $method, array $args)
    {
        $class   = \get_class($target);
        $advices = $this->match('method', $class, $method);

        foreach ($advices['before'] ?? [] as $cb) $cb($target, $method, $args);

        try {
            $result = $target->$method(...$args);
            foreach ($advices['after'] ?? [] as $cb) $cb($target, $method, $args, $result);
            return $result;
        }
        catch (Throwable $e) {
            foreach ($advices['after_throw'] ?? [] as $cb) $cb($target, $method, $args, $e);
            throw $e;
        }
    }

    /**
     * Get Property Value with AOP Aspects
     * 
     * 获取属性值时织入切面
     * 
     * @param object $target 目标对象
     * @param string $prop 属性名
     * @return mixed 属性值
     * @since 1.0.0
     * @author Wang Shai
     */
    public function get(object $target, string $prop)
    {
        $class   = \get_class($target);
        $advices = $this->match('property', $class, $prop);
        foreach ($advices['before_get'] ?? [] as $cb) $cb($target, $prop);
        $ro = new ReflectionObject($target);
        $p  = $ro->getProperty($prop);
        $p->setAccessible(true);
        $val = $p->getValue($target);
        foreach ($advices['after_get'] ?? [] as $cb) $cb($target, $prop, $val);
        return $val;
    }

    /**
     * Set Property Value with AOP Aspects
     * 
     * 设置属性值时织入切面
     * 
     * @param object $target 目标对象
     * @param string $prop 属性名
     * @param mixed $val 属性值
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public function set(object $target, string $prop, $val)
    {
        $class   = \get_class($target);
        $advices = $this->match('property', $class, $prop);
        foreach ($advices['before_set'] ?? [] as $cb) $cb($target, $prop, $val);
        $ro = new ReflectionObject($target);
        $p  = $ro->getProperty($prop);
        $p->setAccessible(true);
        $p->setValue($target, $val);
        foreach ($advices['after_set'] ?? [] as $cb) $cb($target, $prop, $val);
    }

    /**
     * Trigger AOP Advice
     * 
     * 触发AOP通知
     * 
     * @param string $type 通知类型（method/property）
     * @param string $class 类名
     * @param string $advice 通知类型（before/after/before_get/after_get/before_set/after_set/before_throw/after_throw）
     * @param array $args 方法参数或属性值
     * @param object|null $obj 目标对象（仅在after通知中使用）
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    private function trigger(string $type, string $class, string $advice, array $args, $obj = null)
    {
        foreach ($this->config as $c) {
            if ($c['type'] === $type && $this->classMatch($c['class'], $class) && ($c['advice'] ?? '') === $advice) {
                // ($c['callback'])($class, $args, $obj);
                $c['callback']($class, $args, $obj);
            }
        }
    }

    /**
     * matchAdvice
     * 
     * 匹配切点规则
     * 
     * @param string $type 通知类型（method/property）
     * @param string $class 类名
     * @param string $name 方法名或属性名
     * @return array 匹配的通知回调数组
     * @since 1.0.0
     * @author Wang Shai
     */
    private function match(string $type, string $class, string $name): array
    {
        $result = [];
        foreach ($this->config as $c) {
            if ($c['type'] === $type && $this->classMatch($c['class'], $class) && $this->nameMatch($c[$type === 'method' ? 'method' : 'prop'] ?? '*', $name)) {
                $result[$c['advice']][] = $c['callback'];
            }
        }
        return $result;
    }

    /**
     * classMatch
     * 
     * 匹配类名规则
     * 
     * @param string $pattern 类名模式（支持通配符）
     * @param string $class 类名
     * @return bool 是否匹配
     * @since 1.0.0
     * @author Wang Shai
     */
    private function classMatch(string $pattern, string $class): bool
    {
        return fnmatch($pattern, $class);
    }

    /**
     * nameMatch
     * 
     * 匹配方法名或属性名规则
     * 
     * @param string $pattern 方法名或属性名模式（支持通配符）
     * @param string $name 方法名或属性名
     * @return bool 是否匹配
     * @since 1.0.0
     * @author Wang Shai
     */
    private function nameMatch(string $pattern, string $name): bool
    {
        return fnmatch($pattern, $name);
    }

    /**
     * collectAttributes
     * 
     * 扫描类、方法、属性上的AOP注解
     * 
     * @param object|string $target 目标对象或类名
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    private function collectAttributes(object|string $target): void
    {
        $ref       = is_object($target) ? new ReflectionObject($target) : new ReflectionClass($target);
        $className = $ref->getName();

        // 类注解
        foreach ($ref->getAttributes(AopAttr::class) as $attr) {
            $this->registerAdvice($className, $attr->newInstance());
        }

        // 方法注解
        foreach ($ref->getMethods() as $method) {
            foreach ($method->getAttributes(AopAttr::class) as $attr) {
                $a         = $attr->newInstance();
                $a->target = $method->getName();
                $this->registerAdvice($className, $a);
            }
        }

        // 属性注解
        foreach ($ref->getProperties() as $prop) {
            foreach ($prop->getAttributes(AopAttr::class) as $attr) {
                $a         = $attr->newInstance();
                $a->target = $prop->getName();
                $this->registerAdvice($className, $a);
            }
        }
    }

    /**
     * registerAdvice
     * 
     * 注册AOP通知
     * 
     * @param string $class 类名
     * @param AopAttr $aop AOP注解实例
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    private function registerAdvice(string $class, AopAttr $aop): void
    {
        $this->config[] = [
            'type'     => $aop->type,
            'class'    => $class,
            'method'   => $aop->type === 'method' ? $aop->target : null,
            'prop'     => $aop->type === 'property' ? $aop->target : null,
            'advice'   => $aop->advice,
            'callback' => $aop->callback,
        ];
    }
}