<?php

/**  
 * 如果GET参数中有debug=sql,则不使用缓存  
 */
if ((isset($_GET['debug']) && $_GET['debug'] == 'sql')) return;
/**   
 * 定义WP_CACHE_KEY_SALT常量,用于生成缓存键
 */
if (!defined('WP_CACHE_KEY_SALT')) define('WP_CACHE_KEY_SALT', '');
/**
 * 如果安装了Memcached扩展,则使用Memcached对象缓存
 */
if (class_exists('Memcached')) {
	/**
	 * 初始化WP_Object_Cache对象
	 */
	if (!isset($_GET['debug']) || $_GET['debug'] != 'sql') {
		function wp_cache_init()
		{
			global $wp_object_cache;
			$wp_object_cache = new WP_Object_Cache();
		}
	}
	/**
	 * 添加数据到缓存
	 * @param  string $key		缓存键
	 * @param  mixed  $data		缓存数据
	 * @param  string $group	缓存分组
	 * @param  int    $expire	过期时间
	 * @return boolean			是否成功
	 */
	function wp_cache_add($key, $data, $group = '', $expire = 0)
	{
		global $wp_object_cache;
		return $wp_object_cache->add($key, $data, $group, (int) $expire);
	}
	/**
	 * 设置缓存
	 * @param string $key		缓存键
	 * @param mixed  $data		缓存数据
	 * @param string $group		缓存分组
	 * @param int    $expire	过期时间
	 * @return boolean			是否成功
	 */
	function wp_cache_set($key, $data, $group = '', $expire = 0)
	{
		global $wp_object_cache;
		return $wp_object_cache->set($key, $data, $group, (int) $expire);
	}
	/**
	 * 获取缓存
	 * @param  string $key		缓存键
	 * @param  string $group	缓存分组
	 * @param  boolean $force	是否强制获取
	 * @param  boolean $found	是否找到
	 * @return mixed			缓存数据
	 */
	function wp_cache_get($key, $group = '', $force = false, &$found = null)
	{
		global $wp_object_cache;
		return $wp_object_cache->get($key, $group, $force, $found);
	}
	/**
	 * 删除缓存
	 * @param  string $key   缓存键
	 * @param  string $group 缓存分组
	 * @return boolean       是否成功
	 */
	function wp_cache_delete($key, $group = '')
	{
		global $wp_object_cache;
		return $wp_object_cache->delete($key, $group);
	}
	/**
	 * 增加缓存值
	 * @param  string $key				缓存键
	 * @param  int    $offset			偏移量
	 * @param  string $group			缓存分组
	 * @param  int    $initial_value	初始化值
	 * @param  int    $expire			过期时间
	 * @return int						新缓存值，失败则返回false
	 */
	function wp_cache_incr($key, $offset = 1, $group = '', $initial_value = 0, $expire = 0)
	{
		global $wp_object_cache;
		return $wp_object_cache->incr($key, $offset, $group, $initial_value, $expire);
	}
	/**
	 * 减少缓存值
	 * @param  string $key            缓存键
	 * @param  int    $offset         偏移量
	 * @param  string $group          缓存分组
	 * @param  int    $initial_value  初始化值
	 * @param  int    $expire         过期时间
	 * @return int                    新缓存值，失败则返回false
	 */
	function wp_cache_decr($key, $offset = 1, $group = '', $initial_value = 0, $expire = 0)
	{
		global $wp_object_cache;
		return $wp_object_cache->decr($key, $offset, $group, $initial_value, $expire);
	}
	/**
	 * 替换缓存
	 * @param  string $key		缓存键
	 * @param  mixed  $data		缓存数据
	 * @param  string $group	缓存分组
	 * @param  int    $expire	过期时间
	 * @return boolean			是否成功
	 */
	function wp_cache_replace($key, $data, $group = '', $expire = 0)
	{
		global $wp_object_cache;
		return $wp_object_cache->replace($key, $data, $group, (int) $expire);
	}
	/**
	 * 对有CAS令牌的缓存值进行比较并交换(Compare And Swap)
	 * @param  string $cas_token CAS令牌
	 * @param  string $key		缓存键
	 * @param  mixed  $data		缓存数据
	 * @param  string $group	缓存分组
	 * @param  int    $expire	过期时间
	 * @return boolean			CAS操作是否成功
	 */
	function wp_cache_cas($cas_token, $key, $data, $group = '', $expire = 0)
	{
		global $wp_object_cache;
		return $wp_object_cache->cas($cas_token, $key, $data, $group, (int) $expire);
	}
	/**
	 * 获取缓存值并返回CAS令牌
	 * @param  string $key			缓存键
	 * @param  string $group		缓存分组
	 * @param  string &$cas_token	CAS令牌
	 * @return mixed				缓存值
	 */
	function wp_cache_get_with_cas($key, $group = '', &$cas_token = null)
	{
		global $wp_object_cache;
		return $wp_object_cache->get_with_cas($key, $group, $cas_token);
	}
	/**
	 * 关闭缓存
	 * @return boolean 是否成功
	 */
	function wp_cache_close()
	{
		global $wp_object_cache;
		return $wp_object_cache->close();
	}
	/**
	 * 清空缓存
	 * @return boolean 是否成功
	 */
	function wp_cache_flush()
	{
		global $wp_object_cache;
		return $wp_object_cache->flush();
	}
	/**
	 * 获取多个缓存
	 * @param  array  $keys		缓存键数组
	 * @param  string $group	缓存分组
	 * @param  boolean $force	是否强制获取
	 * @return array			缓存数据数组
	 */
	function wp_cache_get_multiple($keys, $group = '', $force = false)
	{
		global $wp_object_cache;
		return $wp_object_cache->get_multiple($keys, $group, $force);
	}
	/**
	 * 设置多个缓存
	 * @param array $data   缓存数据数组
	 * @param string $group 缓存分组
	 * @param int $expire   过期时间
	 * @return boolean      是否成功
	 */
	function wp_cache_set_multiple($data, $group = '', $expire = 0)
	{
		global $wp_object_cache;
		return $wp_object_cache->set_multiple($data, $group, $expire);
	}
	/**
	 * 删除多个缓存
	 * @param  array  $keys  缓存键数组
	 * @param  string $group 缓存分组
	 * @return boolean       是否成功
	 */
	function wp_cache_delete_multiple($keys, $group = '')
	{
		global $wp_object_cache;
		return $wp_object_cache->delete_multiple($keys, $group);
	}
	/**
	 * 在多站点中切换到指定站点
	 * @param  int $blog_id		站点ID
	 * @return boolean       	是否成功
	 */
	function wp_cache_switch_to_blog($blog_id)
	{
		global $wp_object_cache;
		return $wp_object_cache->switch_to_blog($blog_id);
	}
	/**
	 * 添加全局分组
	 * @param array $groups		分组数组
	 */
	function wp_cache_add_global_groups($groups)
	{
		global $wp_object_cache;
		$wp_object_cache->add_global_groups($groups);
	}
	/**
	 * 添加非持久化分组
	 * @param array $groups		分组数组
	 */
	function wp_cache_add_non_persistent_groups($groups)
	{
		global $wp_object_cache;
		$wp_object_cache->add_non_persistent_groups($groups);
	}
	/**
	 * 获取 WordPress 缓存统计信息
	 */
	function wp_cache_get_stats()
	{
		global $wp_object_cache;
		return $wp_object_cache->get_stats();
	}
	/**  
	 * WP_Object_Cache类,对Memcached进行封装
	 */
	class WP_Object_Cache {
		private $cache = [];
		private $mc = null;
		private $blog_prefix;
		private $global_prefix;
		protected $global_groups = [];
		protected $non_persistent_groups = [];

		/**
		 * 构造函数,连接Memcached服务器
		 */
		public function __construct()
		{
			if (extension_loaded('memcached')) {
				$this->mc = new Memcached();
			} else {
				return false;
			}
			// 设置Memcached选项
			$this->mc->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
			if (!$this->mc->getServerList()) {
				// 如果没有服务器列表,默认添加localhost:11211
				$this->mc->addServer('127.0.0.1', 11211, 100);
			}
			// 根据网站设置key前缀:单站使用$table_prefix,多站使用博客ID
			if (is_multisite()) {
				$this->blog_prefix   = get_current_blog_id() . ':';
				$this->global_prefix = '';
			} else {
				global $table_prefix;
				$this->blog_prefix = $table_prefix . ':';
				// 如果使用自定义表则不使用全局前缀
				if (defined('CUSTOM_USER_TABLE') && defined('CUSTOM_USER_META_TABLE')) {
					$this->global_prefix = '';
				} else {
					$this->global_prefix = $table_prefix . ':';
				}
			}
		}
		/**
		 * 执行缓存操作
		 * @param  string $action	操作类型
		 * @param  string $id		缓存键
		 * @param  string $group	缓存分组
		 * @param  mixed  $data		缓存数据
		 * @param  int    $expire	过期时间
		 * @return boolean			是否成功
		 */
		protected function action($action, $id, $group, $data, $expire = 0)
		{
			$key = $this->build_key($id, $group);
			if ($this->is_non_persistent_group($group)) {
				// 处理非持久化分组的缓存
				if ($action == 'add') {
					if ($this->internal_exists($key)) return false;
				} elseif ($action == 'replace') {
					if (!$this->internal_exists($key)) return false;
				} elseif ($action == 'increment' || $action == 'decrement') {
					$current = $this->get_from_internal($key);
					$action == 'increment' ? $data = $current + $data : $data = $current - $data;
					if ($data < 0) $data = 0;
				}
				return $this->add_to_internal($key, $data);
			} else {
				// 处理持久化分组的缓存
				if ($action == 'set') {
					$result = $this->mc->set($key, $data, $expire);
				} elseif ($action == 'add') {
					$result = $this->mc->add($key, $data, $expire);
				} elseif ($action == 'replace') {
					$result = $this->mc->replace($key, $data, $expire);
				} elseif ($action == 'increment') {
					$result = $data = $this->mc->increment($key, $data);
				} elseif ($action == 'decrement') {
					$result = $data = $this->mc->decrement($key, $data);
				}

				if ($this->mc->getResultCode() === Memcached::RES_SUCCESS) {
					$this->add_to_internal($key, $data);
				} else {
					$this->delete_from_internal($key);
				}
				return $result;
			}
		}
		// 添加缓存
		public function add($id, $data, $group = 'default', $expire = 0)
		{
			if (wp_suspend_cache_addition()) {
				return false;
			}
			return $this->action('add', $id, $group, $data, $expire);
		}
		// 设置缓存
		public function set($id, $data, $group = 'default', $expire = 0)
		{
			return $this->action('set', $id, $group, $data, $expire);
		}
		// 获取缓存
		public function get($id, $group = 'default', $force = false, &$found = null)
		{
			$key = $this->build_key($id, $group);
			if ($this->internal_exists($key) && !$force) {
				$found = true;
				return $this->get_from_internal($key);
			} elseif ($this->is_non_persistent_group($group)) {
				$found = false;
				return false;
			}
			$value = $this->mc->get($key);
			if ($this->mc->getResultCode() == Memcached::RES_NOTFOUND) {
				$found = false;
			} else {
				$found = true;
				$this->add_to_internal($key, $value);
			}
			return $value;
		}
		// 删除缓存
		public function delete($id, $group = 'default')
		{
			$key = $this->build_key($id, $group);
			$this->delete_from_internal($key);
			if ($this->is_non_persistent_group($group)) {
				return true;
			}
			return $this->mc->delete($key);
		}
		// 增加(递增)缓存
		public function incr($id, $offset = 1, $group = 'default', $initial_value = 0, $expire = 0)
		{
			$this->action('add', $id, $group, $initial_value, $expire);
			return $this->action('increment', $id, $group, $offset);
		}
		// 减少(递减)缓存
		public function decr($id, $offset = 1, $group = 'default', $initial_value = 0, $expire = 0)
		{
			$this->action('add', $id, $group, $initial_value, $expire);
			return $this->action('decrement', $id, $group, $offset);
		}
		// 替换缓存
		public function replace($id, $data, $group = 'default', $expire = 0)
		{
			return $this->action('replace', $id, $group, $data, $expire);
		}
		// 对有CAS令牌的缓存值进行比较并交换(Compare And Swap)
		public function cas($cas_token, $id, $data, $group = 'default', $expire = 0)
		{
			$key = $this->build_key($id, $group);
			$this->delete_from_internal($key);
			return $this->mc->cas($cas_token, $key, $data, $expire);
		}
		// 获取带CAS令牌的缓存
		public function get_with_cas($id, $group = 'default', &$cas_token = null)
		{
			$key = $this->build_key($id, $group);
			if (defined('Memcached::GET_EXTENDED')) {
				$result = $this->mc->get($key, null, Memcached::GET_EXTENDED);
				if ($this->mc->getResultCode() == Memcached::RES_NOTFOUND) {
					return false;
				} else {
					$cas_token = $result['cas'];
					return $result['value'];
				}
			} else {
				$result = $this->mc->get($key, null, $cas_token);
				if ($this->mc->getResultCode() == Memcached::RES_NOTFOUND) {
					return false;
				} else {
					return $result;
				}
			}
		}
		// 关闭Memcached连接
		public function close()
		{
			$this->mc->quit();
		}
		// 清空缓存
		public function flush()
		{
			$this->cache = [];
			return $this->mc->flush();
		}
		// 获取多个缓存
		public function get_multiple($ids, $group = 'default', $force = false)
		{
			$caches           = [];
			$keys             = [];
			$persistent_group = !$this->is_non_persistent_group($group);
			if (!$persistent_group || !$force) {
				foreach ($ids as $id) {
					$key         = $this->build_key($id, $group);
					$value       = $this->get_from_internal($key);
					$caches[$id] = $value;
					$keys[$id]   = $key;
					if ($persistent_group && !$this->internal_exists($key)) $force = true;
				}
				if (!$persistent_group || !$force) return $caches;
			}
			$results = $this->mc->getMulti(array_values($keys));
			foreach ($keys as $id => $key) {
				if ($results && isset($results[$key])) {
					$caches[$id] = $results[$key];
					$this->add_to_internal($key, $caches[$id]);
				} else {
					$caches[$id] = false;
					$this->delete_from_internal($key);
				}
			}
			return $caches;
		}
		// 设置多个缓存
		public function set_multiple($data, $group = 'default', $expire = 0)
		{
			$items = [];
			foreach ($data as $id => $value) {
				$key         = $this->build_key($id, $group);
				$items[$key] = $value;
				$this->add_to_internal($key, $value);
			}
			if ($this->is_non_persistent_group($group)) return true;
			$result = $this->mc->setMulti($items, $expire);
			if ($this->mc->getResultCode() !== Memcached::RES_SUCCESS) {
				foreach ($items as $key => $value) {
					$this->delete_from_internal($key);
				}
			}
			return $result;
		}
		// 删除多个缓存
		public function delete_multiple($ids, $group = 'default')
		{
			$keys = [];
			foreach ($ids as $id) {
				$keys[] = $key = $this->build_key($id, $group);
				$this->delete_from_internal($key);
			}
			if ($this->is_non_persistent_group($group)) return true;
			return $this->mc->deleteMulti($keys);
		}
		// 在多站点环境中切换到指定站点
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
		// 添加全局组
		public function add_global_groups($groups)
		{
			$groups              = (array) $groups;
			$groups              = array_fill_keys($groups, true);
			$this->global_groups = array_merge($this->global_groups, $groups);
		}
		// 添加非持久组
		public function add_non_persistent_groups($groups)
		{
			$groups                      = (array) $groups;
			$groups                      = array_fill_keys($groups, true);
			$this->non_persistent_groups = array_merge($this->non_persistent_groups, $groups);
		}
		// 获取Memcached统计信息
		public function get_stats()
		{
			return $this->mc->getStats();
		}
		// 检查内部缓存中是否存在缓存键
		private function internal_exists($key)
		{
			return $this->cache && isset($this->cache[$key]) && $this->cache[$key] !== false;
		}
		// 从内部缓存中获取缓存
		private function get_from_internal($key)
		{
			if (!$this->internal_exists($key)) return false;
			if (is_object($this->cache[$key])) return clone $this->cache[$key];
			return $this->cache[$key];
		}
		// 添加缓存到内部缓存
		private function add_to_internal($key, $value)
		{
			if (is_object($value)) $value = clone $value;
			$this->cache[$key] = $value;
			return true;
		}
		// 从内部缓存中删除缓存
		private function delete_from_internal($key)
		{
			unset($this->cache[$key]);
		}
		// 检查是否为非持久组
		private function is_non_persistent_group($group)
		{
			$group = $group ?: 'default';
			return isset($this->non_persistent_groups[$group]);
		}
		// 构建缓存键
		private function build_key($id, $group = 'default')
		{
			$group  = $group ?: 'default';
			$prefix = isset($this->global_groups[$group]) ? $this->global_prefix : $this->blog_prefix;
			return preg_replace('/\s+/', '', WP_CACHE_KEY_SALT . $prefix . $group . ':' . $id);
		}
		// 获取Memcached对象
		public function get_mc()
		{
			return $this->mc;
		}
		// 连接Memcached服务器失败的回调方法
		public function failure_callback($host, $port)
		{
		}
	}
}
