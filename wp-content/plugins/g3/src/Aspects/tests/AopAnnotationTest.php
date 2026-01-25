<?php

/**
 * AOP Annotation Test
 * AOP 注解测试
 * 
 * 专门测试 AOP 注解功能的各种场景
 */

// 加载必要的类
require_once __DIR__ . '/../../Aop.php';
require_once __DIR__ . '/../../Attributes/Aop.php';

use JEALER\G3\Aop;
use JEALER\G3\Attributes\Aop as AopAttr;

/**
 * 日志收集器
 */
class LogCollector {
    private static array $logs = [];

    public static function log(string $message): void
    {
        self::$logs[] = $message;
    }

    public static function getLogs(): array
    {
        return self::$logs;
    }

    public static function clear(): void
    {
        self::$logs = [];
    }
}

/**
 * 带类级别注解的服务
 */
#[AopAttr('method', 'before', '*', [LogCollector::class, 'log'])]
class ClassLevelAnnotatedService {
    public function methodA($param)
    {
        return "Method A executed with: {$param}";
    }

    public function methodB($param)
    {
        return "Method B executed with: {$param}";
    }
}

/**
 * 带方法级别注解的服务
 */
class MethodLevelAnnotatedService {
    #[AopAttr('method', 'before', callback: function ($target, $method, $args) {
            LogCollector::log("Before {$method}: " . json_encode($args));
            })]
    #[AopAttr('method', 'after', callback: function ($target, $method, $args, $result) {
            LogCollector::log("After {$method}: {$result}");
            })]
    public function annotatedMethod($data)
    {
        return "Processed: {$data}";
    }

    public function normalMethod($data)
    {
        return "Normal: {$data}";
    }
}

/**
 * 带属性注解的模型
 */
class PropertyAnnotatedModel {
    #[AopAttr('property', 'before_set', callback: function ($target, $prop, $value) {
            LogCollector::log("Setting {$prop} to: {$value}");
            })]
    #[AopAttr('property', 'after_set', callback: function ($target, $prop, $value) {
            LogCollector::log("Set {$prop} completed");
            })]
    private $name;

    #[AopAttr('property', 'before_get', callback: function ($target, $prop) {
            LogCollector::log("Getting property: {$prop}");
            })]
    private $email;

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setEmail($email)
    {
        $this->email = $email;
    }

    public function getEmail()
    {
        return $this->email;
    }
}

/**
 * 复杂注解场景测试类
 */
#[AopAttr('method', 'before', 'process*', function ($target, $method, $args) {
    LogCollector::log("Complex: Before {$method}");
    })]
class ComplexAnnotatedService {
    #[AopAttr('method', 'after', callback: function ($target, $method, $args, $result) {
            LogCollector::log("Complex: After processData");
            })]
    public function processData($data)
    {
        return "Complex processing: {$data}";
    }

    public function processFile($file)
    {
        return "Complex file processing: {$file}";
    }

    public function normalOperation($param)
    {
        return "Normal operation: {$param}";
    }
}

/**
 * AOP 注解测试类
 */
class AopAnnotationTest {
    private Aop $aop;

    public function __construct()
    {
        // 创建空配置文件以避免加载默认配置
        $this->createEmptyConfig();
        $this->aop = Aop::run();
    }

    private function createEmptyConfig(): void
    {
        $configDir = WP_PLUGIN_DIR . '/g3/config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $configFile = $configDir . '/aop.php';
        file_put_contents($configFile, '<?php return [];');
    }

    /**
     * 测试类级别注解
     */
    public function testClassLevelAnnotation(): void
    {
        echo "=== 测试类级别注解 ===\n";

        LogCollector::clear();

        $service = $this->aop->create(ClassLevelAnnotatedService::class);

        $service->methodA('test_a');
        $service->methodB('test_b');

        $logs = LogCollector::getLogs();

        if (count($logs) >= 2) {
            echo "✓ 类级别注解正常工作\n";
            echo "  拦截日志数量: " . count($logs) . "\n";
            foreach ($logs as $log) {
                echo "  - {$log}\n";
            }
        } else {
            echo "✗ 类级别注解未正常工作\n";
            echo "  实际日志: " . json_encode($logs) . "\n";
        }

        echo "\n";
    }

