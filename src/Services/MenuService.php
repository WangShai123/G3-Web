<?php
namespace JEALER\G3\Services;
use JEALER\G3\Core\Service\Service;
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
    public function getHtml(array $args = []): string|false|null
    {
        $defaults = [
            'theme_location'  => 'desktop-header',
            'container'       => 'nav',
            'container_class' => 'j-menu',
            'menu_class'      => 'menu',
            'fallback_cb'     => false,
            'depth'           => 2,
            'echo'            => false,
            'logged_in'       => false,
        ];
        $params   = array_merge($defaults, $args);

        $echo = false;
        if ($params['echo'] === true) {
            $echo           = true;
            $params['echo'] = false;
        }

        $sign = $params['logged_in'] ? '_logged_in' : '';
        $key  = $params['theme_location'] . $sign;
        $menu = wp_cache_get($key, self::MENU_HTML_CACHE_GROUP);

        if (false === $menu) {
            $menu = wp_nav_menu($params);
            if (is_string($menu) && !empty(trim($menu))) {
                // 24 hours cache
                wp_cache_set($key, $menu, self::MENU_HTML_CACHE_GROUP, DAY_IN_SECONDS);
            }
        }

        if ($echo) {
            echo $menu;
            return null;
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

        // Format the response data using helper function
        $formattedItems = array_map([$this, 'formatMenuItem'], array_values($topLevelItems));

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
            'type'             => $this->getMenuItemDisplayType($item['ID']),
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

    private function getMenuItemDisplayType(int|string $itemId): int
    {
        return match (get_post_meta((int) $itemId, '_menu_item_display_type', true)) {
            'logged-in'     => 1,
            'not-logged-in' => 2,
            default         => 0,
        };
    }
}
