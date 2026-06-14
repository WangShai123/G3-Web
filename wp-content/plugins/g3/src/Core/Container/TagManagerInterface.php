<?php
namespace JEALER\G3\Core\Container;

/**
 * Tag Manager Interface
 * 
 * 标签管理器接口。负责管理服务标签，支持按标签查找和分组服务
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
interface TagManagerInterface {

    /**
     * 为服务添加标签
     * 
     * @param string $serviceId 服务ID
     * @param string ...$tags 标签列表
     * @return void
     */
    public function tag(string $serviceId, string ...$tags): void;

    /**
     * 根据标签获取服务ID列表
     * 
     * @param string $tag 标签名
     * @return array 服务ID数组
     */
    public function getByTag(string $tag): array;

    /**
     * 获取服务的所有标签
     * 
     * @param string $serviceId 服务ID
     * @return array 标签数组
     */
    public function getTags(string $serviceId): array;

    /**
     * 检查服务是否有指定标签
     * 
     * @param string $serviceId 服务ID
     * @param string $tag 标签名
     * @return bool
     */
    public function hasTag(string $serviceId, string $tag): bool;

    /**
     * 移除服务的所有标签
     * 
     * @param string $serviceId 服务ID
     * @return void
     */
    public function removeServiceTags(string $serviceId): void;

    /**
     * 移除服务的特定标签
     * 
     * @param string $serviceId 服务ID
     * @param string $tag 标签名
     * @return void
     */
    public function removeTag(string $serviceId, string $tag): void;

    /**
     * 获取所有标签
     * 
     * @return array 标签数组
     */
    public function getAllTags(): array;

    /**
     * 获取标签统计信息
     * 
     * @return array 标签 => 服务数量的映射
     */
    public function getTagStats(): array;
}
