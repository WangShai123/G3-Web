<?php
namespace JEALER\G3\Utilities;

/**
 * Context Manager
 * 
 * 状态管理器，用于组件间数据共享和缓存
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class Context {
    /**
     * Shared data storage
     * 
     * 共享数据存储
     * 
     * @var array
     */
    public static array $data = [];

    /**
     * Cached data storage
     * 
     * 缓存数据存储
     * 
     * @var array
     */
    private static array $cache = [];

    private static array $observers = [];

    private static array $computed = [];

    /**
     * Set shared data
     * 
     * 设置共享数据
     * 
     * @param string $key Data key
     * @param mixed $value Data value
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function set(string $key, mixed $value, bool $listen = false): void
    {
        if (!$listen) {
            self::$data[$key] = $value;
        } else {
            $oldValue         = self::get($key);
            self::$data[$key] = $value;
            self::triggerChange($key, $oldValue, $value);
        }
    }

    /**
     * Get shared data
     * 
     * 获取共享数据
     * 
     * @param string $key Data key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Data value
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$data[$key] ?? $default;
    }

    /**
     * Check if shared data exists
     * 
     * 检查共享数据是否存在
     * 
     * @param string $key Data key
     * @return bool Whether the key exists
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function has(string $key): bool
    {
        return isset(self::$data[$key]);
    }

    /**
     * Remove shared data
     * 
     * 移除共享数据
     * 
     * @param string $key Data key
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function remove(string $key): void
    {
        unset(self::$data[$key]);
    }

    /**
     * Clear all shared data
     * 
     * 清空所有共享数据
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function clear(): void
    {
        self::$data = [];
    }

    /**
     * Set cached data with expiration
     * 
     * 设置带过期时间的缓存数据
     * 
     * @param string $key Cache key
     * @param mixed $value Cache value
     * @param int $ttl Time to live in seconds (default: 3600 seconds = 1 hour)
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function setCache(string $key, mixed $value, int $ttl = 3600): void
    {
        self::$cache[$key] = [
            'value'      => $value,
            'expires_at' => time() + $ttl
        ];
    }

    /**
     * Get cached data
     * 
     * 获取缓存数据
     * 
     * @param string $key Cache key
     * @param mixed $default Default value if key doesn't exist or has expired
     * @return mixed Cache value
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function getCache(string $key, mixed $default = null): mixed
    {
        if (!isset(self::$cache[$key])) {
            return $default;
        }

        // Check if cache has expired
        if (time() > self::$cache[$key]['expires_at']) {
            unset(self::$cache[$key]);
            return $default;
        }

        return self::$cache[$key]['value'];
    }

    /**
     * Check if cached data exists and is still valid
     * 
     * 检查缓存数据是否存在且仍然有效
     * 
     * @param string $key Cache key
     * @return bool Whether the cache exists and is valid
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function hasCache(string $key): bool
    {
        if (!isset(self::$cache[$key])) {
            return false;
        }

        // Check if cache has expired
        if (time() > self::$cache[$key]['expires_at']) {
            unset(self::$cache[$key]);
            return false;
        }

        return true;
    }

    /**
     * Remove cached data
     * 
     * 移除缓存数据
     * 
     * @param string $key Cache key
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function removeCache(string $key): void
    {
        unset(self::$cache[$key]);
    }

    /**
     * Clear all cached data
     * 
     * 清空所有缓存数据
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Get all shared data keys
     * 
     * 获取所有共享数据的键
     * 
     * @return array Array of keys
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function getKeys(): array
    {
        return array_keys(self::$data);
    }

    /**
     * Get all cached data keys
     * 
     * 获取所有缓存数据的键
     * 
     * @return array Array of keys
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function getCacheKeys(): array
    {
        // Clean up expired entries while getting keys
        $keys = [];
        foreach (self::$cache as $key => $data) {
            if (time() <= $data['expires_at']) {
                $keys[] = $key;
            } else {
                // Remove expired entry
                unset(self::$cache[$key]);
            }
        }
        return $keys;
    }

    /**
     * 添加数据变化监听器
     */
    public static function addListener(string $key, callable $callback): void
    {
        if (!isset(self::$observers[$key])) {
            self::$observers[$key] = [];
        }
        self::$observers[$key][] = $callback;
    }

    /**
     * 触发数据变化事件
     */
    private static function triggerChange(string $key, mixed $oldValue, mixed $newValue): void
    {
        if (isset(self::$observers[$key])) {
            foreach (self::$observers[$key] as $callback) {
                $callback($oldValue, $newValue);
            }
        }
    }

    /**
     * 定义计算属性
     */
    public static function computed(string $key, callable $compute, array $dependencies): void
    {
        self::$computed[$key] = [
            'compute'      => $compute,
            'dependencies' => $dependencies
        ];

        // 监听依赖变化
        foreach ($dependencies as $dep) {
            self::addListener($dep, function () use ($key) {
                self::updateComputed($key);
            });
        }
    }

    private static function updateComputed(string $key): void
    {
        if (isset(self::$computed[$key])) {
            $computed = self::$computed[$key];
            $value    = $computed['compute']();
            self::set($key, $value);
        }
    }
}