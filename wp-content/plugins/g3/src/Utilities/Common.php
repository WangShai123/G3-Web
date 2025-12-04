<?php
namespace JEALER\G3\Utilities;

use InvalidArgumentException;
use ReflectionClass;
use WP_User;
use WP_Post;

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
     * @since 1.0.0
     * @author Wang Shai
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
     * @since 1.0.0
     * @author Wang Shai
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
     * Get human readable time string
     * 
     * 获取人类可读的时间字符串
     * 
     * @param int $time timestamp
     * @return string human readable time string
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function getHumanTime(int $time = 0): string
    {
        if (!$time) {
            // Default: Post publish time
            $time = get_the_time('U');
        }

        $timeDiff = current_time('timestamp') - $time;

        return match (true) {
            $timeDiff < 60 => \sprintf(
                _n('%s second ago', '%s seconds ago', $timeDiff, 'G3'),
                $timeDiff
            ),
            $timeDiff < 3600 => \sprintf(
                _n('%s minute ago', '%s minutes ago', intdiv($timeDiff, 60), 'G3'),
                intdiv($timeDiff, 60)
            ),
            $timeDiff < 86400 => \sprintf(
                _n('%s hour ago', '%s hours ago', intdiv($timeDiff, 3600), 'G3'),
                intdiv($timeDiff, 3600)
            ),
            $timeDiff < 2592000 => \sprintf(
                _n('%s day ago', '%s days ago', intdiv($timeDiff, 86400), 'G3'),
                intdiv($timeDiff, 86400)
            ),
            default => wp_date(get_option('date_format'), $time),
        };
    }
}