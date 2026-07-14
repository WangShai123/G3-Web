<?php
/**
 * 对象缓存强化扩展
 * 
 * 路径: wp-content/object-cache.php
 * 支持类型:
 *      1. Redis（phpredis 扩展）
 *      2. Memcached
 *      3. 内存数组
 * 驱动优先级: Redis > Memcached > Array
 * 
 * 注意:
 * 1.您可以在 wp-config.php 定义 WP_CACHE_MEMCACHED_SERVERS 来定制 memcached 集群
 * 2.Redis 值使用 serialize() 存储，读取时 unserialize() 解析，避免与 phpredis 的序列化设置冲突
 * 3.Redis 的 CAS 使用 Lua脚本实现，不需要WATCH/MULTI，更简洁可靠
 * 4.非持久化组数据仅保存在进程内存（$this->cache），不会写入 Memcached/Redis。常用于敏感/短期缓存或请求级缓存。
 *
 * 可选常量（建议在 wp-config.php 中定义）：
 *  - WP_CACHE_KEY_SALT (string) : 缓存 key 前缀盐
 *  - WP_CACHE_MEMCACHED_SERVERS (array) : [['host' => '127.0.0.1', 'port' => 11211, 'weight' => 0], ...]
 *  - WP_CACHE_REDIS_HOST (string) : Redis 主机，默认 127.0.0.1
 *  - WP_CACHE_REDIS_PORT (int) : Redis 端口，默认 6379
 *  - WP_CACHE_REDIS_PASSWORD (string|null) : Redis 密码（可选）
 * 
 */

/** 如果 GET 参数中有 debug=sql，则不启用 object-cache（方便 debug） */
if ((isset($_GET['debug']) && $_GET['debug'] === 'sql')) {
    return;
}

/** 定义盐常量（如果未定义） */
if (!defined('WP_CACHE_KEY_SALT')) {
    define('WP_CACHE_KEY_SALT', '');
}

/** 默认缓存有效期（秒）: 7 天 */
if (!defined('G3_CACHE_DEFAULT_TTL')) {
    define('G3_CACHE_DEFAULT_TTL', 86400 * 7);
}

/**
 * 初始化缓存对象
 */
if (!function_exists('wp_cache_init')) {
    function wp_cache_init()
    {
        global $wp_object_cache;
        $wp_object_cache = new WP_Object_Cache();
    }
}

/* ---------------------------
 * WordPress 缓存函数封装（API）
 * --------------------------- */
