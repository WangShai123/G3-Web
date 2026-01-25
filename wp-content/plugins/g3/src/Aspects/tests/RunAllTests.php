<?php

/**
 * AOP Test Runner
 * AOP 测试运行器
 * 
 * 运行所有 AOP 相关的测试
 */

// 模拟 WordPress 环境
if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', dirname(__DIR__, 3));
}

if (!function_exists('get_stylesheet_directory')) {
    function get_stylesheet_directory()
    {
        return '/tmp/theme';
    }
}

/**
 * 测试运行器类
 */
class AopTestRunner {
    private array $testResults = [];
    private int $totalTests = 0;
    private int $passedTests = 0;
    private int $failedTests = 0;

    /**
     * 运行所有测试
     */
    public function runAllTests(): void
    {
        echo "G3 AOP 系统完整测试套件\n";
        echo "========================\n";
        echo "测试时间: " . date('Y-m-d H:i:s') . "\n";
        echo "PHP 版本: " . PHP_VERSION . "\n";
        echo "内存限制: " . ini_get('memory_limit') . "\n";
        echo "========================\n\n";

        $startTime   = microtime(true);
        $startMemory = memory_get_usage();

        // 运行基础功能测试
        $this->runTest('基础功能测试', function () {
            require_once __DIR__ . '/AopTest.php';
            $test = new AopTest();
            ob_start();
            $test->runAllTests();
            $output = ob_get_clean();
            return $this->analyzeTestOutput($output);
        });

        // 运行注解测试
        $this->runTest('注解功能测试', function () {
            require_once __DIR__ . '/AopAnnotationTest.php';
            $test = new AopAnnotationTest();
            ob_start();
            $test->runAllTests();
            $output = ob_get_clean();
            return $this->analyzeTestOutput($output);
        });

        // 运行性能测试
        $this->runTest('性能测试', function () {
            require_once __DIR__ . '/AopPerformanceTest.php';
            $test = new AopPerformanceTest();
            ob_start();
            $test->runAllTests();
            $output = ob_get_clean();
            return $this->analyzePerformanceOutput($output);
        });

        // 运行集成测试
        $this->runTest('集成测试', function () {
            return $this->runIntegrationTests();
        });

        $endTime   = microtime(true);
        $endMemory = memory_get_usage();

        $this->generateFinalReport($endTime - $startTime, $endMemory - $startMemory);
    }

    /**
     * 运行单个测试
     */
    private function runTest(string $testName, callable $testFunction): void
    {
        echo "运行 {$testName}...\n";
        echo str_repeat('-', 50) . "\n";

        $startTime = microtime(true);

        try {
            $result   = $testFunction();
            $duration = microtime(true) - $startTime;

            $this->testResults[$testName] = [
                'status'   => $result['status'],
                'duration' => $duration,
                'details'  => $result['details'] ?? '',
                'errors'   => $result['errors'] ?? []
            ];

            if ($result['status'] === 'passed') {
                echo "✓ {$testName} 通过";
                $this->passedTests++;
            } else {
                echo "✗ {$testName} 失败";
                $this->failedTests++;
                if (!empty($result['errors'])) {
                    echo "\n错误详情:\n";
                    foreach ($result['errors'] as $error) {
                        echo "  - {$error}\n";
                    }
                }
            }

            echo " (耗时: " . number_format($duration * 1000, 2) . " ms)\n";
            $this->totalTests++;

        }
        catch (Exception $e) {
            $duration = microtime(true) - $startTime;

            $this->testResults[$testName] = [
                'status'   => 'error',
                'duration' => $duration,
                'details'  => $e->getMessage(),
                'errors'   => [$e->getMessage()]
            ];

            echo "✗ {$testName} 出现异常: " . $e->getMessage() . "\n";
            $this->failedTests++;
            $this->totalTests++;
        }

        echo "\n";
    }

    /**
     * 分析测试输出
     */
    private function analyzeTestOutput(string $output): array
    {
        $lines  = explode("\n", $output);
        $errors = [];
        $passed = 0;
        $failed = 0;

        foreach ($lines as $line) {
            if (strpos($line, '✓') !== false) {
                $passed++;
            } elseif (strpos($line, '✗') !== false) {
                $failed++;
                $errors[] = trim($line);
            }
        }

        return [
            'status'  => $failed === 0 ? 'passed' : 'failed',
            'details' => "通过: {$passed}, 失败: {$failed}",
            'errors'  => $errors
        ];
    }

