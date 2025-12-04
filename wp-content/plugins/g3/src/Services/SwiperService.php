<?php
namespace JEALER\G3\Services;

class SwiperService {
    public static $instance = null;
    public const TABLE               = 'g3_swipers';
    public const CACHE_KEY           = 'swipers';
    public const CACHE_GROUP         = 'swipers';
    public const QUERY_CACHE_GROUP   = 'swipers_query';
    public const LOCATION_OPTION_KEY = 'g3_option_swiper_locations';
    public const QUERY_OPTION_KEY    = 'g3_option_swipers_query';
    public function __construct()
    {
    }

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
            $table = $wpdb->prefix . 'g3_swipers';
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
}