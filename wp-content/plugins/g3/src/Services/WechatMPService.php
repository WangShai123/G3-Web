<?php
namespace JEALER\G3\Services;
class WechatMPService {
    public const TABLE          = 'g3_wechat_mp_menus';
    public const CACHE_GROUP    = 'wechat-MP';
    public const MENU_CACHE_KEY = 'menus';

    /**
     * Get all menus from cache or database
     * 
     * 获取所有菜单数据
     * 
     * @return array Formatted menu data
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function getMenus(): array
    {
        $menus = [];
        $menus = wp_cache_get(self::MENU_CACHE_KEY, self::CACHE_GROUP);
        if (false === $menus) {
            global $wpdb;
            $table = $wpdb->prefix . self::TABLE;
            $menus = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
            if (!empty($menus)) {
                wp_cache_set(self::MENU_CACHE_KEY, $menus, self::CACHE_GROUP);
            }
        }
        // return self::formattedMenus($menus);
        return $menus;
    }

    /**
     * Get menu by ID
     * 
     * 通过ID获取菜单数据
     * 
     * @param int $id Menu ID
     * @return array Menu data
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function getMenuById(int $id): array
    {
        if ($id == 0) return [];

        $key = 'menus:' . $id;

        $menu = [];
        $menu = wp_cache_get($key, self::CACHE_GROUP);
        if (false === $menu) {
            global $wpdb;
            $table = $wpdb->prefix . self::TABLE;
            $menu  = $wpdb->get_row("SELECT * FROM $table WHERE id = $id", ARRAY_A);
            if (!empty($menu)) {
                wp_cache_set($key, $menu, self::CACHE_GROUP);
            }
        }
        return $menu;
    }

    /**
     * Update menu
     * 
     * 更新菜单数据
     * 
     * @param int $id Menu ID
     * @param array $data Menu data
     * @return bool|int Number of rows affected, or false on error
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function updateMenu(int $id, array $data): bool|int
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        if ($id == 0) {
            wp_cache_delete(self::MENU_CACHE_KEY, self::CACHE_GROUP);
            wp_cache_delete('menus:' . $id, self::CACHE_GROUP);
            return $wpdb->insert($table, $data);
        }
        $result = $wpdb->update($table, $data, ['id' => $id]);
        if ($result) {
            wp_cache_delete(self::MENU_CACHE_KEY, self::CACHE_GROUP);
            wp_cache_delete('menus:' . $id, self::CACHE_GROUP);
        }
        return $result;
    }

    /**
     * Delete Menu
     * 
     * 删除菜单
     * 
     * @param int $id Menu ID
     * @return bool|int Number of rows affected, or false on error
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function deleteMenu(int $id): bool|int
    {
        if ($id == 0) return false;

        global $wpdb;
        $table  = $wpdb->prefix . self::TABLE;
        $result = $wpdb->delete($table, ['id' => $id]);
        if ($result) {
            wp_cache_delete(self::MENU_CACHE_KEY, self::CACHE_GROUP);
            wp_cache_delete('menus:' . $id, self::CACHE_GROUP);
        }
        return $result;
    }

    /**
     * Format menus data and build hierarchical structure
     * 
     * 格式化菜单数据并构建层级结构
     * 
     * @param array $menus Raw menu data
     * @return array Formatted menu data with hierarchy indentation
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function formatMenus(array $menus): array
    {
        // Sort menus by parent and sort
        usort($menus, function ($a, $b) {
            if ($a['parent'] == $b['parent']) {
                return $a['sort'] <=> $b['sort'];
            }
            return $a['parent'] <=> $b['parent'];
        });

        // Build parent-child menu relationship
        $menuMap = [];
        $result  = [];

        // Build ID map
        foreach ($menus as $menu) {
            $menuMap[$menu['id']] = $menu;
        }

        // Build hierarchical structure
        foreach ($menus as $menu) {
            $menu['type'] = self::renderMenuType($menu['type']);

            if ($menu['parent'] == '0') {
                // Add top-level menu directly
                $result[] = $menu;

                // Find and add child menus
                foreach ($menus as $child) {
                    if ($child['parent'] == $menu['id']) {
                        // reRender child menu
                        $child['name'] = '└─ ' . $child['name'];
                        $child['sort'] = '└─ ' . $child['sort'];
                        $child['type'] = self::renderMenuType($child['type']);
                        $result[]      = $child;
                    }
                }
            }
        }

        return $result;
    }

    private static function renderMenuType(string $type): string
    {
        match ($type) {
            '1' => $type = 'view',
            '2' => $type = 'click',
            default => $type = '-'
        };
        return $type;
    }
}