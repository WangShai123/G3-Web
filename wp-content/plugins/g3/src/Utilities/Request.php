<?php
namespace JEALER\G3\Utilities;
use JEALER\G3\Utilities\System;
use WP_REST_Request;
final class Request {

    /**
     * Get standard WordPress REST API URL
     * 
     * 获取标准的 WordPress REST API URL
     *
     * @param string $router
     * @return string
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function restApi(string $router = ''): string
    {
        return get_site_url() . '/wp-json' . $router;
    }

    /**
     * Get standard WordPress AJAX API URL
     * 
     * 获取标准的 WordPress AJAX API URL
     *
     * @param string $action
     * @return string
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function ajaxApi(string $action = ''): string
    {
        return admin_url('admin-ajax.php') . '?action=' . $action;
    }

    /**
     * Get current request count
     * 
     * 获取当前请求计数
     *
     * @param WP_REST_Request $request
     * @return int
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function count(WP_REST_Request $request): int
    {
        $ip       = System::ip();
        $cacheKey = 'rate_limit_' . md5($ip . $request->get_route());
        $count    = get_transient($cacheKey);
        return $count === false ? 0 : $count;
    }

}
