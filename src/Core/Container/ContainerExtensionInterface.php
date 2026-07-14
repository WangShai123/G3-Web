<?php
namespace JEALER\G3\Core\Container;
use JEALER\G3\Core\Container\Container;

/**
 * Container Extension Interface
 * 
 * 容器扩展接口。定义容器扩展的标准接口，支持可插拔的功能模块
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
interface ContainerExtensionInterface {

    /**
     * 获取扩展名称
     * 
     * @return string 扩展名称（唯一标识）
     */
    public function getName(): string;

    /**
     * 加载扩展到容器
     * 
     * @param Container $container 容器实例
     * @return void
     */
    public function load(Container $container): void;

    /**
     * 检查扩展是否启用
     * 
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * 获取扩展描述
     * 
     * @return string
     */
    public function getDescription(): string;

    /**
     * 获取扩展版本
     * 
     * @return string
     */
    public function getVersion(): string;

    /**
     * 获取扩展依赖
     * 
     * @return array 依赖的扩展名称数组
     */
    public function getDependencies(): array;
}
