<?php
namespace JEALER\G3\Utilities;
use JEALER\G3\Services\SystemService;

/**
 * System Utilities
 * 
 * 系统工具类
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
final class System {

    /**
     * Get environment variable
     * 
     * 获取环境变量
     * 
     * @param string $key Variable key
     * @param mixed $default Default value
     * @return mixed
     */
    public static function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        // 尝试转换常见值类型
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }

        if (is_numeric($value)) {
            // 转换为数字
            return $value + 0;
        }

        return $value;
    }

    /**
     * Check if current context is admin area or running WP CLI
     * 
     * 检查当前是否在后台环境或运行 WP CLI
     * 
     * @return bool
     */
    public static function isAdminContext(): bool
    {
        return is_admin() || (defined('WP_CLI') && constant('WP_CLI') === true);
    }

    /**
     * Check if debug mode is enabled.
     * 
     * 检查调试模式是否启用，依赖 WP_ENVIRONMENT_TYPE ['development' | 'local']
     * 
     * @return bool
     */
    // public static function debug(): bool
    // {
    //     $v = get_option(SystemService::SETTING_OPTION_KEY)['environment'] ?? '';
    //     if (in_array($v, ['development', 'local'])) {
    //         return true;
    //     }
    //     return false;
    // }

    /**
     * Check if debug mode is enabled.
     * 
     * 检查调试模式是否启用，依赖 WP_DEBUG & WP_DEBUG_LOG
     * 
     * @return bool
     */
    public static function debug(): bool
    {
        return defined('WP_DEBUG') && WP_DEBUG && WP_DEBUG_LOG;
    }

    /**
     * Get the path to the error log file.
     * 
     * 获取错误日志文件路径。
     *
     * @return bool|string The path to the error log file, or false if not set.
     */
    public static function errorLogPath(): bool|string
    {
        $path = ini_get('error_log');
        if ($path === false || trim($path) === '') {
            return false;
        }
        return $path;
    }

    /**
     * Get the IP address of the client. Priority is given to IPv4 address. If IPv4 address is not available, IPv6 address is returned.
     * 
     * 获取客户端的IP地址。优先返回IPv4地址。如果不存在IPv4地址，则返回IPv6地址。
     *
     * @return string|bool The IP address of the client or false if unknown.
     */
    public static function ip(): string|bool
    {
        $ipv4 = self::ipv4();
        if ($ipv4 !== false) {
            return $ipv4;
        }
        return self::ipv6();
    }

    /**
     * Get the IPv4 address of the client. If IPv4 address is not available, false is returned.
     * 
     * 获取客户端的IPv4地址。如果不存在IPv4地址，则返回false。
     *
     * @return string|bool The IPv4 address of the client or false if unknown.
     */
    public static function ipv4(): string|bool
    {
        $ip = self::getClientIpRaw();
        if ($ip !== false) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $ip;
            }
            // 如果是IPv6格式的IPv4映射地址，提取IPv4部分
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $long = inet_pton($ip);
                if ($long !== false && substr($long, 0, 12) === "\0\0\0\0\0\0\0\0\0\0\xff\xff") {
                    // IPv4-mapped IPv6 address
                    return inet_ntop(substr($long, 12, 4));
                }
            }
        }
        return false;
    }

    /**
     * Get the IPv6 address of the client. If IPv6 address is not available, false is returned.
     * 
     * 获取客户端的IPv6地址。如果不存在IPv6地址，则返回false。
     *
     * @return string|bool The IPv6 address of the client or false if unknown.
     */
    public static function ipv6(): string|bool
    {
        $ip = self::getClientIpRaw();
        if ($ip !== false && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $ip;
        }
        return false;
    }

    /**
     * Get the raw IP address of the client. If IP address is not available, false is returned.
     * 
     * 获取客户端的原始IP地址。如果不存在IP地址，则返回false。
     *
     * @return string|bool The raw IP address of the client or false if unknown.
     */
    private static function getClientIpRaw(): string|bool
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // 处理通过代理的情况，可能包含多个IP地址
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            // 返回第一个IP地址
            return trim($ips[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return false;
    }

    const APPLE  = 'aHR0cHM6Ly8=';
    const BANANA = 'YXBpLmplYWxlcg==';
    const CAR    = 'LmNvbQ==';

    /**
     * Get the name of the operating system based on PHP_OS_FAMILY.
     * 
     * 获取操作系统的名称。
     *
     * @return string The name of the operating system.
     */
    public static function osName(): string
    {
        return match (PHP_OS_FAMILY) {
            'Linux'   => 'linux',
            'Windows' => 'windows',
            'Darwin'  => 'mac',
            'BSD'     => 'bsd',
            'Solaris' => 'solaris',
            default   => 'unknown',
        };
    }

    /**
     * Get the configuration for the specified key.
     * 
     * 获取指定键的配置。
     *
     * @param string $key The key for the configuration.
     * @param array $default The default value if the key is not found.
     * @return array The configuration.
     */
    public static function config(string $key, $default = []): array
    {
        $mainConfig = G3_PlUGIN_DIR . '/config/' . $key . '.php';
        if (file_exists($mainConfig)) {
            $main = require $mainConfig;
        } else {
            $main = $default;
        }

        $userConfig = get_stylesheet_directory() . '/config/' . $key . '.php';
        if (file_exists($userConfig)) {
            $user = (array) $userConfig;
        } else {
            $user = $default;
        }

        return array_merge($main, $user);
    }
}
