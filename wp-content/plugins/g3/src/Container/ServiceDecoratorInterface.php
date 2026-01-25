<?php
namespace JEALER\G3\Container;

/**
 * Service Decorator Interface
 * 服务装饰器接口
 * 
 * 负责管理服务装饰器，允许在不修改原服务的情况下扩展功能
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
interface ServiceDecoratorInterface {
    /**
     * 装饰服务
     * 
     * @param string $serviceId 服务ID
     * @param callable $decorator 装饰器函数 function(object $service, string $serviceId): object
     * @return void
     */
    public function decorate(string $serviceId, callable $decorator): void;

    /**
     * 获取服务的所有装饰器
     * 
     * @param string $serviceId 服务ID
     * @return array 装饰器数组
     */
    public function getDecorators(string $serviceId): array;

    /**
     * 应用装饰器到服务实例
     * 
     * @param string $serviceId 服务ID
     * @param object $service 原始服务实例
     * @return object 装饰后的服务实例
     */
    public function applyDecorators(string $serviceId, object $service): object;

    /**
     * 检查服务是否有装饰器
     * 
     * @param string $serviceId 服务ID
     * @return bool
     */
    public function hasDecorators(string $serviceId): bool;

    /**
     * 移除服务的所有装饰器
     * 
     * @param string $serviceId 服务ID
     * @return void
     */
    public function removeDecorators(string $serviceId): void;

    /**
     * 获取所有装饰器
     * 
     * @return array
     */
    public function getAllDecorators(): array;
}