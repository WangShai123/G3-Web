<?php
namespace JEALER\G3\Core\Container;

/**
 * Extension Manager Interface
 * 
 * 扩展管理器接口。负责管理容器扩展的注册、加载和依赖解析
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
interface ExtensionManagerInterface {

    /**
     * 注册扩展
     * 
     * @param ContainerExtensionInterface $extension 扩展实例
     * @return void
     * @throws \InvalidArgumentException 如果扩展已存在
     */
    public function register(ContainerExtensionInterface $extension): void;

    /**
     * 获取所有已注册的扩展
     * 
     * @return array<string, ContainerExtensionInterface>
     */
    public function getExtensions(): array;

    /**
     * 检查扩展是否启用
     * 
     * @param string $extensionName 扩展名称
     * @return bool
     */
    public function isEnabled(string $extensionName): bool;

    /**
     * 获取指定扩展
     * 
     * @param string $name 扩展名称
     * @return ContainerExtensionInterface|null
     */
    public function getExtension(string $name): ?ContainerExtensionInterface;

    /**
     * 加载所有启用的扩展
     * 
     * @return void
     */
    public function loadExtensions(): void;

    /**
     * 检查扩展依赖是否满足
     * 
     * @param ContainerExtensionInterface $extension 扩展实例
     * @return bool
     */
    public function checkDependencies(ContainerExtensionInterface $extension): bool;

    /**
     * 获取扩展加载顺序（考虑依赖关系）
     * 
     * @return array 按加载顺序排列的扩展名称数组
     */
    public function getLoadOrder(): array;
}
