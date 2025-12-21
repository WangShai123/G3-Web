<?php
namespace JEALER\G3\Utilities;
final class Router {

    /**
     * Extract query_var from query
     * 
     * 从rewrite地址中解析query_var (从 index.php?foo=$matches[1]&bar=... 中解析 query_var 名称)
     * 
     * @param string $query
     * @return array query_var
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function extractQueryVarsFromQuery(string $query): array
    {
        $out   = [];
        $parts = parse_url($query);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $arr);
            foreach ($arr as $k => $v) {
                $out[] = $k;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * Generate route id
     * 
     * 生成稳定的路由 ID，用于 mp_route 分发
     * 
     * @param string $class 
     * @param string $method
     * @param string $regex
     * @param string $query
     * @return string route id
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function routeId(string $class, string $method, string $regex, string $query): string
    {
        return substr(hash('xxh3', $class . '@' . $method . '|' . $regex . '|' . $query), 0, 12);
    }
}