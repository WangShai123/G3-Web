<?php

/**
 * AOP Performance Test
 * AOP 性能测试
 * 
 * 测试 AOP 系统在不同场景下的性能表现
 */

// 加载必要的类
require_once __DIR__ . '/../../Aspects.php';
require_once __DIR__ . '/../../Attributes/Aspects.php';

use JEALER\G3\Aspects\Aspects as Aop;
use JEALER\G3\Attributes\Aspects as AopAttr;

/**
 * 简单的测试服务类
 */
class SimpleService {
    private $counter = 0;

    public function increment()
    {
        $this->counter++;
        return $this->counter;
    }

    public function add($a, $b)
    {
        return $a + $b;
    }

    public function complexOperation($data)
    {
        $result = 0;
        for ($i = 0; $i < 100; $i++) {
            $result += $i * strlen($data);
        }
        return $result;
    }

    public function getCounter()
    {
        return $this->counter;
    }
}

/**
 * 带注解的服务类
 */
#[AopAttr('method', 'before', '*', null)]
class AnnotatedService {
    private $counter = 0;

    #[AopAttr('method', 'after', callback: null)]
    public function increment()
    {
        $this->counter++;
        return $this->counter;
    }

    public function add($a, $b)
    {
        return $a + $b;
    }

    public function getCounter()
    {
        return $this->counter;
    }
}

/**
 * 复杂的服务类（多个切面）
 */
class ComplexService {
    private $data = [];

    public function save($key, $value)
    {
        $this->data[$key] = $value;
        return true;
    }

    public function get($key)
    {
        return $this->data[$key] ?? null;
    }

    public function delete($key)
    {
        unset($this->data[$key]);
        return true;
    }

    public function count()
    {
        return count($this->data);
    }
}

/**
 * AOP 性能测试类
 */
class AopPerformanceTest {
    private Aop $aop;
    private array $results = [];

    public function __construct()
    {
        $this->createPerformanceConfig();
        $this->aop = Aop::run();
    }

    /**
     * 创建性能测试配置
     */
    private function createPerformanceConfig(): void
    {
        $configDir = WP_PLUGIN_DIR . '/g3/config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $configFile = $configDir . '/aop.php';
        $config     = [
            // 简单的前置通知
            [
                'type'     => 'method',
                'class'    => 'ComplexService',
                'method'   => '*',
                'advice'   => 'before',
                'callback' => function ($target, $method, $args) {
                    // 简单的日志记录
                }
            ],
            // 后置通知
            [
                'type'     => 'method',
                'class'    => 'ComplexService',
                'method'   => '*',
                'advice'   => 'after',
                'callback' => function ($target, $method, $args, $result) {
                    // 简单的结果处理
                }
            ],
            // 属性访问拦截
            [
                'type'     => 'property',
                'class'    => 'ComplexService',
                'prop'     => '*',
                'advice'   => 'before_get',
                'callback' => function ($target, $prop) {
                    // 属性访问日志
                }
            ]
        ];

        file_put_contents($configFile, '<?php return ' . var_export($config, true) . ';');
    }

    /**
     * 基准测试：原始对象性能
     */
    public function benchmarkOriginalObject(): void
    {
        echo "=== 基准测试：原始对象性能 ===\n";

        $iterations = 10000;
        $service    = new SimpleService();

        // 测试简单方法调用
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $service->increment();
        }
        $simpleTime = microtime(true) - $startTime;