    /**
     * 分析性能测试输出
     */
    private function analyzePerformanceOutput(string $output): array
    {
        $lines           = explode("\n", $output);
        $performanceData = [];
        $warnings        = [];

        foreach ($lines as $line) {
            if (strpos($line, '开销:') !== false || strpos($line, 'ms') !== false) {
                $performanceData[] = trim($line);
            }
            if (strpos($line, '⚠') !== false || strpos($line, '✗') !== false) {
                $warnings[] = trim($line);
            }
        }

        return [
            'status'  => empty($warnings) ? 'passed' : 'warning',
            'details' => implode('; ', $performanceData),
            'errors'  => $warnings
        ];
    }

    /**
     * 运行集成测试
     */
    private function runIntegrationTests(): array
    {
        $errors = [];

        try {
            // 测试配置文件加载
            $configFile = WP_PLUGIN_DIR . '/g3/config/aop.php';
            if (!file_exists($configFile)) {
                file_put_contents($configFile, '<?php return [];');
            }

            // 测试 AOP 类加载
            require_once __DIR__ . '/../../Aop.php';
            require_once __DIR__ . '/../../Attributes/Aop.php';

            $aop = JEALER\G3\Aop::run();

            if (!$aop instanceof JEALER\G3\Aop) {
                $errors[] = 'AOP 实例创建失败';
            }

            // 测试配置获取
            $config = $aop->getConfig();
            if (!is_array($config)) {
                $errors[] = '配置获取失败';
            }

            // 测试简单对象创建
            $testObj = $aop->create('stdClass');
            if (!is_object($testObj)) {
                $errors[] = '对象创建失败';
            }

            // 测试对象包装
            $wrappedObj = $aop->wrap(new stdClass());
            if (!is_object($wrappedObj)) {
                $errors[] = '对象包装失败';
            }

            // 清理
            if (file_exists($configFile)) {
                unlink($configFile);
            }

        }
        catch (Exception $e) {
            $errors[] = '集成测试异常: ' . $e->getMessage();
        }

        return [
            'status'  => empty($errors) ? 'passed' : 'failed',
            'details' => empty($errors) ? '所有集成测试通过' : '部分集成测试失败',
            'errors'  => $errors
        ];
    }

    /**
     * 生成最终报告
     */
    private function generateFinalReport(float $totalDuration, int $memoryUsed): void
    {
        echo "========================\n";
        echo "测试完成报告\n";
        echo "========================\n";
        echo "总测试数: {$this->totalTests}\n";
        echo "通过测试: {$this->passedTests}\n";
        echo "失败测试: {$this->failedTests}\n";
        echo "成功率: " . number_format(($this->passedTests / $this->totalTests) * 100, 2) . "%\n";
        echo "总耗时: " . number_format($totalDuration * 1000, 2) . " ms\n";
        echo "内存使用: " . number_format($memoryUsed / 1024, 2) . " KB\n";
        echo "========================\n\n";

        // 详细测试结果
        echo "详细测试结果:\n";
        foreach ($this->testResults as $testName => $result) {
            $status = $result['status'] === 'passed' ? '✓' :
                ($result['status'] === 'warning' ? '⚠' : '✗');

            echo "{$status} {$testName}: {$result['details']}\n";
            echo "   耗时: " . number_format($result['duration'] * 1000, 2) . " ms\n";

            if (!empty($result['errors'])) {
                echo "   错误: " . implode(', ', $result['errors']) . "\n";
            }
            echo "\n";
        }

        // 系统建议
        echo "系统建议:\n";

        if ($this->passedTests === $this->totalTests) {
            echo "✓ AOP 系统运行正常，可以投入生产使用\n";
        } elseif ($this->passedTests / $this->totalTests >= 0.8) {
            echo "⚠ AOP 系统基本正常，但存在一些问题需要关注\n";
        } else {
            echo "✗ AOP 系统存在严重问题，不建议投入生产使用\n";
        }

        echo "\n性能建议:\n";
        if ($totalDuration < 1.0) {
            echo "✓ 测试执行速度良好\n";
        } else {
            echo "⚠ 测试执行较慢，可能存在性能问题\n";
        }

        if ($memoryUsed < 1024 * 1024) { // 1MB
            echo "✓ 内存使用合理\n";
        } else {
            echo "⚠ 内存使用较高，需要优化\n";
        }

        echo "\n使用建议:\n";
        echo "1. 在生产环境中谨慎使用复杂的切面逻辑\n";
        echo "2. 定期监控 AOP 对应用性能的影响\n";
        echo "3. 为切面逻辑编写充分的测试用例\n";
        echo "4. 考虑使用缓存来优化切面匹配性能\n";
        echo "5. 在高并发场景下评估 AOP 的适用性\n";
    }
}

// 运行测试
if (php_sapi_name() === 'cli') {
    $runner = new AopTestRunner();
    $runner->runAllTests();
} else {
    echo "请在命令行环境下运行此测试\n";
}