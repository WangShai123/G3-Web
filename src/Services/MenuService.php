<?php
namespace JEALER\G3\Services;
use JEALER\G3\Core\Service\Service;
use JEALER\G3\Services\AuthService;
use WP_Error;

class MenuService extends Service {
    const MENU_HTML_CACHE_GROUP = 'g3_menu_html';
    const MENU_JSON_CACHE_GROUP = 'g3_menu_json';

    /**
     * get menu items. 24 hours cache if echo is false.
     * 
     * 获取菜单项。如果echo为false，24小时缓存。
     * 
     * @param array $args
     * @return string|false|void
     * @since 1.0.0
     * @author Wang Shai
     */
    public function get(array $args = [])
    {
        $defaults = [
            'theme_location'  => 'desktop-header',
            'container'       => 'nav',
            'container_class' => 'j-menu',
            'menu_class'      => 'menu',
            'fallback_cb'     => false,
            'depth'           => 2,
            'echo'            => false,
        ];
        $params   = wp_parse_args($args, $defaults);

        if ($params['echo']) {
            wp_nav_menu($params);
            return;
        }

        $key  = $params['theme_location'];
        $menu = wp_cache_get($key, self::MENU_HTML_CACHE_GROUP);
        if (false === $menu) {
            $menu = wp_nav_menu($params);
            if (is_string($menu) && !empty(trim($menu))) {
                // 24 hours cache
                wp_cache_set($key, $menu, self::MENU_HTML_CACHE_GROUP, DAY_IN_SECONDS);
            }
        }
        return $menu;
    }

    /**
     * Get menu items in JSON format. 24 hours cache.
     * 
     * 获取菜单项的JSON格式。24小时缓存。
     * 
     * @param string $location
     * @return array|WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    public function getJson(string $location): array|WP_Error
    {
        $locations = get_nav_menu_locations();
        if (!isset($locations[$location])) {
            return new WP_Error('invalid_location', 'Invalid location', ['status' => 400]);
        }

        $menuId = $locations[$location];
        // Get the menu object
        $menu = wp_get_nav_menu_object($menuId);
        if (!$menu) {
            return new WP_Error(
                'invalid_menu',
                'Invalid menu',
                ['status' => 404]
            );
        }

        // Get menu items and sort them
        $menuItems = wp_get_nav_menu_items($menu->term_id);
        if (empty($menuItems)) {
            return new WP_Error(
                'no_items',
                'No items found',
                ['status' => 404]
            );
        }

        usort($menuItems, function ($a, $b) {
            return $a->menu_order <=> $b->menu_order;
        });

        // Build a map of menu items by ID for easy lookup
        $menuItemMap = [];
        foreach ($menuItems as $item) {
            $menuItemMap[$item->ID]             = (array) $item;
            $menuItemMap[$item->ID]['children'] = []; // Initialize children array
        }

        // Assign children to their respective parents
        foreach ($menuItemMap as $itemId => $itemData) {
            $parentId = (int) $itemData['menu_item_parent'];
            if ($parentId > 0 && isset($menuItemMap[$parentId])) {
                $menuItemMap[$parentId]['children'][] = &$menuItemMap[$itemId];
            }
        }

        // Filter out top-level items (those without a parent)
        $topLevelItems = array_filter($menuItemMap, function ($item) {
            return (int) $item['menu_item_parent'] === 0;
        });

        // Filter menu items by display type (login status)
        $filteredItems = $this->filterMenuItemsByDisplayType(array_values($topLevelItems));

        // Format the response data using helper function
        $formattedItems = array_map([$this, 'formatMenuItem'], $filteredItems);

        // Validate final result
        if (empty($formattedItems)) {
            return new WP_Error(
                'no_items',
                'No items found.',
                ['status' => 404]
            );
        }

        // Return success response
        $result = array_values($formattedItems);

        // 24 hours cache
        wp_cache_set($location, $result, MenuService::MENU_JSON_CACHE_GROUP, DAY_IN_SECONDS);

        return $result;
    }

    /**
     * Filter menu items by display type based on user login status.
     * 
     * 根据用户登录状态，按显示类型过滤菜单项。
     * 
     * @param array $items The menu items to filter.
     * @return array The filtered menu items.
     * @since 1.0.0
     * @author Wang Shai
     */
    private function filterMenuItemsByDisplayType(array $items): array
    {
        AuthService::checkWordPressCookie();
        $isLoggedIn = is_user_logged_in();

        return array_filter($items, function ($item) use ($isLoggedIn) {
            $displayType = get_post_meta($item['ID'], '_menu_item_display_type', true);

            // Filter menu items based on login status
            if ($displayType === 'logged-in' && !$isLoggedIn) {
                return false;
            }

            if ($displayType === 'not-logged-in' && $isLoggedIn) {
                return false;
            }

            // Recursively filter child menu items
            if (!empty($item['children'])) {
                $item['children'] = $this->filterMenuItemsByDisplayType($item['children']);
            }

            return true;
        });
    }

    /**
     * Helper function to format a menu item and its children recursively.
     * 
     * 辅助函数，递归格式化菜单项及其子项。
     * 
     * @param array $item The menu item data.
     * @return array The formatted menu item.
     * @since 1.0.0
     * @author Wang Shai
     */
    private function formatMenuItem(array $item): array
    {
        $formattedItem = [
            'id'               => $item['ID'],
            'title'            => $item['title'],
            'url'              => $item['url'],
            'target'           => $item['target'],
            'description'      => $item['description'],
            'classes'          => !empty($item['classes']) ? implode(' ', $item['classes']) : '',
            // 'xfn'              => $item['xfn'],
            'menu_item_parent' => (int) $item['menu_item_parent'] ?: null,
            'children'         => !empty($item['children']) ? array_values($item['children']) : [],
        ];

        // Recursively format children if they exist
        if (!empty($item['children'])) {
            $formattedItem['children'] = array_map([$this, 'formatMenuItem'], $item['children']);
        }

        return $formattedItem;
    }
}
