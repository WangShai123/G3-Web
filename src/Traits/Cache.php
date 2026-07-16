<?php
namespace JEALER\G3\Traits;

trait Cache {

    /**
     * Generate a cache key based on an array of parameters
     *
     * 根据参数数组生成缓存键。比 serialize 更紧凑和高效，并对数组的顺序不敏感。
     *
     * @param array $params
     * @return string
     * @since 1.0.0
     * @author Wang Shai
     */
    private function generateArrayCacheKey(array $params): string
    {
        // 排序参数数组的键，以确保顺序不影响缓存键
        ksort($params);

        foreach ($params as &$value) {
            if (is_array($value)) {
                // 检查数组类型
                if (array_keys($value) === range(0, count($value) - 1)) {
                    // 索引数组按值排序
                    sort($value);
                } else {
                    // 关联数组按键排序
                    ksort($value);
                }
            }
        }

        // 转换为紧凑字符串并计算 MD5
        return md5(http_build_query($params));
    }
}