if (!function_exists('wp_cache_add')) {
    function wp_cache_add($key, $data, $group = '', $expire = 0)
    {
        global $wp_object_cache;
        return $wp_object_cache->add($key, $data, $group, (int) $expire);
    }
}
if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $data, $group = '', $expire = 0)
    {
        global $wp_object_cache;
        return $wp_object_cache->set($key, $data, $group, (int) $expire);
    }
}
if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '', $force = false, &$found = null)
    {
        global $wp_object_cache;
        return $wp_object_cache->get($key, $group, $force, $found);
    }
}
if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, $group = '')
    {
        global $wp_object_cache;
        return $wp_object_cache->delete($key, $group);
    }
}
if (!function_exists('wp_cache_replace')) {
    function wp_cache_replace($key, $data, $group = '', $expire = 0)
    {
        global $wp_object_cache;
        return $wp_object_cache->replace($key, $data, $group, (int) $expire);
    }
}
if (!function_exists('wp_cache_incr')) {
    function wp_cache_incr($key, $offset = 1, $group = '', $initial_value = 0, $expire = 0)
    {
        global $wp_object_cache;
        return $wp_object_cache->incr($key, $offset, $group, $initial_value, $expire);
    }
}
if (!function_exists('wp_cache_decr')) {
    function wp_cache_decr($key, $offset = 1, $group = '', $initial_value = 0, $expire = 0)
    {
        global $wp_object_cache;
        return $wp_object_cache->decr($key, $offset, $group, $initial_value, $expire);
    }
}
if (!function_exists('wp_cache_flush')) {
    function wp_cache_flush()
    {
        global $wp_object_cache;
        return $wp_object_cache->flush();
    }
}
if (!function_exists('wp_cache_flush_group')) {
    function wp_cache_flush_group($group)
    {
        global $wp_object_cache;
        return $wp_object_cache->flush_group($group);
    }
}
if (!function_exists('wp_cache_close')) {
    function wp_cache_close()
    {
        global $wp_object_cache;
        return $wp_object_cache->close();
    }
}
if (!function_exists('wp_cache_get_stats')) {
    function wp_cache_get_stats()
    {
        global $wp_object_cache;
        return $wp_object_cache->get_stats();
    }
}
if (!function_exists('wp_cache_get_multiple')) {
    function wp_cache_get_multiple($keys, $group = '', $force = false)
    {
        global $wp_object_cache;
        return $wp_object_cache->get_multiple((array) $keys, $group, $force);
    }
}
if (!function_exists('wp_cache_set_multiple')) {
    function wp_cache_set_multiple($data, $group = '', $expire = 0)
    {
        global $wp_object_cache;
        return $wp_object_cache->set_multiple((array) $data, $group, $expire);
    }
}
if (!function_exists('wp_cache_delete_multiple')) {
    function wp_cache_delete_multiple($keys, $group = '')
    {
        global $wp_object_cache;
        return $wp_object_cache->delete_multiple((array) $keys, $group);
    }
}
if (!function_exists('wp_cache_add_global_groups')) {
    function wp_cache_add_global_groups($groups)
    {
        global $wp_object_cache;
        return $wp_object_cache->add_global_groups($groups);
    }
}
if (!function_exists('wp_cache_add_non_persistent_groups')) {
    function wp_cache_add_non_persistent_groups($groups)
    {
        global $wp_object_cache;
        return $wp_object_cache->add_non_persistent_groups($groups);
    }
}
if (!function_exists('wp_cache_switch_to_blog')) {
    function wp_cache_switch_to_blog($blog_id)
    {
        global $wp_object_cache;
        return $wp_object_cache->switch_to_blog((int) $blog_id);
    }
}
if (!function_exists('wp_cache_get_with_cas')) {
    function wp_cache_get_with_cas($key, $group = '', &$cas_token = null)
    {
        global $wp_object_cache;
        return $wp_object_cache->get_with_cas($key, $group, $cas_token);
    }
}
if (!function_exists('wp_cache_cas')) {
    function wp_cache_cas($cas_token, $key, $data, $group = '', $expire = 0)
    {
        global $wp_object_cache;
        if (!is_object($wp_object_cache) || !method_exists($wp_object_cache, 'cas')) {
            return false;
        }
        return $wp_object_cache->cas($cas_token, $key, $data, $group, (int) $expire);
    }
}

/* ---------------------------
 * WP_Object_Cache 实现
 * --------------------------- */
class WP_Object_Cache {
    private $cache  = []; // 内存层缓存（key => value）
    private $driver = 'redis'; // redis|memcached|array
    private $mc     = null; // Memcached 实例
    /** @var Redis|null */
    private   $redis                 = null; // Redis 实例
    private   $blog_prefix           = '';
    private   $global_prefix         = '';
    protected $global_groups         = [];
    protected $non_persistent_groups = [];

