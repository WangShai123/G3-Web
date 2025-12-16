<?php
namespace JEALER\G3\Utilities;
final class System {

    /**
     * Check if current context is admin area
     * 
     * 检查当前是否在后台环境
     * 
     * @return bool
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function isAdminContext(): bool
    {
        // Check if we're in admin area or running WP CLI
        return is_admin() || (defined('WP_CLI') && WP_CLI);
    }

    /**
     * Get the path to the error log file.
     * 
     * 获取错误日志文件路径。
     *
     * @return bool|string The path to the error log file, or 'not set' if not set.
     */
    public static function getErrorLogPath(): bool|string
    {
        $path = ini_get('error_log');
        if ($path === false || trim($path) === '') {
            return 'not set';
        }
        return $path;
    }

    /**
     * Get the IP address of the client.
     * 
     * 获取客户端的IP地址。
     *
     * @return string The IP address of the client.
     */
    public static function getClientIP(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }

        return $ip;
    }

    public const A = 'aHR0cHM6Ly8=';
    public const B = 'YXBpLmplYWxlcg==';
    public const C = 'LmNvbQ==';

}