        // 测试带参数的方法调用
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $service->add($i, $i + 1);
        }
        $paramTime = microtime(true) - $startTime;

        // 测试复杂操作
        $startTime = microtime(true);
        for ($i = 0; $i < 1000; $i++) {
            $service->complexOperation("test_data_{$i}");
        }
        $complexTime = microtime(true) - $startTime;

        $this->results['original'] = [
            'simple'  => $simpleTime,
            'param'   => $paramTime,
            'complex' => $complexTime
        ];

        echo "✓ 原始对象性能基准:\n";
        echo "  简单方法调用 ({$iterations}次): " . number_format($simpleTime * 1000, 2) . " ms\n";
        echo "  带参数方法调用 ({$iterations}次): " . number_format($paramTime * 1000, 2) . " ms\n";
        echo "  复杂操作 (1000次): " . number_format($complexTime * 1000, 2) . " ms\n";
        echo "\n";
    }

    /**
     * 测试 AOP 包装对象性能
     */
    public function benchmarkAopWrappedObject(): void
    {
        echo "=== 测试 AOP 包装对象性能 ===\n";

        $iterations = 10000;
        $service    = $this->aop->wrap(new SimpleService());

        // 测试简单方法调用
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $service->increment();
        }
        $simpleTime = microtime(true) - $startTime;

        // 测试带参数的方法调用
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $service->add($i, $i + 1);
        }
        $paramTime = microtime(true) - $startTime;

        // 测试复杂操作
        $startTime = microtime(true);
        for ($i = 0; $i < 1000; $i++) {
            $service->complexOperation("test_data_{$i}");
        }
        $complexTime = microtime(true) - $startTime;

        $this->results['wrapped'] = [
            'simple'  => $simpleTime,
            'param'   => $paramTime,
            'complex' => $complexTime
        ];

        echo "✓ AOP 包装对象性能:\n";
        echo "  简单方法调用 ({$iterations}次): " . number_format($simpleTime * 1000, 2) . " ms\n";
        echo "  带参数方法调用 ({$iterations}次): " . number_format($paramTime * 1000, 2) . " ms\n";
        echo "  复杂操作 (1000次): " . number_format($complexTime * 1000, 2) . " ms\n";
        echo "\n";
    }

    /**
     * 测试带切面的对象性能
     */
    public function benchmarkAopWithAdvices(): void
    {
        echo "=== 测试带切面的对象性能 ===\n";

        $iterations = 10000;
        $service    = $this->aop->create(ComplexService::class);

        // 测试方法调用（会触发 before 和 after 通知）
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $service->save("key_{$i}", "value_{$i}");
        }
        $saveTime = microtime(true) - $startTime;

        // 测试读取操作
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $service->get("key_" . ($i % 100));
        }
        $getTime = microtime(true) - $startTime;

        // 测试删除操作
        $startTime = microtime(true);
        for ($i = 0; $i < 1000; $i++) {
            $service->delete("key_{$i}");
        }
        $deleteTime = microtime(true) - $startTime;

        $this->results['with_advices'] = [
            'save'   => $saveTime,
            'get'    => $getTime,
            'delete' => $deleteTime
        ];

        echo "✓ 带切面对象性能:\n";
        echo "  保存操作 ({$iterations}次): " . number_format($saveTime * 1000, 2) . " ms\n";
        echo "  读取操作 ({$iterations}次): " . number_format($getTime * 1000, 2) . " ms\n";
        echo "  删除操作 (1000次): " . number_format($deleteTime * 1000, 2) . " ms\n";
        echo "\n";
    }

    /**
     * 测试注解对象性能
     */
    public function benchmarkAnnotatedObject(): void
    {
        echo "=== 测试注解对象性能 ===\n";

        $iterations = 10000;
        $service    = $this->aop->create(AnnotatedService::class);

        // 测试带注解的方法
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $service->increment();
        }
        $annotatedTime = microtime(true) - $startTime;

        // 测试普通方法
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $service->add($i, $i + 1);
        }
        $normalTime = microtime(true) - $startTime;

        $this->results['annotated'] = [
            'annotated' => $annotatedTime,
            'normal'    => $normalTime
        ];

        echo "✓ 注解对象性能:\n";
        echo "  带注解方法 ({$iterations}次): " . number_format($annotatedTime * 1000, 2) . " ms\n";
        echo "  普通方法 ({$iterations}次): " . number_format($normalTime * 1000, 2) . " ms\n";
        echo "\n";
    }

    /**
     * 测试内存使用情况
     */
    public function benchmarkMemoryUsage(): void
    {
        echo "=== 测试内存使用情况 ===\n";

        $iterations = 1000;

        // 测试原始对象内存使用
        $startMemory = memory_get_usage();
        $services    = [];
        for ($i = 0; $i < $iterations; $i++) {
            $services[] = new SimpleService();
        }
        $originalMemory = memory_get_usage() - $startMemory;
        unset($services);

        // 测试 AOP 包装对象内存使用
        $startMemory     = memory_get_usage();
        $wrappedServices = [];
        for ($i = 0; $i < $iterations; $i++) {
            $wrappedServices[] = $this->aop->wrap(new SimpleService());
        }
        $wrappedMemory = memory_get_usage() - $startMemory;
        unset($wrappedServices);

        // 测试 AOP 创建对象内存使用
        $startMemory     = memory_get_usage();
        $createdServices = [];
        for ($i = 0; $i < $iterations; $i++) {
            $createdServices[] = $this->aop->create(SimpleService::class);
        }
        $createdMemory = memory_get_usage() - $startMemory;
        unset($createdServices);

        echo "✓ 内存使用情况 ({$iterations} 个对象):\n";
        echo "  原始对象: " . number_format($originalMemory / 1024, 2) . " KB\n";
        echo "  AOP 包装对象: " . number_format($wrappedMemory / 1024, 2) . " KB\n";
        echo "  AOP 创建对象: " . number_format($createdMemory / 1024, 2) . " KB\n";

        $wrapOverhead   = (($wrappedMemory - $originalMemory) / $originalMemory) * 100;
        $createOverhead = (($createdMemory - $originalMemory) / $originalMemory) * 100;

        echo "  包装对象内存开销: " . number_format($wrapOverhead, 2) . "%\n";
        echo "  创建对象内存开销: " . number_format($createOverhead, 2) . "%\n";
        echo "\n";
    }

    /**
     * 测试不同切面数量的性能影响
     */
    public function benchmarkAdviceCount(): void
    {
        echo "=== 测试不同切面数量的性能影响 ===\n";

        $iterations   = 5000;
        $adviceCounts = [0, 1, 3, 5, 10];

        foreach ($adviceCounts as $count) {
            $this->createAdviceCountConfig($count);
            $aop = Aop::run();

            $service = $aop->create(SimpleService::class);

            $startTime = microtime(true);
            for ($i = 0; $i < $iterations; $i++) {
                $service->increment();
            }
            $executionTime = microtime(true) - $startTime;

            echo "  {$count} 个切面: " . number_format($executionTime * 1000, 2) . " ms\n";
        }

        echo "\n";
    }

    /**
     * 创建指定数量切面的配置
     */
    private function createAdviceCountConfig(int $count): void
    {
        $config = [];

        for ($i = 0; $i < $count; $i++) {
            $config[] = [
                'type'     => 'method',
                'class'    => 'SimpleService',
                'method'   => 'increment',
                'advice'   => 'before',
                'callback' => function ($target, $method, $args) use ($i) {
                    // 简单操作，模拟切面逻辑
                    $dummy = "advice_{$i}";
                }
            ];
        }

        $configFile = WP_PLUGIN_DIR . '/g3/config/aop.php';
        file_put_contents($configFile, '<?php return ' . var_export($config, true) . ';');
    }

    /**
     * 生成性能报告
     */
    public function generatePerformanceReport(): void
    {
        echo "=== 性能测试报告 ===\n";

        if (isset($this->results['original']) && isset($this->results['wrapped'])) {
            $original = $this->results['original'];
            $wrapped  = $this->results['wrapped'];

            echo "AOP 包装开销分析:\n";

            $simpleOverhead  = (($wrapped['simple'] - $original['simple']) / $original['simple']) * 100;
            $paramOverhead   = (($wrapped['param'] - $original['param']) / $original['param']) * 100;
            $complexOverhead = (($wrapped['complex'] - $original['complex']) / $original['complex']) * 100;

            echo "  简单方法调用开销: " . number_format($simpleOverhead, 2) . "%\n";
            echo "  带参数方法开销: " . number_format($paramOverhead, 2) . "%\n";
            echo "  复杂操作开销: " . number_format($complexOverhead, 2) . "%\n";

            $avgOverhead = ($simpleOverhead + $paramOverhead + $complexOverhead) / 3;
            echo "  平均性能开销: " . number_format($avgOverhead, 2) . "%\n";

            if ($avgOverhead < 20) {
                echo "✓ 性能开销在可接受范围内\n";
            } elseif ($avgOverhead < 50) {
                echo "⚠ 性能开销中等，需要注意使用场景\n";
            } else {
                echo "✗ 性能开销较高，建议优化\n";
            }
        }

        echo "\n性能优化建议:\n";
        echo "1. 避免在高频调用的方法上使用复杂切面\n";
        echo "2. 切面回调函数应尽可能简洁\n";
        echo "3. 考虑使用条件切面，避免不必要的拦截\n";
        echo "4. 在生产环境中监控 AOP 对性能的实际影响\n";
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
     * 运行所有性能测试
     */
    public function runAllTests(): void
    {
        echo "G3 AOP 性能测试开始\n";
        echo "===================\n\n";

        $this->benchmarkOriginalObject();
        $this->benchmarkAopWrappedObject();
        $this->benchmarkAopWithAdvices();
        $this->benchmarkAnnotatedObject();
        $this->benchmarkMemoryUsage();
        $this->benchmarkAdviceCount();
        $this->generatePerformanceReport();

        // 清理
        $this->cleanup();

        echo "===================\n";
        echo "性能测试完成\n";
    }
}

// 模拟 WordPress 环境
if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', dirname(__DIR__, 3));
}

// 运行测试
if (php_sapi_name() === 'cli') {
    $test = new AopPerformanceTest();
    $test->runAllTests();
} else {
    echo "请在命令行环境下运行此测试\n";
}