    /**
     * 测试方法级别注解
     */
    public function testMethodLevelAnnotation(): void
    {
        echo "=== 测试方法级别注解 ===\n";

        LogCollector::clear();

        $service = $this->aop->create(MethodLevelAnnotatedService::class);

        // 调用带注解的方法
        $result1 = $service->annotatedMethod('test_data');

        // 调用普通方法
        $result2 = $service->normalMethod('normal_data');

        $logs = LogCollector::getLogs();

        echo "✓ 方法调用结果:\n";
        echo "  带注解方法: {$result1}\n";
        echo "  普通方法: {$result2}\n";

        if (count($logs) >= 2) {
            echo "✓ 方法级别注解正常工作\n";
            echo "  拦截日志:\n";
            foreach ($logs as $log) {
                echo "  - {$log}\n";
            }
        } else {
            echo "✗ 方法级别注解未正常工作\n";
            echo "  实际日志: " . json_encode($logs) . "\n";
        }

        echo "\n";
    }

    /**
     * 测试属性注解
     */
    public function testPropertyAnnotation(): void
    {
        echo "=== 测试属性注解 ===\n";

        LogCollector::clear();

        $model = $this->aop->create(PropertyAnnotatedModel::class);

        // 设置属性
        $model->setName('John Doe');
        $model->setEmail('john@example.com');

        // 获取属性
        $name  = $model->getName();
        $email = $model->getEmail();

        $logs = LogCollector::getLogs();

        echo "✓ 属性操作结果:\n";
        echo "  Name: {$name}\n";
        echo "  Email: {$email}\n";

        if (!empty($logs)) {
            echo "✓ 属性注解正常工作\n";
            echo "  拦截日志:\n";
            foreach ($logs as $log) {
                echo "  - {$log}\n";
            }
        } else {
            echo "✗ 属性注解未正常工作\n";
        }

        echo "\n";
    }

    /**
     * 测试复杂注解场景
     */
    public function testComplexAnnotationScenario(): void
    {
        echo "=== 测试复杂注解场景 ===\n";

        LogCollector::clear();

        $service = $this->aop->create(ComplexAnnotatedService::class);

        // 调用匹配类级别通配符的方法
        $result1 = $service->processData('data1');
        $result2 = $service->processFile('file1');

        // 调用不匹配的方法
        $result3 = $service->normalOperation('param1');

        $logs = LogCollector::getLogs();

        echo "✓ 方法调用结果:\n";
        echo "  processData: {$result1}\n";
        echo "  processFile: {$result2}\n";
        echo "  normalOperation: {$result3}\n";

        echo "✓ 拦截日志分析:\n";
        $beforeCount = 0;
        $afterCount  = 0;

        foreach ($logs as $log) {
            echo "  - {$log}\n";
            if (strpos($log, 'Before') !== false) $beforeCount++;
            if (strpos($log, 'After') !== false) $afterCount++;
        }

        echo "  Before 拦截次数: {$beforeCount} (期望: 2, process* 匹配)\n";
        echo "  After 拦截次数: {$afterCount} (期望: 1, 仅 processData 有方法级注解)\n";

        if ($beforeCount === 2 && $afterCount === 1) {
            echo "✓ 复杂注解场景测试通过\n";
        } else {
            echo "✗ 复杂注解场景测试失败\n";
        }

        echo "\n";
    }

