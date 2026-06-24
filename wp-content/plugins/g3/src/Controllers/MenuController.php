<?php
namespace JEALER\G3\Controllers;
use JEALER\G3\Core\Attributes\RestRouter;
use JEALER\G3\Core\Attributes\Middleware;
use JEALER\G3\Core\Attributes\Schema;
use JEALER\G3\Middleware\RateLimitMiddleware;
use JEALER\G3\Services\AuthService;
use JEALER\G3\Services\SystemService;
use JEALER\G3\Utilities\Message;
use JEALER\G3\Utilities\Request;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class MenuController {

    /**
     * Get menu items by location.
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: 'menu/query',
        methods: 'POST'
    )]
    #[Schema([
        'type'       => 'object',
        'required'   => ['location'],
        'properties' => [
            'location' => [
                'type'      => 'string',
                'minLength' => 1,
            ]
        ]
    ])]
    // #[Middleware(RateLimitMiddleware::class, [20, 60])]
    public function handler(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data = $request->get_json_params();

        $location = $data['location'];

        $cacheKey = SystemService::MENU_CACHE_KEY_PREFIX . ':' . $location;
        $cache    = wp_cache_get($cacheKey, SystemService::MENU_CACHE_GROUP);
        if ($cache) {
            return rest_ensure_response([
                'success' => true,
                'code'    => 200,
                'data'    => $cache,
            ]);
        }

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
        $response = array_values($formattedItems);
        // 1 week cache for menu items query
        wp_cache_set($cacheKey, $response, SystemService::MENU_CACHE_GROUP, WEEK_IN_SECONDS);
        return rest_ensure_response([
            'success' => true,
            'code'    => 200,
            'data'    => $response,
        ]);
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