    /**
     * 构造
     * 优先检测 Redis > Memcached > Array
     */
    public function __construct()
    {
        // 前缀设置
        if (is_multisite()) {
            $this->blog_prefix   = get_current_blog_id() . ':';
            $this->global_prefix = '';
        } else {
            global $table_prefix;
            $this->blog_prefix   = $table_prefix . ':';
            $this->global_prefix = $table_prefix . ':';
        }

        // Redis (phpredis)
        if (class_exists('Redis')) {
            try {
                $this->redis = new Redis();
                $host        = defined('WP_CACHE_REDIS_HOST') ? WP_CACHE_REDIS_HOST : '127.0.0.1';
                $port        = defined('WP_CACHE_REDIS_PORT') ? WP_CACHE_REDIS_PORT : 6379;
                $this->redis->connect($host, $port);

                if (defined('WP_CACHE_REDIS_PASSWORD') && WP_CACHE_REDIS_PASSWORD) {
                    $this->redis->auth(WP_CACHE_REDIS_PASSWORD);
                }

                $this->driver = 'redis';
                return;
            }
            catch (Exception $e) {
                $this->redis = null;
            }
        }

        // Memcached (可以在 wp-config.php 指定服务器)
        if (class_exists('Memcached')) {
            try {
                $this->mc = new Memcached();
                $this->mc->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);

                // 允许通过常量配置服务器列表
                if (defined('WP_CACHE_MEMCACHED_SERVERS') && is_array(WP_CACHE_MEMCACHED_SERVERS)) {
                    foreach (WP_CACHE_MEMCACHED_SERVERS as $srv) {
                        $host   = $srv['host'] ?? '127.0.0.1';
                        $port   = $srv['port'] ?? 11211;
                        $weight = $srv['weight'] ?? 0;
                        $this->mc->addServer($host, $port, $weight);
                    }
                } else {
                    if (!$this->mc->getServerList()) {
                        $this->mc->addServer('127.0.0.1', 11211);
                    }
                }

                $this->driver = 'memcached';
                return;
            }
            catch (Exception $e) {
                // fallback
                $this->mc = null;
            }
        }