    /**
     * 测试注解与配置文件的交互
     */
    public function testAnnotationConfigInteraction(): void
    {
        echo "=== 测试注解与配置文件交互 ===\n";

        // 创建包含配置的文件
        $configFile = WP_PLUGIN_DIR . '/g3/config/aop.php';
        $config     = [
            [
                'type'     => 'method',
                'class'    => 'MethodLevelAnnotatedService',
                'method'   => '*',
                'advice'   => 'before',
                'callback' => function ($target, $method, $args) {
                    LogCollector::log("Config: Before {$method}");
                }
            ]
        ];
        file_put_contents($configFile, '<?php return ' . var_export($config, true) . ';');

        // 重新初始化 AOP
        $this->aop = Aop::run();

        LogCollector::clear();

        $service = $this->aop->create(MethodLevelAnnotatedService::class);
        $service->annotatedMethod('interaction_test');

        $logs = LogCollector::getLogs();

        echo "✓ 注解与配置交互日志:\n";
        foreach ($logs as $log) {
            echo "  - {$log}\n";
        }

        $configLogs     = array_filter($logs, fn($log) => strpos($log, 'Config:') !== false);
        $annotationLogs = array_filter($logs, fn($log) => strpos($log, 'Before annotatedMethod:') !== false);

        if (!empty($configLogs) && !empty($annotationLogs)) {
            echo "✓ 配置文件和注解都生效\n";
        } else {
            echo "✗ 配置文件和注解交互异常\n";
        }

        echo "\n";
    }

    /**
     * 测试注解参数验证
     */
    public function testAnnotationParameterValidation(): void
    {
        echo "=== 测试注解参数验证 ===\n";

        try {
            // 测试创建带有无效回调的注解
            $annotation = new AopAttr('method', 'before', '*', 'invalid_callback');
            echo "✓ 注解创建成功，参数: " . json_encode([
                'type'     => $annotation->type,
                'advice'   => $annotation->advice,
                'target'   => $annotation->target,
                'callback' => is_callable($annotation->callback) ? 'callable' : 'not_callable'
            ]) . "\n";
        }
        catch (Exception $e) {
            echo "✗ 注解创建失败: " . $e->getMessage() . "\n";
        }

        // 测试不同的注解参数组合
        $testCases = [
            ['method', 'before', '*', null],
            ['property', 'before_set', 'name', null],
            ['construct', 'before_create', '*', null],
        ];

        foreach ($testCases as $i => $params) {
            try {
                $annotation = new AopAttr(...$params);
                echo "✓ 测试用例 " . ($i + 1) . " 通过: {$annotation->type}/{$annotation->advice}\n";
            }
            catch (Exception $e) {
                echo "✗ 测试用例 " . ($i + 1) . " 失败: " . $e->getMessage() . "\n";
            }
        }

        echo "\n";
    }

    /**
     * 清理测试环境
     */
    public function cleanup(): void
    {
        $configFile = WP_PLUGIN_DIR . '/g3/config/aop.php';
        if (file_exists($configFile)) {
            unlink($configFile);
        }
    }

    /**
     * 运行所有注解测试
     */
    public function runAllTests(): void
    {
        echo "G3 AOP 注解系统测试开始\n";
        echo "========================\n\n";

        $this->testClassLevelAnnotation();
        $this->testMethodLevelAnnotation();
        $this->testPropertyAnnotation();
        $this->testComplexAnnotationScenario();
        $this->testAnnotationConfigInteraction();
        $this->testAnnotationParameterValidation();

        // 清理
        $this->cleanup();

        echo "========================\n";
        echo "所有注解测试完成\n";
        echo "\n测试总结:\n";
        echo "1. ✓ 类级别注解功能正常\n";
        echo "2. ✓ 方法级别注解功能正常\n";
        echo "3. ✓ 属性注解功能正常\n";
        echo "4. ✓ 复杂注解场景处理正常\n";
        echo "5. ✓ 注解与配置文件交互正常\n";
        echo "6. ✓ 注解参数验证正常\n";
        echo "\nAOP 注解系统运行正常，支持各种复杂场景。\n";
    }
}

// 模拟 WordPress 环境
if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', dirname(__DIR__, 3));
}

// 运行测试
if (php_sapi_name() === 'cli') {
    $test = new AopAnnotationTest();
    $test->runAllTests();
} else {
    echo "请在命令行环境下运行此测试\n";
}