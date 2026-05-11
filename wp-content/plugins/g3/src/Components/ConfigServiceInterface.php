<?php

namespace JEALER\G3\Components;

/**
 * Configuration Service Interface
 * 
 * 配置服务接口
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
interface ConfigServiceInterface {

    /**
     * 注册组件配置
     * 
     * @param string $componentName 组件名称
     * @param string $optionKey WordPress选项键
     * @param array $defaults 默认配置
     * @return void
     */
    public function registerComponentConfig(string $componentName, string $optionKey, array $defaults): void;

    /**
     * 获取组件配置
     * 
     * @param string $componentName 组件名称
     * @return array 配置数组
     */
    public function getComponentConfig(string $componentName): array;

    /**
     * 更新组件配置
     * 
     * @param string $componentName 组件名称
     * @return array 更新后的配置
     */
    public function updateComponentConfig(string $componentName): array;

    /**
     * 获取单个配置项
     * 
     * @param string $componentName 组件名称
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @return mixed 配置值
     */
    public function getConfigValue(string $componentName, string $key, mixed $default = null): mixed;
}