        // fallback
        $this->driver = 'array';
    }

    /* ---------- 辅助：构建存储 key ---------- */
    private function build_key($id, $group = 'default')
    {
        $group  = $group ?: 'default';
        $prefix = isset($this->global_groups[$group]) ? $this->global_prefix : $this->blog_prefix;
        $raw    = WP_CACHE_KEY_SALT . $prefix . $group . ':' . $id;
        // 移除空白字符，Redis/Memcached 的 key 长度也有限制，但这里不裁切
        return preg_replace('/\s+/', '', $raw);
    }

    /* ---------- 内部内存缓存操作 ---------- */
    private function internal_exists($key)
    {
        return isset($this->cache[$key]) && $this->cache[$key] !== false;
    }

    private function get_from_internal($key)
    {
        if (!$this->internal_exists($key)) return false;
        $val = $this->cache[$key];
        if (is_object($val)) {
            return clone $val;
        }
        return $val;
    }

    private function add_to_internal($key, $value)
    {
        if (is_object($value)) $value = clone $value;
        $this->cache[$key] = $value;
        return true;
    }

    private function delete_from_internal($key)
    {
        unset($this->cache[$key]);
    }

    /* ---------- 基本操作：add / set / get / delete ---------- */
    public function add($id, $data, $group = 'default', $expire = 0)
    {
        if ($this->is_non_persistent_group($group)) {
            // non-persistent 组只存在内存层
            $key = $this->build_key($id, $group);
            if ($this->internal_exists($key)) return false;
            return $this->add_to_internal($key, $data);
        }

        $key = $this->build_key($id, $group);
        // 如果存在则返回 false
        $found  = null;
        $exists = $this->get($id, $group, false, $found);
        if ($exists !== false && $found) return false;

        return $this->set($id, $data, $group, $expire);
    }

    public function set($id, $data, $group = 'default', $expire = 0)
    {
        $key = $this->build_key($id, $group);
        // 内存层先写入
        $this->add_to_internal($key, $data);

        switch ($this->driver) {
            case 'redis':
                $val = serialize($data);
                if ((int) $expire > 0) {
                    return $this->redis->setex($key, (int) $expire, $val);
                } else {
                    return $this->redis->set($key, $val);
                }
            case 'memcached':
                // Memcached 支持对象存储
                return $this->mc->set($key, $data, (int) $expire);
            default:
                // 内存模式，直接成功
                return true;
        }
    }

    public function get($id, $group = 'default', $force = false, &$found = null)
    {
        $key = $this->build_key($id, $group);

        // 内存层优先（除非 force）
        if (!$force && $this->internal_exists($key)) {
            $found = true;
            return $this->get_from_internal($key);
        }

        // non-persistent 组不会从持久层获取
        if ($this->is_non_persistent_group($group)) {
            $found = false;
            return false;
        }

        switch ($this->driver) {
            case 'redis':
                $val = $this->redis->get($key);
                if ($val === false || $val === null) {
                    $found = false;
                    $this->delete_from_internal($key);
                    return false;
                }
                $un    = @unserialize($val);
                $found = true;
                $this->add_to_internal($key, $un);
                return $un;
            case 'memcached':
                $val   = $this->mc->get($key);
                $found = ($this->mc->getResultCode() !== Memcached::RES_NOTFOUND);
                if ($found) {
                    $this->add_to_internal($key, $val);
                } else {
                    $this->delete_from_internal($key);
                }
                return $val;
            default:
                $found = false;
                return false;
        }
    }

    public function delete($id, $group = 'default')
    {
        $key = $this->build_key($id, $group);
        $this->delete_from_internal($key);

        if ($this->is_non_persistent_group($group)) {
            return true;
        }

        switch ($this->driver) {
            case 'redis':
                return (bool) $this->redis->del($key);
            case 'memcached':
                return $this->mc->delete($key);
            default:
                return true;
        }
    }

    /* ---------- replace (只有存在时替换) ---------- */
    public function replace($id, $data, $group = 'default', $expire = 0)
    {
        $key = $this->build_key($id, $group);
        // 仅当存在时才替换
        $found  = null;
        $exists = $this->get($id, $group, false, $found);
        if ($exists === false && !$found) return false;

        return $this->set($id, $data, $group, $expire);
    }

    /* ---------- incr / decr ---------- */
    public function incr($id, $offset = 1, $group = 'default', $initial_value = 0, $expire = 0)
    {
        $key = $this->build_key($id, $group);

        if ($this->is_non_persistent_group($group)) {
            $cur  = $this->get_from_internal($key) ?: $initial_value;
            $cur += $offset;
            $this->add_to_internal($key, $cur);
            return $cur;
        }

        switch ($this->driver) {
            case 'redis':
                // Redis incrBy, 若不存在会初始化为 offset
                $res = $this->redis->incrBy($key, $offset);
                $this->add_to_internal($key, $res);
                if ($expire > 0) {
                    $this->redis->expire($key, (int) $expire);
                }
                return $res;
            case 'memcached':
                // memcached->increment 支持 initial value in some impls; 保守做法：先使用 add 确保存在
                $this->mc->add($key, $initial_value);
                $res = $this->mc->increment($key, $offset);
                if ($res === false) {
                    // Memcached 执行失败时，fallback 到 get/set
                    $val = $this->mc->get($key);
                    $val = ($val === false ? $initial_value : $val) + $offset;
                    $this->mc->set($key, $val);
                    $this->add_to_internal($key, $val);
                    return $val;
                }
                $this->add_to_internal($key, $res);
                return $res;
            default:
                $cur  = $this->get_from_internal($key) ?: $initial_value;
                $cur += $offset;
                $this->add_to_internal($key, $cur);
                return $cur;
        }
    }

    public function decr($id, $offset = 1, $group = 'default', $initial_value = 0, $expire = 0)
    {
        $key = $this->build_key($id, $group);

        if ($this->is_non_persistent_group($group)) {
            $cur  = $this->get_from_internal($key) ?: $initial_value;
            $cur -= $offset;
            if ($cur < 0) $cur = 0;
            $this->add_to_internal($key, $cur);
            return $cur;
        }

        switch ($this->driver) {
            case 'redis':
                $res = $this->redis->decrBy($key, $offset);
                if ($res < 0) {
                    $this->redis->set($key, 0);
                    $res = 0;
                }
                $this->add_to_internal($key, $res);
                if ($expire > 0) {
                    $this->redis->expire($key, (int) $expire);
                }
                return $res;
            case 'memcached':
                $this->mc->add($key, $initial_value);
                $res = $this->mc->decrement($key, $offset);
                if ($res === false) {
                    $val = $this->mc->get($key);
                    $val = ($val === false ? $initial_value : $val) - $offset;
                    if ($val < 0) $val = 0;
                    $this->mc->set($key, $val);
                    $this->add_to_internal($key, $val);
                    return $val;
                }
                $this->add_to_internal($key, $res);
                return $res;
            default:
                $cur  = $this->get_from_internal($key) ?: $initial_value;
                $cur -= $offset;
                if ($cur < 0) $cur = 0;
                $this->add_to_internal($key, $cur);
                return $cur;
        }
    }

    /* ---------- 多键操作 ---------- */
    public function get_multiple($ids, $group = 'default', $force = false)
    {
        $results = [];
        $ids     = (array) $ids;
        if (empty($ids)) return $results;

        // 先尝试从内存层获取
        $keys = [];
        foreach ($ids as $id) {
            $key = $this->build_key($id, $group);
            if (!$force && $this->internal_exists($key)) {
                $results[$id] = $this->get_from_internal($key);
            } else {
                $keys[$id] = $key;
            }
        }

        if (empty($keys)) return $results;

        if ($this->is_non_persistent_group($group)) {
            foreach ($keys as $id => $key) $results[$id] = false;
            return $results;
        }

        switch ($this->driver) {
            case 'redis':
                $vals = $this->redis->mGet(array_values($keys));
                // mGet 返回按顺序的数组
                $i = 0;
                foreach ($keys as $id => $key) {
                    $v = $vals[$i] ?? false;
                    if ($v === false || $v === null) {
                        $results[$id] = false;
                        $this->delete_from_internal($key);
                    } else {
                        $results[$id] = @unserialize($v);
                        $this->add_to_internal($key, $results[$id]);
                    }
                    $i++;
                }
                return $results;
            case 'memcached':
                $vals = $this->mc->getMulti(array_values($keys));
                foreach ($keys as $id => $key) {
                    if ($vals && array_key_exists($key, $vals)) {
                        $results[$id] = $vals[$key];
                        $this->add_to_internal($key, $results[$id]);
                    } else {
                        $results[$id] = false;
                        $this->delete_from_internal($key);
                    }
                }
                return $results;
            default:
                foreach ($keys as $id => $key) $results[$id] = false;
                return $results;
        }
    }

    public function set_multiple($data, $group = 'default', $expire = 0)
    {
        $data = (array) $data;
        if (empty($data)) return true;

        // 内存层写入
        $items = [];
        foreach ($data as $id => $value) {
            $key = $this->build_key($id, $group);
            $this->add_to_internal($key, $value);
            $items[$key] = $value;
        }

        if ($this->is_non_persistent_group($group)) return true;

        switch ($this->driver) {
            case 'redis':
                // redis mSet 不能设置 TTL 单独，循环 setex
                $success = true;
                foreach ($items as $key => $val) {
                    $v = serialize($val);
                    if ((int) $expire > 0) {
                        $r = $this->redis->setex($key, (int) $expire, $v);
                    } else {
                        $r = $this->redis->set($key, $v);
                    }
                    if ($r === false) $success = false;
                }
                return $success;
            case 'memcached':
                // Memcached::setMulti 支持 TTL 但行为依赖版本，采用循环 set 以兼容
                $success = true;
                foreach ($items as $key => $val) {
                    $r = $this->mc->set($key, $val, (int) $expire);
                    if ($r === false) $success = false;
                }
                return $success;
            default:
                return true;
        }
    }

    public function delete_multiple($ids, $group = 'default')
    {
        $ids = (array) $ids;
        if (empty($ids)) return true;
        $keys = [];
        foreach ($ids as $id) {
            $keys[] = $this->build_key($id, $group);
            $this->delete_from_internal(end($keys));
        }

        if ($this->is_non_persistent_group($group)) return true;

        switch ($this->driver) {
            case 'redis':
                return (bool) $this->redis->del($keys);
            case 'memcached':
                $success = true;
                foreach ($keys as $k) {
                    if ($this->mc->delete($k) === false) $success = false;
                }
                return $success;
            default:
                return true;
        }
    }

    /* ---------- CAS (compare-and-swap) 支持 ---------- */
    /**
     * get_with_cas - 返回 value 并输出 cas_token（memcached 的 cas token / redis 的 md5(serialized_value)）
     */
    public function get_with_cas($id, $group = 'default', &$cas_token = null)
    {
        $key = $this->build_key($id, $group);

        if ($this->is_non_persistent_group($group)) {
            $cas_token = null;
            return $this->get($id, $group);
        }

        switch ($this->driver) {
            case 'redis':
                $val = $this->redis->get($key);
                if ($val === false || $val === null) {
                    $cas_token = null;
                    return false;
                }
                $un        = @unserialize($val);
                $cas_token = md5($val); // token 为序列化字符串的 md5
                $this->add_to_internal($key, $un);
                return $un;
            case 'memcached':
                // Memcached 扩展的 get 可能返回 CAS token via getMulti? 使用 getDelayed/getDelayed? PHP Memcached 提供 get for cas via get() + getResultCode? 
                // 这里使用 get 和 getResultCode 判断是否存在；获取CAS需要 Memcached::get($key, null, Memcached::GET_EXTENDED)
                if (defined('Memcached::GET_EXTENDED')) {
                    $result = $this->mc->get($key, null, Memcached::GET_EXTENDED);
                    if ($this->mc->getResultCode() === Memcached::RES_NOTFOUND) {
                        $cas_token = null;
                        return false;
                    }
                    $cas_token = $result['cas'] ?? null;
                    $val       = $result['value'] ?? null;
                    $this->add_to_internal($key, $val);
                    return $val;
                } else {
                    // fallback - 没有 GET_EXTENDED, 使用普通 get
                    $val       = $this->mc->get($key);
                    $cas_token = md5(serialize($val));
                    $this->add_to_internal($key, $val);
                    return $val;
                }
            default:
                $val       = $this->get($id, $group);
                $cas_token = $val === false ? null : md5(serialize($val));
                return $val;
        }
    }

    /**
     * cas - Compare-And-Swap（比较并交换）
     * 
     * memcached 原生cas
     * redis Lua脚本实现
     * 
     * @param string $cas_token CAS 校验 token
     * @param string $id 缓存 key
     * @param mixed  $data 要写入的数据
     * @param string $group 缓存分组
     * @param int    $expire 过期时间（秒）
     * @return bool 成功返回 true，失败返回 false
     */
    public function cas($cas_token, $id, $data, $group = 'default', $expire = 0)
    {
        $key = $this->build_key($id, $group);

        // 非持久化组直接 set
        if ($this->is_non_persistent_group($group)) {
            $this->add_to_internal($key, $data);
            return true;
        }

        switch ($this->driver) {
            case 'redis':
                // Redis 使用 WATCH + MULTI 来实现乐观锁
                try {
                    $this->redis->watch($key);
                    $curr      = $this->redis->get($key);
                    $currToken = $curr === false ? null : md5($curr);
                    if ($currToken !== (string) $cas_token) {
                        $this->redis->unwatch();
                        return false;
                    }
                    $this->redis->multi();
                    $val = serialize($data);
                    if ((int) $expire > 0) {
                        $this->redis->setex($key, (int) $expire, $val);
                    } else {
                        $this->redis->set($key, $val);
                    }
                    $exec = $this->redis->exec(); // 为空数组或 false 表示失败
                    if ($exec === false || $exec === null) {
                        return false;
                    }
                    $this->add_to_internal($key, $data);
                    return true;
                }
                catch (Exception $e) {
                    // unwatch on error
                    try {
                        $this->redis->unwatch();
                    }
                    catch (Exception $ex) {
                    }
                    return false;
                }
            case 'memcached':
                // Memcached 的 cas
                if (defined('Memcached::GET_EXTENDED')) {
                    $result = $this->mc->get($key, null, Memcached::GET_EXTENDED);
                    if ($this->mc->getResultCode() === Memcached::RES_NOTFOUND) {
                        return false;
                    }
                    $existingCas = $result['cas'] ?? null;
                    if ($existingCas === $cas_token) {
                        if (method_exists($this->mc, 'cas')) {
                            $ok = $this->mc->cas($cas_token, $key, $data, (int) $expire);
                            if ($ok) {
                                $this->add_to_internal($key, $data);
                                return true;
                            }
                        }
                        // fallback: 强制 set
                        $this->mc->set($key, $data, (int) $expire);
                        $this->add_to_internal($key, $data);
                        return true;
                    }
                    return false;
                } else {
                    // fallback: 用 md5 校验
                    $curr = $this->mc->get($key);
                    if (md5(serialize($curr)) === (string) $cas_token) {
                        $this->mc->set($key, $data, (int) $expire);
                        $this->add_to_internal($key, $data);
                        return true;
                    }
                    return false;
                }
            default:
                // 默认 fallback: 用 md5 校验
                $curr = $this->get($id, $group);
                if (md5(serialize($curr)) === (string) $cas_token) {
                    $this->set($id, $data, $group, $expire);
                    return true;
                }
                return false;
        }
    }

    /* ---------- 其他工具方法 ---------- */

    public function flush()
    {
        $this->cache = [];

        switch ($this->driver) {
            case 'redis':
                return $this->redis->flushDB();
            case 'memcached':
                return $this->mc->flush();
            default:
                return true;
        }
    }

    public function close()
    {
        switch ($this->driver) {
            case 'redis':
                if ($this->redis) return $this->redis->close();
                return true;
            case 'memcached':
                if ($this->mc) return $this->mc->quit();
                return true;
            default:
                return true;
        }
    }

    public function get_stats()
    {
        switch ($this->driver) {
            case 'redis':
                return $this->redis ? $this->redis->info() : [];
            case 'memcached':
                return $this->mc ? $this->mc->getStats() : [];
            default:
                return ['driver' => 'array'];
        }
    }

    public function get_mc()
    {
        return $this->mc;
    }

    public function get_redis()
    {
        return $this->redis;
    }

    /* ---------- global / non-persistent groups 管理 ---------- */
    public function add_global_groups($groups)
    {
        $groups = (array) $groups;
        foreach ($groups as $g) {
            $this->global_groups[(string) $g] = true;
        }
    }

    public function add_non_persistent_groups($groups)
    {
        $groups = (array) $groups;
        foreach ($groups as $g) {
            $this->non_persistent_groups[(string) $g] = true;
        }
    }

    public function is_non_persistent_group($group)
    {
        $group = $group ?: 'default';
        return isset($this->non_persistent_groups[$group]);
    }

    public function switch_to_blog($blog_id)
    {
        if (is_multisite()) {
            $blog_id           = (int) $blog_id;
            $this->blog_prefix = $blog_id . ':';
        } else {
            global $table_prefix;
            $this->blog_prefix = $table_prefix . ':';
        }
    }
}
