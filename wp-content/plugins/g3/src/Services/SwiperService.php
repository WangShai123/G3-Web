<?php
namespace JEALER\G3\Services;

class SwiperService {

    /**
     * Singleton instance
     * 
     * 单例实例
     * 
     * @var self
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public static $instance = null;

    /**
     * Swiper table name
     * 
     * 轮播图表名
     * 
     * @var string
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public const TABLE = 'g3_swipers';

    /**
     * Swiper Cache key
     * 
     * 轮播图缓存键
     * 
     * @var string
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public const CACHE_KEY = 'swipers';

    /**
     * Swiper Cache group
     * 
     * 轮播图缓存组
     * 
     * @var string
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public const CACHE_GROUP = 'swipers';

    /**
     * Swiper Query Cache group
     * 
     * 轮播图查询缓存组
     * 
     * @var string
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public const QUERY_CACHE_GROUP = 'swipers_query';

    /**
     * Swiper Location Option key
     * 
     * 轮播图位置选项键
     * 
     * @var string
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public const LOCATION_OPTION_KEY = 'g3_option_swiper_locations';

    /**
     * Swiper Query Option key
     * 
     * 轮播图查询选项键
     * 
     * @var string
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public const QUERY_OPTION_KEY = 'g3_option_swipers_query';

    public function __construct()
    {
    }

    /**
     * Run
     * 
     * 运行
     * 
     * @return self
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function run(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get all swipers
     * 
     * 获取所有轮播图
     * 
     * @return array
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function getSwipers(): array
    {
        $swipers = [];
        $swipers = wp_cache_get(self::CACHE_KEY, self::CACHE_GROUP);
        if (false === $swipers) {
            global $wpdb;
            $table   = $wpdb->prefix . self::TABLE;
            $swipers = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
            if ($swipers) {
                wp_cache_set(self::CACHE_KEY, $swipers, self::CACHE_GROUP);
            }
        }
        return $swipers;
    }

    /**
     * Count swipers
     * 
     * 统计轮播图数量
     * 
     * @return int
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function count(): int
    {
        return count(self::getSwipers());
    }

    /**
     * Get one swiper data by id
     * 
     * 通过 ID 获取轮播图数据
     * 
     * @param int $id Swiper ID
     * @return array
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function queryById(int $id)
    {
        $data = wp_cache_get($id, self::CACHE_GROUP);
        if (false === $data) {
            global $wpdb;
            $table = $wpdb->prefix . self::TABLE;
            $data  = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
            if ($data) {
                wp_cache_set($id, $data, self::CACHE_GROUP);
            }
        }
        return $data;
    }

    /**
     * Get swipers query by location
     * 
     * 通过位置获取轮播图查询
     * 
     * @param string $location Swiper location
     * @return array
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function queryByLocation(string $location)
    {
        $data = wp_cache_get($location, self::QUERY_CACHE_GROUP);
        if (false === $data) {
            global $wpdb;
            $table = $wpdb->prefix . self::TABLE;
            $data  = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE location = %s", $location), ARRAY_A);

            if ($data) {
                wp_cache_set($location, $data, self::QUERY_CACHE_GROUP);

                // Save all swiper query key, for manage swiper query
                $cacheKey = get_option(self::QUERY_OPTION_KEY, []);

                if (!in_array($location, $cacheKey)) {
                    $cacheKey[] = $location;
                    update_option(self::QUERY_OPTION_KEY, $cacheKey);
                }
            }
        }
        return $data;
    }

    /**
     * Clear swipers query cache
     * 
     * 清空轮播图查询缓存
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function clearQueryCache(): void
    {
        $cacheKey = get_option(self::QUERY_OPTION_KEY, []);
        if ($cacheKey) {
            foreach ($cacheKey as $key) {
                wp_cache_delete($key, self::QUERY_CACHE_GROUP);
            }
        }
        delete_option(self::QUERY_OPTION_KEY);
    }

    /**
     * Get swipers
     * 
     * 获取轮播图
     * 
     * @param array $args
     * @return bool
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function query(array $args): bool
    {
        $default  = [
            'location'              => 'home',
            'container'             => 'div',
            'container_id'          => 'mySwiper',
            'container_class'       => 'swiper',
            'pagination'            => true,
            'pagination_class'      => 'swiper-pagination',
            'pagination_clickable'  => true,
            'navigation'            => true,
            'navigation_next_class' => 'swiper-button-next',
            'navigation_prev_class' => 'swiper-button-prev',
            'scroll'                => true,
            'scroll_class'          => 'swiper-scrollbar',
            'scroll_hide'           => true,
            'horizontal'            => true,
            'loop'                  => true,
            'speed'                 => 500,
            'autoplay'              => true,
            'autoplay_delay'        => 3000,
            'lazy'                  => false,
        ];
        $args     = \wp_parse_args($args, $default);
        $template = G3_PlUGIN_DIR . '/templates/swiper/render.php';
        if (file_exists($template)) {
            load_template($template, true, $args);
            return true;
        } else {
            echo __('Template not found', 'G3');
            return false;
        }
    }

    /**
     * Update swiper status
     * 
     * 更新轮播图状态
     * 
     * @param array $ids Swiper IDs
     * @param int $status Swiper status
     * @return bool|int
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function updateStatus(array $ids, int $status): bool|int
    {
        if (!is_array($ids) || !count($ids)) return false;

        global $wpdb;
        $table  = $wpdb->prefix . self::TABLE;
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table SET `status` = %d WHERE `id` IN (" . implode(',', array_map('intval', $ids)) . ")",
                $status
            )
        );
        foreach ($ids as $id) {
            wp_cache_delete($id, self::CACHE_GROUP);
        }
        return $result;
    }

    /**
     * Delete swipers
     * 
     * 删除轮播图
     * 
     * @param int|array $ids Swiper IDs
     * @return bool|int
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function deleteSwipers(int|array $ids): bool|int
    {
        if (!$ids) return false;

        global $wpdb;
        $table  = $wpdb->prefix . self::TABLE;
        $result = false;

        if (is_array($ids)) {
            $ids     = array_map('intval', $ids);
            $ids_str = implode(',', $ids);
            $result  = $wpdb->query("DELETE FROM {$table} WHERE id IN ({$ids_str})");
        } else {
            $result = $wpdb->delete($table, ['id' => $ids]);
        }

        if ($result) {
            if (is_array($ids)) {
                foreach ($ids as $id) {
                    wp_cache_delete($id, self::CACHE_GROUP);
                }
            } else {
                wp_cache_delete($ids, self::CACHE_GROUP);
            }
            self::clearQueryCache();
            wp_cache_delete(self::CACHE_KEY, self::CACHE_GROUP);
        }

        return $result;
    }

    /**
     * Delete swiper locations
     * 
     * 删除轮播图位置
     * 
     * @param string|array $locations
     * @return bool
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function deleteLocations(string|array $locations): bool
    {
        if (!$locations) return false;

        $data = get_option(self::LOCATION_OPTION_KEY, []);

        if (is_array($locations)) {
            foreach ($locations as $location) {
                if (isset($data[$location])) {
                    unset($data[$location]);
                }
            }
        } else {
            if (isset($data[$locations])) {
                unset($data[$locations]);
            }
        }

        return update_option(self::LOCATION_OPTION_KEY, $data);
    }

    /**
     * render swiper status
     * 
     * @param int $status
     * @return string
     */
    public static function renderStatus(int $status): string
    {
        return $status == 1 ? __('Enable') : __('Disable');
    }

    /**
     * render swiper target
     * 
     * @param int $target
     * @return string
     */
    public static function renderTarget(int $target): string
    {
        return $target == 0 ? __('Current Tab', 'G3') : __('New Tab', 'G3');
    }
}