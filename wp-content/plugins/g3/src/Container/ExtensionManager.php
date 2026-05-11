<?php

namespace JEALER\G3\Container;

use JEALER\G3\Container\Container;
use InvalidArgumentException;
use RuntimeException;

/**
 * Extension Manager
 * 扩展管理器实现
 * 
 * 支持扩展注册、依赖解析和按序加载
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class ExtensionManager implements ExtensionManagerInterface {

    /**
     * @var array<string, ContainerExtensionInterface> 已注册的扩展
     */
    private array $extensions = [];

    /**
     * @var array<string> 已加载的扩展名称
     */
    private array $loadedExtensions = [];

    /**
     * @var Container 容器实例
     */
    private Container $container;

    /**
     * @var bool 是否已解析依赖关系
     */
    private bool $dependenciesResolved = false;

    /**
     * @var array<string> 扩展加载顺序
     */
    private array $loadOrder = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function register(ContainerExtensionInterface $extension): void
    {
        $name = $extension->getName();

        if (isset($this->extensions[$name])) {
            throw new InvalidArgumentException("[G3 ExtensionManager] Extension '{$name}' is already registered");
        }

        // 验证扩展名称
        if (!$this->isValidExtensionName($name)) {
            throw new InvalidArgumentException("[G3 ExtensionManager] Invalid extension name '{$name}'");
        }

        $this->extensions[$name] = $extension;

        // 重置依赖解析状态
        $this->dependenciesResolved = false;
        $this->loadOrder            = [];

        // 如果扩展启用且依赖满足，立即加载
        if ($extension->isEnabled() && $this->checkDependencies($extension)) {
            $this->loadExtension($extension);
        }
    }

    public function getExtensions(): array
    {
        return $this->extensions;
    }

    public function isEnabled(string $extensionName): bool
    {
        return isset($this->extensions[$extensionName])
            && $this->extensions[$extensionName]->isEnabled();
    }

    public function getExtension(string $name): ?ContainerExtensionInterface
    {
        return $this->extensions[$name] ?? null;
    }

    public function loadExtensions(): void
    {
        // 解析依赖关系
        if (!$this->dependenciesResolved) {
            $this->resolveDependencies();
        }

        // 按依赖顺序加载扩展
        foreach ($this->loadOrder as $extensionName) {
            $extension = $this->extensions[$extensionName];

            if ($extension->isEnabled() && !$this->isLoaded($extensionName)) {
                $this->loadExtension($extension);
            }
        }
    }

    public function checkDependencies(ContainerExtensionInterface $extension): bool
    {
        $dependencies = $extension->getDependencies();

        foreach ($dependencies as $depName) {
            if (!isset($this->extensions[$depName])) {
                return false;
            }

            if (!$this->extensions[$depName]->isEnabled()) {
                return false;
            }
        }

        return true;
    }

    public function getLoadOrder(): array
    {
        if (!$this->dependenciesResolved) {
            $this->resolveDependencies();
        }

        return $this->loadOrder;
    }

    /**
     * 检查扩展是否已加载
     * 
     * @param string $extensionName 扩展名称
     * @return bool
     */
    public function isLoaded(string $extensionName): bool
    {
        return in_array($extensionName, $this->loadedExtensions, true);
    }

    /**
     * 卸载扩展
     * 
     * @param string $extensionName 扩展名称
     * @return bool 是否成功卸载
     */
    public function unload(string $extensionName): bool
    {
        if (!$this->isLoaded($extensionName)) {
            return false;
        }

        // 从已加载列表中移除
        $this->loadedExtensions = array_filter(
            $this->loadedExtensions,
            fn($name) => $name !== $extensionName
        );

        return true;
    }

    /**
     * 获取扩展信息
     * 
     * @param string $extensionName 扩展名称
     * @return array|null 扩展信息
     */
    public function getExtensionInfo(string $extensionName): ?array
    {
        $extension = $this->getExtension($extensionName);

        if (!$extension) {
            return null;
        }

        return [
            'name'             => $extension->getName(),
            'description'      => $extension->getDescription(),
            'version'          => $extension->getVersion(),
            'dependencies'     => $extension->getDependencies(),
            'enabled'          => $extension->isEnabled(),
            'loaded'           => $this->isLoaded($extensionName),
            'dependencies_met' => $this->checkDependencies($extension)
        ];
    }

    /**
     * 获取所有扩展信息
     * 
     * @return array 扩展信息数组
     */
    public function getAllExtensionInfo(): array
    {
        $info = [];

        foreach (array_keys($this->extensions) as $name) {
            $info[$name] = $this->getExtensionInfo($name);
        }

        return $info;
    }

    /**
     * 加载单个扩展
     * 
     * @param ContainerExtensionInterface $extension 扩展实例
     * @return void
     * @throws RuntimeException 如果加载失败
     */
    private function loadExtension(ContainerExtensionInterface $extension): void
    {
        $name = $extension->getName();

        if ($this->isLoaded($name)) {
            return;
        }

        try {
            $extension->load($this->container);
            $this->loadedExtensions[] = $name;
            error_log("[G3 Debug][Container Extension Manager] Extension '{$name}' loaded successfully");
        }
        catch (\Throwable $e) {
            throw new RuntimeException(
                "Failed to load extension '{$name}': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * 解析扩展依赖关系
     * 
     * @return void
     * @throws RuntimeException 如果存在循环依赖或缺失依赖
     */
    private function resolveDependencies(): void
    {
        $this->loadOrder = [];
        $visited         = [];
        $recursionStack  = [];

        foreach ($this->extensions as $name => $extension) {
            if ($extension->isEnabled() && !isset($visited[$name])) {
                $this->topologicalSort($name, $visited, $recursionStack);
            }
        }

        $this->dependenciesResolved = true;
    }

    /**
     * 拓扑排序解决依赖关系
     * 
     * @param string $extensionName 扩展名称
     * @param array $visited 已访问的扩展
     * @param array $recursionStack 递归栈
     * @return void
     * @throws RuntimeException 如果存在循环依赖或缺失依赖
     */
    private function topologicalSort(string $extensionName, array &$visited, array &$recursionStack): void
    {
        if (isset($recursionStack[$extensionName])) {
            throw new RuntimeException("Circular dependency detected in extension: {$extensionName}");
        }

        if (isset($visited[$extensionName])) {
            return;
        }

        $extension                      = $this->extensions[$extensionName];
        $recursionStack[$extensionName] = true;

        // 处理依赖
        foreach ($extension->getDependencies() as $depName) {
            if (!isset($this->extensions[$depName])) {
                throw new RuntimeException("Extension '{$extensionName}' depends on missing extension '{$depName}'");
            }

            if (!$this->extensions[$depName]->isEnabled()) {
                throw new RuntimeException("Extension '{$extensionName}' depends on disabled extension '{$depName}'");
            }

            $this->topologicalSort($depName, $visited, $recursionStack);
        }

        unset($recursionStack[$extensionName]);
        $visited[$extensionName] = true;
        $this->loadOrder[]       = $extensionName;
    }

    /**
     * 验证扩展名称
     * 
     * @param string $name 扩展名称
     * @return bool 是否有效
     */
    private function isValidExtensionName(string $name): bool
    {
        // 扩展名称只能包含字母、数字、下划线和连字符
        return preg_match('/^[a-zA-Z0-9_-]+$/', $name) === 1;
    }

    /**
     * 获取扩展统计信息
     * 
     * @return array 统计信息
     */
    public function getStats(): array
    {
        $total            = count($this->extensions);
        $enabled          = 0;
        $loaded           = count($this->loadedExtensions);
        $withDependencies = 0;

        foreach ($this->extensions as $extension) {
            if ($extension->isEnabled()) {
                $enabled++;
            }

            if (!empty($extension->getDependencies())) {
                $withDependencies++;
            }
        }

        return [
            'total'               => $total,
            'enabled'             => $enabled,
            'loaded'              => $loaded,
            'with_dependencies'   => $withDependencies,
            'load_order_resolved' => $this->dependenciesResolved
        ];
    }

    /**
     * 重新加载所有扩展
     * 
     * @return void
     */
    public function reload(): void
    {
        // 清除加载状态
        $this->loadedExtensions     = [];
        $this->dependenciesResolved = false;
        $this->loadOrder            = [];

        // 重新加载
        $this->loadExtensions();
    }

    /**
     * 导出扩展配置
     * 
     * @return array 可序列化的扩展配置
     */
    public function export(): array
    {
        return [
            'extensions'        => $this->getAllExtensionInfo(),
            'load_order'        => $this->loadOrder,
            'loaded_extensions' => $this->loadedExtensions,
            'stats'             => $this->getStats()
        ];
    }

    /**
     * 验证所有扩展的依赖关系
     * 
     * @return array 验证结果
     */
    public function validateDependencies(): array
    {
        $errors = [];

        foreach ($this->extensions as $name => $extension) {
            if (!$extension->isEnabled()) {
                continue;
            }

            foreach ($extension->getDependencies() as $depName) {
                if (!isset($this->extensions[$depName])) {
                    $errors[] = "Extension '{$name}' depends on missing extension '{$depName}'";
                } elseif (!$this->extensions[$depName]->isEnabled()) {
                    $errors[] = "Extension '{$name}' depends on disabled extension '{$depName}'";
                }
            }
        }

        // 检查循环依赖
        try {
            $this->resolveDependencies();
        }
        catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }

        return $errors;
    }
}
