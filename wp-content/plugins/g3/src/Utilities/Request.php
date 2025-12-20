<?php
namespace JEALER\G3\Utilities;
use JEALER\G3\Utilities\System;
use WP_REST_Request;
final class Request {

    /**
     * Get REST API URL
     * 
     * 获取REST API URL
     *
     * @param string $router
     * @return string
     */
    public static function restApi(string $router = ''): string
    {
        return get_site_url() . '/wp-json' . $router;
    }

    /**
     * Get AJAX API URL
     * 
     * 获取AJAX API URL
     *
     * @param string $endpoint
     * @return string
     */
    public static function ajaxApi(string $endpoint = ''): string
    {
        return admin_url('admin-ajax.php') . '?action=' . $endpoint;
    }

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

    public static function ajaxSuccess(string $message): void
    {
        wp_send_json_success([
            'message' => $message
        ]);
    }
    public static function ajaxError(string $message): void
    {
        wp_send_json_error([
            'message' => $message
        ]);
    }
    public static function ajaxUpdated(): void
    {
        wp_send_json_success([
            'message' => __('Updated', 'G3')
        ]);
    }
    public static function ajaxForbidden(): void
    {
        wp_send_json_error([
            'message' => __('Forbidden', 'G3')
        ]);
    }
    public static function ajaxFailed(): void
    {
        wp_send_json_error([
            'message' => __('Failed', 'G3')
        ]);
    }
}
