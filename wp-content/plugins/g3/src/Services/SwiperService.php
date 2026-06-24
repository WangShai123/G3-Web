<?php
namespace JEALER\G3\Services;

class SwiperService {
    // Singleton instance
    private static SwiperService $instance;
    // Swiper table name
    const TABLE = 'g3_swipers';
    // Swiper Cache key
    const CACHE_KEY = 'swipers';
    // Swiper Cache group
    const CACHE_GROUP = 'g3_swipers';
    // Swiper Query Cache group
    const QUERY_CACHE_GROUP = self::CACHE_GROUP . ':query';
    // Swiper Location Option key
    const LOCATION_OPTION_KEY = 'g3_option_swiper_locations';
    public function __construct()
    {
    }
    public static function run(): SwiperService
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    /**
     * Get all swipers
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
     * get swipers count
     * @return int
     */
    public static function count(): int
    {
        return count(self::getSwipers());
    }
    /**
     * Get one swiper data by id
     * @param int $id
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
     * @param string $location
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
            }
        }
        return $data;
    }
    public static function clearSwipersCache(): bool
    {
        return wp_cache_delete(self::CACHE_KEY, self::CACHE_GROUP);
    }
    public static function clearSwiperCache(int $id): bool
    {
        return wp_cache_delete($id, self::CACHE_GROUP);
    }
    public static function clearLocationCache(string $location): bool
    {
        return wp_cache_delete($location, self::QUERY_CACHE_GROUP);
    }
    /**
     * Get swipers query by args
     * @param array $args
     * @return bool
     */
    public static function query(array $args): bool
    {
        $default  = [
            'location'        => 'home',
            'containerClass'  => 'j-swiper-container',
            'direction'       => 'horizontal',
            'autoplay'        => true,
            'delay'           => 3000,
            'speed'           => 300,
            'touchRatio'      => 1,
            'touchAngle'      => 45,
            'longSwipesMs'    => 300,
            'longSwipesRatio' => 0.05,
            'preventClick'    => true,
            'loop'            => true,
            'pagination'      => true,
            'navigation'      => true,
            'lazyload'        => true,
            'targetBlank'     => true,
            'data'            => [],
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
     * Update swipers status by IDs
     * @param array $ids
     * @param int $status
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
            self::clearSwiperCache($id);
        }
        return $result;
    }
    /**
     * Delete swipers by IDs
     * @param int|array $ids
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
                    self::clearSwiperCache($id);
                }
            } else {
                self::clearSwiperCache($ids);
            }
            self::clearSwipersCache();
        }
        return $result;
    }
    /**
     * Delete swiper locations by keys
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
     * @param int $status
     * @return string
     */
    public static function renderStatus(int $status): string
    {
        return $status == 1 ? __('Enable') : __('Disable');
    }
    /**
     * render swiper target
     * @param int $target
     * @return string
     */
    public static function renderTarget(int $target): string
    {
        return $target === 1 ? __('New Tab', 'G3') : __('Current Tab', 'G3');
    }
}