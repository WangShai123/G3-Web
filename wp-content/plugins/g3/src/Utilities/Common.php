<?php

namespace JEALER\G3\Utilities;

use Exception;
use InvalidArgumentException;
use ReflectionClass;

/**
 * Common utilities
 * 
 * 通用工具类
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
final class Common {

    /**
     * Easy inside translate
     * 
     * 简单翻译
     * 
     * @param string $key translation key
     * @param string $lang language code 'zh' or 'en'
     * @param array $messages translation messages array
     *  Example:
     *   $messages = [
     *     'en' => [
     *       'hello' => 'hello',
     *     ],
     *     'zh' => [
     *       'hello' => '你好',
     *     ]
     *   ];
     * 
     * @return string translated string
     */
    public static function t(string $key, string $lang, array $messages): string
    {
        return $messages[$lang][$key] ?? $key;
    }

    /**
     * Common singleton factory (support reset)
     * 
     * 通用单例工厂 (支持重置)
     *
     * @param string $className 类名（包含命名空间）
     * @param mixed  $args      构造函数参数（可为 null / 单个值 / 数组）
     * @param bool   $reset     是否重置已有实例，强制重新创建
     * @return object
     * @throws InvalidArgumentException
     */
    public static function singleton(string $className, mixed $args = null, bool $reset = false): object
    {
        static $instances = [];

        if (!class_exists($className)) {
            throw new InvalidArgumentException("Class {$className} does not exist.");
        }

        if ($reset || !isset($instances[$className])) {
            // protected constructor
            if ($args === null) {
                $instances[$className] = new $className();
            } elseif (is_array($args)) {
                $reflect               = new ReflectionClass($className);
                $instances[$className] = $reflect->newInstanceArgs($args);
            } else {
                $instances[$className] = new $className($args);
            }
        }

        return $instances[$className];
    }

    /**
     * Load an extension manually, support .dll and .so files
     * 
     * 手动加载扩展，兼容 .dll 和 .so 文件
     * 
     * @param string $extensionPath 扩展文件路径（包含文件名）
     * @return bool 是否成功加载扩展
     */
    public static function loadExtension(string $extensionPath): bool
    {
        if (!file_exists($extensionPath)) {
            return false;
        }
        // check file extension
        $extension = pathinfo($extensionPath, PATHINFO_EXTENSION);
        if ($extension !== 'dll' && $extension !== 'so') {
            return false;
        }
        // check if extension is loaded
        if (extension_loaded($extensionPath)) {
            return true;
        }
        return dl($extensionPath) !== false;
    }

    /**
     * Generate a random string, support max length 32 bits
     * 
     * 生成随机字符串，支持最大长度 32 位
     * 
     * @param int $length string length
     * @return string random string
     */
    public static function hash(int $length = 8): string
    {
        if ($length > 32) {
            return substr(wp_hash(uniqid('G3')), 0, 32);
        }
        return substr(wp_hash(uniqid('G3')), 0, $length);
    }

    /**
     * Truncate string with ellipsis
     * 
     * 截断字符串并添加省略号
     * 
     * @param string $string The string to truncate
     * @param int $length Maximum length of the string
     * @param string $ellipsis Ellipsis string, defaults to '...'
     * @return string Truncated string with ellipsis
     */
    public static function truncate($string, $length = 50, $ellipsis = '...'): string
    {
        // Use multibyte functions for proper handling of UTF-8 characters
        return mb_strlen($string) > $length
            ? mb_substr($string, 0, $length) . $ellipsis
            : $string;
    }

    /**
     * Truncate HTML string with ellipsis
     * 
     * 截断 HTML 字符串并添加省略号
     * 
     * @param string $html The HTML string to truncate
     * @param int $length Maximum length of the string
     * @param string $more Ellipsis string, defaults to '…'
     * @return string Truncated HTML string with ellipsis
     */
    public static function truncateHtml(string $html, int $length = 80, string $more = '…'): string
    {
        if (mb_strlen(strip_tags($html)) <= $length) {
            return $html;
        }

        // Use wp_html_excerpt to safely truncate (will not cut off in the middle of a tag)
        $truncated = wp_html_excerpt($html, $length, '');

        // Auto close unclosed tags
        $truncated = force_balance_tags($truncated);

        return $truncated . $more;
    }

    /**
     * Check if theme mode is available
     * 
     * 检查主题模式是否可用
     * 
     * @return bool
     */
    public static function themeModeAvailable(): bool
    {
        return !(defined('WP_USE_THEMES') && WP_USE_THEMES === false);
    }

    /**
     * Get Cache Key
     * 
     * 获取缓存键名
     * 
     * @param string $id
     * @param string $subFolder
     * @param string $prefix
     * @return string
     */
    public static function getCacheKey($id, $subFolder = '', $prefix = ''): string
    {
        return ($subFolder ? "{$subFolder}:" : '') . ($prefix ? "{$prefix}_" : '') . "{$id}";
    }

    /**
     * Convert to seconds
     * 
     * 转换为秒
     * 
     * @param string $unit
     * @param int $time
     * @return int|Exception
     */
    public static function toSeconds(int $time, string $unit): int|Exception
    {
        return match ($unit) {
            'second' => $time,
            'minute' => $time * 60,
            'hour' => $time * 60 * 60,
            'day' => $time * 60 * 60 * 24,
            'week' => $time * 60 * 60 * 24 * 7,
            'month' => $time * 60 * 60 * 24 * 30,
            'year' => $time * 60 * 60 * 24 * 365,
            default => throw new Exception('Invalid unit'),
        };
    }

    /**
     * Format seconds into human-readable time string.
     * 
     * 将秒数格式化为人类可读的时间字符串。
     * 
     * @param int $seconds The number of seconds to format.
     * @return string Formatted time string (e.g., "1分30秒", "2天3小时").
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function formatSeconds(int $seconds): string
    {
        if ($seconds < 0) {
            return '0' . __('Second');
        }

        if ($seconds === 0) {
            return '0' . __('Second');
        }

        $units = [
            'year'   => 365 * 24 * 60 * 60,
            'month'  => 30 * 24 * 60 * 60,
            'week'   => 7 * 24 * 60 * 60,
            'day'    => 24 * 60 * 60,
            'hour'   => 60 * 60,
            'minute' => 60,
            'second' => 1,
        ];

        $parts = [];

        foreach ($units as $unitName => $unitValue) {
            if ($seconds >= $unitValue) {
                $count    = intdiv($seconds, $unitValue);
                $seconds %= $unitValue;

                // Map unit name to Chinese label
                $label   = match ($unitName) {
                    'year' => __('Year'),
                    'month' => __('Month'),
                    'week' => __('Week', 'G3'),
                    'day' => __('Day'),
                    'hour' => __('Hour'),
                    'minute' => __('Minute'),
                    'second' => __('Second'),
                    default => '',
                };
                $parts[] = "{$count}{$label}";
            }
        }

        // Optional: Limit to top 2 units for brevity if needed, e.g., array_slice($parts, 0, 2)
        // Currently returning all significant units for precision.

        return implode('', $parts);
    }
}
