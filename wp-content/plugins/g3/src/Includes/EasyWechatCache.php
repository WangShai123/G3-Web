<?php
namespace JEALER\G3\Includes;

use Psr\SimpleCache\CacheInterface;

class EasyWechatCache implements CacheInterface {
    private $prefix = 'easywechat:';
    private $group = 'easywechat';

    public function get(string $key, mixed $default = null): mixed
    {
        $value = wp_cache_get($this->prefix . $key, $this->group);
        return false !== $value ? $value : $default;
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        if ($ttl instanceof \DateInterval) {
            $ttl = $ttl->s;
        }

        // unit: seconds
        return wp_cache_set($this->prefix . $key, $value, $this->group, $ttl ?: 7200);
    }

    public function delete(string $key): bool
    {
        return wp_cache_delete($this->prefix . $key, $this->group);
    }

    public function clear(): bool
    {
        // WordPress 对象缓存没有统一清除组的方法
        // 在支持的缓存系统中可能需要特殊处理
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }
        return $values;
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        $results = [];
        foreach ($values as $key => $value) {
            $results[$key] = $this->set($key, $value, $ttl);
        }
        return !in_array(false, $results);
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->delete($key);
        }
        return !in_array(false, $results);
    }

    public function has(string $key): bool
    {
        return false !== wp_cache_get($this->prefix . $key, $this->group);
    }
}