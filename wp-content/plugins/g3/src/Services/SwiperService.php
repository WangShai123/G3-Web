<?php

namespace JEALER\G3\Services;

/**
 * Swiper Service
 * 
 * 轮播图服务
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class SwiperService {

    /**
     * Singleton instance
     * 
     * 单例实例
     * 
     * @var self
     */
    private static SwiperService $instance;

    /**
     * Swiper table name
     * 
     * 轮播图表名
     * 
     * @var string
     */
    const TABLE = 'g3_swipers';

    /**
     * Swiper Cache key
     * 
     * 轮播图缓存键
     * 
     * @var string
     */
    const CACHE_KEY = 'swipers';

    /**
     * Swiper Cache group
     * 
     * 轮播图缓存组
     * 
     * @var string
     */
    const CACHE_GROUP = 'g3_swipers';

    /**
     * Swiper Query Cache group
     * 
     * 轮播图查询缓存组
     * 
     * @var string
     */
    const QUERY_CACHE_GROUP = self::CACHE_GROUP . ':query';

    /**
     * Swiper Location Option key
     * 
     * 轮播图位置选项键
     * 
     * @var string
     */
    const LOCATION_OPTION_KEY = 'g3_option_swiper_locations';

    /**
     * Swiper Query Option key
     * 
     * 轮播图查询选项键
     * 
     * @var string
     */
    const QUERY_OPTION_KEY = 'g3_option_swipers_query';

    public function __construct()
    {
    }

    /**
     * Run
     * 
     * 运行
     * 
     * @return SwiperService
     */
    public static function run(): SwiperService
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
     */
    public static function query(array $args): bool
    {
        $default  = [
            'location'       => 'home',
            'containerClass' => '',
            'direction'      => 'horizontal',
            'loop'           => true,
            'speed'          => 500,
            'autoplay'       => [
                'delay' => 3000,
            ],
            'lazy'           => [
                'loadPrevNext' => true,
            ],
            'pagination'     => [
                'el'        => '.swiper-pagination',
                'type'      => 'bullets',
                'clickable' => true,
            ],
            'navigation'     => [
                'nextEl' => '.swiper-button-next',
                'prevEl' => '.swiper-button-prev',
            ],
        ];
        $args     = wp_parse_args($args, $default);
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
        return $target === 1 ? __('New Tab', 'G3') : __('Current Tab', 'G3');
    }
}
