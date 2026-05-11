<?php

namespace JEALER\G3\Container;

/**
 * Tag Manager
 * 
 * 标签管理器实现。支持服务标签管理，提供按标签查找和分组功能
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class TagManager implements TagManagerInterface {

    /**
     * @var array<string, array<string>> 服务ID => 标签数组
     */
    private array $serviceTags = [];

    /**
     * @var array<string, array<string>> 标签 => 服务ID数组
     */
    private array $tagServices = [];

    public function tag(string $serviceId, string ...$tags): void
    {
        foreach ($tags as $tag) {
            $this->addSingleTag($serviceId, $tag);
        }
    }

    public function getByTag(string $tag): array
    {
        return $this->tagServices[$tag] ?? [];
    }

    public function getTags(string $serviceId): array
    {
        return $this->serviceTags[$serviceId] ?? [];
    }

    public function hasTag(string $serviceId, string $tag): bool
    {
        return in_array($tag, $this->getTags($serviceId), true);
    }

    public function removeServiceTags(string $serviceId): void
    {
        $tags = $this->getTags($serviceId);

        foreach ($tags as $tag) {
            $this->removeSingleTag($serviceId, $tag);
        }

        unset($this->serviceTags[$serviceId]);
    }

    public function removeTag(string $serviceId, string $tag): void
    {
        $this->removeSingleTag($serviceId, $tag);
    }

    public function getAllTags(): array
    {
        return array_keys($this->tagServices);
    }

    public function getTagStats(): array
    {
        $stats = [];
        foreach ($this->tagServices as $tag => $services) {
            $stats[$tag] = count($services);
        }
        return $stats;
    }

    /**
     * 添加单个标签
     * 
     * @param string $serviceId 服务ID
     * @param string $tag 标签
     * @return void
     */
    private function addSingleTag(string $serviceId, string $tag): void
    {
        // 添加到服务标签映射
        if (!isset($this->serviceTags[$serviceId])) {
            $this->serviceTags[$serviceId] = [];
        }

        if (!in_array($tag, $this->serviceTags[$serviceId], true)) {
            $this->serviceTags[$serviceId][] = $tag;
        }

        // 添加到标签服务映射
        if (!isset($this->tagServices[$tag])) {
            $this->tagServices[$tag] = [];
        }

        if (!in_array($serviceId, $this->tagServices[$tag], true)) {
            $this->tagServices[$tag][] = $serviceId;
        }
    }

    /**
     * 移除单个标签
     * 
     * @param string $serviceId 服务ID
     * @param string $tag 标签
     * @return void
     */
    private function removeSingleTag(string $serviceId, string $tag): void
    {
        // 从服务标签映射中移除
        if (isset($this->serviceTags[$serviceId])) {
            $this->serviceTags[$serviceId] = array_filter(
                $this->serviceTags[$serviceId],
                fn($t) => $t !== $tag
            );

            if (empty($this->serviceTags[$serviceId])) {
                unset($this->serviceTags[$serviceId]);
            }
        }

        // 从标签服务映射中移除
        if (isset($this->tagServices[$tag])) {
            $this->tagServices[$tag] = array_filter(
                $this->tagServices[$tag],
                fn($id) => $id !== $serviceId
            );

            if (empty($this->tagServices[$tag])) {
                unset($this->tagServices[$tag]);
            }
        }
    }

    /**
     * 按多个标签查找服务（交集）
     * 
     * @param string ...$tags 标签列表
     * @return array 同时拥有所有标签的服务ID数组
     */
    public function getByTags(string ...$tags): array
    {
        if (empty($tags)) {
            return [];
        }

        $result = $this->getByTag($tags[0]);

        for ($i = 1; $i < count($tags); $i++) {
            $result = array_intersect($result, $this->getByTag($tags[$i]));
        }

        return array_values($result);
    }

    /**
     * 按任意标签查找服务（并集）
     * 
     * @param string ...$tags 标签列表
     * @return array 拥有任意标签的服务ID数组
     */
    public function getByAnyTag(string ...$tags): array
    {
        if (empty($tags)) {
            return [];
        }

        $result = [];

        foreach ($tags as $tag) {
            $result = array_merge($result, $this->getByTag($tag));
        }

        return array_values(array_unique($result));
    }

    /**
     * 获取服务数量最多的标签
     * 
     * @param int $limit 返回数量限制
     * @return array 标签 => 服务数量的数组，按服务数量降序排列
     */
    public function getTopTags(int $limit = 10): array
    {
        $stats = $this->getTagStats();
        arsort($stats);

        return array_slice($stats, 0, $limit, true);
    }

    /**
     * 查找相似标签
     * 
     * @param string $tag 标签
     * @param float $threshold 相似度阈值（0-1）
     * @return array 相似标签数组
     */
    public function findSimilarTags(string $tag, float $threshold = 0.7): array
    {
        $similar = [];
        $allTags = $this->getAllTags();

        foreach ($allTags as $existingTag) {
            if ($existingTag === $tag) {
                continue;
            }

            $similarity = $this->calculateSimilarity($tag, $existingTag);

            if ($similarity >= $threshold) {
                $similar[$existingTag] = $similarity;
            }
        }

        // 按相似度降序排列
        arsort($similar);

        return $similar;
    }

    /**
     * 计算两个标签的相似度
     * 
     * @param string $tag1 标签1
     * @param string $tag2 标签2
     * @return float 相似度（0-1）
     */
    private function calculateSimilarity(string $tag1, string $tag2): float
    {
        // 使用 Levenshtein 距离计算相似度
        $maxLen = max(strlen($tag1), strlen($tag2));

        if ($maxLen === 0) {
            return 1.0;
        }

        $distance = levenshtein($tag1, $tag2);

        return 1.0 - ($distance / $maxLen);
    }

    /**
     * 批量标记服务
     * 
     * @param array $tagConfig 标签配置 [serviceId => [tags]]
     * @return void
     */
    public function batchTag(array $tagConfig): void
    {
        foreach ($tagConfig as $serviceId => $tags) {
            if (is_array($tags)) {
                $this->tag($serviceId, ...$tags);
            } elseif (is_string($tags)) {
                $this->tag($serviceId, $tags);
            }
        }
    }

    /**
     * 获取标签层次结构
     * 
     * @param string $separator 层次分隔符（如 '.'）
     * @return array 层次结构数组
     */
    public function getTagHierarchy(string $separator = '.'): array
    {
        $hierarchy = [];

        foreach ($this->getAllTags() as $tag) {
            if (str_contains($tag, $separator)) {
                $parts   = explode($separator, $tag);
                $current = &$hierarchy;

                foreach ($parts as $part) {
                    if (!isset($current[$part])) {
                        $current[$part] = [];
                    }
                    $current = &$current[$part];
                }
            } else {
                $hierarchy[$tag] = [];
            }
        }

        return $hierarchy;
    }

    /**
     * 验证标签名称
     * 
     * @param string $tag 标签名称
     * @return bool 是否有效
     */
    public function isValidTag(string $tag): bool
    {
        // 标签不能为空
        if (empty(trim($tag))) {
            return false;
        }

        // 标签只能包含字母、数字、下划线、连字符和点
        return preg_match('/^[a-zA-Z0-9_.-]+$/', $tag) === 1;
    }

    /**
     * 清空所有标签
     * 
     * @return void
     */
    public function clearAll(): void
    {
        $this->serviceTags = [];
        $this->tagServices = [];
    }

    /**
     * 导出标签配置
     * 
     * @return array 可序列化的标签配置
     */
    public function export(): array
    {
        return [
            'service_tags'   => $this->serviceTags,
            'tag_services'   => $this->tagServices,
            'stats'          => $this->getTagStats(),
            'total_tags'     => count($this->tagServices),
            'total_services' => count($this->serviceTags)
        ];
    }

    /**
     * 从配置导入标签
     * 
     * @param array $config 标签配置
     * @return void
     */
    public function import(array $config): void
    {
        if (isset($config['service_tags'])) {
            foreach ($config['service_tags'] as $serviceId => $tags) {
                $this->tag($serviceId, ...$tags);
            }
        }
    }

    /**
     * 获取孤立服务（没有标签的服务）
     * 
     * @param array $allServiceIds 所有服务ID列表
     * @return array 没有标签的服务ID数组
     */
    public function getOrphanServices(array $allServiceIds): array
    {
        return array_diff($allServiceIds, array_keys($this->serviceTags));
    }

    /**
     * 获取空标签（没有服务的标签）
     * 
     * @return array 空标签数组
     */
    public function getEmptyTags(): array
    {
        return array_keys(array_filter($this->tagServices, fn($services) => empty($services)));
    }
}
