<?php
namespace JEALER\G3\Core\Container;
use RuntimeException;

/**
 * Parameter Manager
 * 
 * 参数管理器实现。支持参数解析、环境变量、常量等多种参数类型
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class ParameterManager implements ParameterManagerInterface {

    /**
     * @var array 参数存储
     */
    private array $parameters = [];

    /**
     * @var array 解析结果缓存
     */
    private array $resolvedCache = [];

    /**
     * @var int 最大递归深度，防止无限递归
     */
    private int $maxRecursionDepth = 10;

    public function set(string $name, mixed $value): void
    {
        $this->parameters[$name] = $value;

        // 清除相关缓存
        $this->clearRelatedCache($name);
    }

    public function get(string $name, mixed $default = null): mixed
    {
        return $this->parameters[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        return isset($this->parameters[$name]);
    }

    public function resolve(string $expression): mixed
    {
        // 检查缓存
        if (isset($this->resolvedCache[$expression])) {
            return $this->resolvedCache[$expression];
        }

        $result = $this->doResolve($expression, 0);

        // 缓存结果
        $this->resolvedCache[$expression] = $result;

        return $result;
    }

    public function setParameters(array $parameters): void
    {
        foreach ($parameters as $name => $value) {
            $this->set($name, $value);
        }
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function clearCache(): void
    {
        $this->resolvedCache = [];
    }

    /**
     * 实际的解析逻辑
     * 
     * @param string $expression 表达式
     * @param int $depth 递归深度
     * @return mixed 解析结果
     * @throws RuntimeException 如果递归深度超限
     */
    private function doResolve(string $expression, int $depth): mixed
    {
        // 防止无限递归
        if ($depth > $this->maxRecursionDepth) {
            throw new RuntimeException("[G3 ParameterManager] Parameter resolution depth exceeded: {$expression}");
        }

        // 普通参数：%parameter%
        if (preg_match('/^%([^%]+)%$/', $expression, $matches)) {
            $paramName = $matches[1];

            // 支持嵌套参数：%db.%env%.host%
            if (str_contains($paramName, '%')) {
                $paramName = $this->doResolve($paramName, $depth + 1);
            }

            if (!$this->has($paramName)) {
                throw new RuntimeException("[G3 ParameterManager] Parameter '{$paramName}' not found");
            }

            $value = $this->get($paramName);

            // 如果参数值也是表达式，递归解析
            if (is_string($value) && $this->isExpression($value)) {
                return $this->doResolve($value, $depth + 1);
            }

            return $value;
        }

        // 环境变量：%env(VAR_NAME)%
        if (preg_match('/^%env\(([^)]+)\)%$/', $expression, $matches)) {
            $envName = $matches[1];

            // 支持默认值：%env(VAR_NAME:default_value)%
            if (str_contains($envName, ':')) {
                [$envName, $defaultValue] = explode(':', $envName, 2);
                return $_ENV[$envName] ?? $defaultValue;
            }

            if (!isset($_ENV[$envName])) {
                throw new RuntimeException("[G3 ParameterManager] Environment variable '{$envName}' not found");
            }

            return $_ENV[$envName];
        }

        // 常量：%const(CONST_NAME)%
        if (preg_match('/^%const\(([^)]+)\)%$/', $expression, $matches)) {
            $constName = $matches[1];

            if (!defined($constName)) {
                throw new RuntimeException("[G3 ParameterManager] Constant '{$constName}' not defined");
            }

            return constant($constName);
        }

        // 函数调用：%func(function_name:arg1,arg2)%
        if (preg_match('/^%func\(([^:]+):?([^)]*)\)%$/', $expression, $matches)) {
            $funcName = $matches[1];
            $argsStr  = $matches[2] ?? '';

            if (!function_exists($funcName)) {
                throw new RuntimeException("[G3 ParameterManager] Function '{$funcName}' not found");
            }

            $args = $argsStr ? explode(',', $argsStr) : [];

            // 解析参数
            $resolvedArgs = array_map(function ($arg) use ($depth) {
                $arg = trim($arg);
                return $this->isExpression($arg) ? $this->doResolve($arg, $depth + 1) : $arg;
            }, $args);

            return call_user_func_array($funcName, $resolvedArgs);
        }

        // 如果不是表达式，直接返回
        return $expression;
    }

    /**
     * 检查字符串是否为参数表达式
     * 
     * @param string $value 值
     * @return bool
     */
    private function isExpression(string $value): bool
    {
        return preg_match('/^%[^%]*%$/', $value) === 1;
    }

    /**
     * 清除相关缓存
     * 
     * @param string $paramName 参数名
     * @return void
     */
    private function clearRelatedCache(string $paramName): void
    {
        // 清除直接引用该参数的缓存
        $pattern = "/^%{$paramName}%$/";

        foreach (array_keys($this->resolvedCache) as $expression) {
            if (preg_match($pattern, $expression) || str_contains($expression, "%{$paramName}%")) {
                unset($this->resolvedCache[$expression]);
            }
        }
    }

    /**
     * 获取参数依赖关系
     * 
     * @param string $paramName 参数名
     * @return array 依赖的参数列表
     */
    public function getParameterDependencies(string $paramName): array
    {
        if (!$this->has($paramName)) {
            return [];
        }

        $value = $this->get($paramName);

        if (!is_string($value) || !$this->isExpression($value)) {
            return [];
        }

        $dependencies = [];

        // 提取参数依赖
        if (preg_match_all('/%([^%]+)%/', $value, $matches)) {
            foreach ($matches[1] as $dep) {
                if ($dep !== $paramName) { // 避免自引用
                    $dependencies[] = $dep;
                }
            }
        }

        return array_unique($dependencies);
    }

    /**
     * 验证参数配置（检查循环依赖）
     * 
     * @return array 验证结果，包含错误信息
     */
    public function validateParameters(): array
    {
        $errors         = [];
        $visited        = [];
        $recursionStack = [];

        foreach (array_keys($this->parameters) as $paramName) {
            if (!isset($visited[$paramName])) {
                $this->detectCircularDependency($paramName, $visited, $recursionStack, $errors);
            }
        }

        return $errors;
    }

    /**
     * 检测循环依赖
     * 
     * @param string $paramName 参数名
     * @param array $visited 已访问的参数
     * @param array $recursionStack 递归栈
     * @param array $errors 错误列表
     * @return void
     */
    private function detectCircularDependency(string $paramName, array &$visited, array &$recursionStack, array &$errors): void
    {
        $visited[$paramName]        = true;
        $recursionStack[$paramName] = true;

        $dependencies = $this->getParameterDependencies($paramName);

        foreach ($dependencies as $dep) {
            if (!isset($visited[$dep])) {
                $this->detectCircularDependency($dep, $visited, $recursionStack, $errors);
            } elseif (isset($recursionStack[$dep])) {
                $errors[] = "[G3 ParameterManager] Circular dependency detected: {$paramName} -> {$dep}";
            }
        }

        unset($recursionStack[$paramName]);
    }

    /**
     * 导出参数配置
     * 
     * @return array 可序列化的参数配置
     */
    public function export(): array
    {
        return [
            'parameters' => $this->parameters,
            'cache_size' => count($this->resolvedCache),
            'validation' => $this->validateParameters()
        ];
    }

    /**
     * 从配置导入参数
     * 
     * @param array $config 参数配置
     * @return void
     */
    public function import(array $config): void
    {
        if (isset($config['parameters'])) {
            $this->setParameters($config['parameters']);
        }
    }

    /**
     * 根据前缀获取参数组
     * 
     * @param string $prefix 参数前缀
     * @return array 匹配的参数数组
     */
    public function getByPrefix(string $prefix): array
    {
        $result        = [];
        $prefixWithDot = rtrim($prefix, '.') . '.';

        foreach ($this->parameters as $name => $value) {
            if (str_starts_with($name, $prefixWithDot)) {
                // 移除前缀，保留子键名
                $subKey          = substr($name, strlen($prefixWithDot));
                $result[$subKey] = $value;
            }
        }

        return $result;
    }

    /**
     * 批量设置带前缀的参数
     * 
     * @param string $prefix 参数前缀
     * @param array $data 数据数组
     * @return void
     */
    public function setWithPrefix(string $prefix, array $data): void
    {
        $prefixWithDot = rtrim($prefix, '.') . '.';

        foreach ($data as $key => $value) {
            $this->set($prefixWithDot . $key, $value);
        }
    }
}
