<?php
namespace JEALER\G3\Utilities;
use JEALER\G3\Utilities\System;
use WP_REST_Request;
final class Request {
    /**
     * Get current request count
     * 
     * 获取当前请求计数
     *
     * @param WP_REST_Request $request
     * @return int
     */
    public static function count(WP_REST_Request $request): int
    {
        $ip       = System::getClientIP();
        $cacheKey = 'rate_limit_' . md5($ip . $request->get_route());
        $count    = get_transient($cacheKey);
        return $count === false ? 0 : $count;
    }
}
