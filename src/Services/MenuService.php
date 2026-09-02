<?php
namespace JEALER\G3\Services;
use JEALER\G3\Core\Service\Service;
use WP_Post;
use WP_Term;
use WP_Error;

class MenuService extends Service {
    const CACHE_GROUP = 'g3_menu_json';

    /**
     * Render menu HTML from cached menu data.
     * 
     * 基于缓存后的菜单数据渲染 HTML。
     * 
     * - theme_location: 菜单位置。默认 desktop-header
     * - container: 容器元素。默认 nav
     * - container_class: 容器类名。默认 j-menu
     * - container_id: 容器ID。默认 ''
     * - menu_class: 菜单类名。默认 menu
     * - menu_id: 菜单ID。默认 ''
     * - fallback_cb: 回退函数。默认 false
     * - depth: 深度。默认 2
     * - logged_in: 用户登录状态。默认 false
     * 
     * @param array $args
     * @return string|false
     */
    public function html(array $args = []): string|false
    {
        $defaults = [
            'theme_location'  => 'desktop-header',
            'container'       => 'nav',
            'container_class' => 'j-menu',
            'container_id'    => '',
            'menu_class'      => 'menu',
            'menu_id'         => '',
            'fallback_cb'     => false,
            'depth'           => 2,
            'echo'            => false,
            'logged_in'       => false,
        ];
        $params   = array_merge($defaults, $args);

        $items = $this->getData((string) $params['theme_location']);
        if (is_wp_error($items)) {
            $menu = false;
        } else {
            $items = $this->filterMenuItemsForUser($items, (bool) $params['logged_in']);
            $menu  = $this->renderMenu($items, $params);
        }

        return $menu;
    }

    /**
     * Get menu items in format data. 7 days cache.
     * 
     * 获取菜单项的格式化数据。7天缓存。
     * 
     * @param string $location
     * @return array|WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    public function getData(string $location): array|WP_Error
    {
        $cacheKey = $this->getCacheKey($location);
        $cache    = wp_cache_get($cacheKey, self::CACHE_GROUP);
        if (is_array($cache)) {
            return $cache;
        }

        $locations = get_nav_menu_locations();
        if (!isset($locations[$location])) {
            return new WP_Error('invalid_location', 'Invalid location', ['status' => 400]);
        }

        $menuId = $locations[$location];
        $menu   = wp_get_nav_menu_object($menuId);
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

        update_meta_cache('post', array_map('intval', wp_list_pluck($menuItems, 'ID')));

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

        if (empty($formattedItems)) {
            return new WP_Error(
                'no_items',
                'No items found.',
                ['status' => 404]
            );
        }

        $result = array_values($formattedItems);

        wp_cache_set($cacheKey, $result, self::CACHE_GROUP, WEEK_IN_SECONDS);

        return $result;
    }

    private function getCacheKey(string $location): string
    {
        return md5($location);
    }

    private function filterMenuItemsForUser(array $items, bool $loggedIn): array
    {
        $filtered = [];
        foreach ($items as $item) {
            $type = (int) ($item['type'] ?? 0);
            if (($type === 1 && !$loggedIn) || ($type === 2 && $loggedIn)) {
                continue;
            }

            $item['children'] = $this->filterMenuItemsForUser($item['children'] ?? [], $loggedIn);
            $filtered[]       = $item;
        }

        return $filtered;
    }

    private function renderMenu(array $items, array $params): string|false
    {
        if (empty($items)) {
            return false;
        }

        $depth          = (int) ($params['depth'] ?? 0);
        $currentContext = $this->getCurrentUrlContext();
        [$itemsHtml]    = $this->renderMenuItems($items, $depth, 0, $currentContext);
        if ($itemsHtml === '') {
            return false;
        }

        $menuAttrs = $this->buildAttributes([
            'id'    => $params['menu_id'] ?: null,
            'class' => $params['menu_class'] ?: null,
        ]);
        $menuHtml  = '<ul' . $menuAttrs . '>' . $itemsHtml . '</ul>';

        $container = $params['container'] ?? false;
        if ($container === false || $container === '') {
            return $menuHtml;
        }

        $tag = tag_escape((string) $container);
        if ($tag === '') {
            $tag = 'div';
        }

        $containerAttrs = $this->buildAttributes([
            'id'    => $params['container_id'] ?: null,
            'class' => $params['container_class'] ?: null,
        ]);

        return '<' . $tag . $containerAttrs . '>' . $menuHtml . '</' . $tag . '>';
    }

    private function renderMenuItems(array $items, int $maxDepth, int $level, array $currentContext): array
    {
        if ($maxDepth > 0 && $level >= $maxDepth) {
            return ['', false];
        }

        $html      = '';
        $hasActive = false;
        foreach ($items as $item) {
            [$itemHtml, $itemActive]  = $this->renderMenuItem($item, $maxDepth, $level, $currentContext);
            $html                    .= $itemHtml;
            $hasActive                = $hasActive || $itemActive;
        }

        return [$html, $hasActive];
    }

    private function renderMenuItem(array $item, int $maxDepth, int $level, array $currentContext): array
    {
        $children                     = $item['children'] ?? [];
        [$childrenHtml, $childActive] = $this->renderMenuItems($children, $maxDepth, $level + 1, $currentContext);

        $active  = $this->isCurrentMenuItem($item, $currentContext);
        $classes = $this->getMenuItemClasses($item, $level, $childrenHtml !== '', $active, $childActive);

        $attrs = $this->buildAttributes([
            'id'    => 'menu-item-' . (int) ($item['id'] ?? 0),
            'class' => implode(' ', $classes),
        ]);

        $linkAttrs = $this->buildAttributes([
            'href'   => $item['url'] ?? '#',
            'target' => ($item['target'] ?? '') ?: null,
            'title'  => ($item['description'] ?? '') ?: null,
            'rel'    => ($item['xfn'] ?? '') ?: null,
        ], true);

        $html  = '<li' . $attrs . '>';
        $html .= '<a' . $linkAttrs . '>' . esc_html((string) ($item['title'] ?? '')) . '</a>';
        if ($childrenHtml !== '') {
            $html .= '<ul class="sub-menu">' . $childrenHtml . '</ul>';
        }
        $html .= '</li>';

        return [$html, $active || $childActive];
    }

    private function getMenuItemClasses(array $item, int $level, bool $hasChildren, bool $active, bool $childActive): array
    {
        $classes = array_filter(array_map('sanitize_html_class', array_merge(
            ['menu-item', 'menu-item-' . (int) ($item['id'] ?? 0)],
            preg_split('/\s+/', (string) ($item['classes'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: []
        )));

        if ($level === 0 && !empty($item['menu_type'])) {
            $classes[] = sanitize_html_class((string) $item['menu_type']);
        }

        if ($hasChildren) {
            $classes[] = 'menu-item-has-children';
        }

        if ($active) {
            $classes[] = 'current-menu-item';
        } elseif ($childActive) {
            $classes[] = 'current-menu-ancestor';
        }

        return array_values(array_unique($classes));
    }

    private function buildAttributes(array $attributes, bool $url = false): string
    {
        $result = '';
        foreach ($attributes as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $escapedValue  = $url && $key === 'href' ? esc_url((string) $value) : esc_attr((string) $value);
            $result       .= ' ' . esc_attr((string) $key) . '="' . $escapedValue . '"';
        }

        return $result;
    }

    private function getCurrentUrlContext(): array
    {
        $queriedObject = get_queried_object();
        $itemType      = match (true) {
            $queriedObject instanceof WP_Post => 'post_type',
            $queriedObject instanceof WP_Term => 'taxonomy',
            default                           => '',
        };

        return [
            'host'              => strtolower((string) ($_SERVER['HTTP_HOST'] ?? '')),
            'path'              => $this->normalizeUrlPath((string) ($_SERVER['REQUEST_URI'] ?? '/')),
            'queried_object_id' => (int) get_queried_object_id(),
            'item_type'         => $itemType,
        ];
    }

    private function isCurrentMenuItem(array $item, array $currentContext): bool
    {
        $objectId = (int) ($item['object_id'] ?? 0);
        $itemType = (string) ($item['item_type'] ?? '');
        if (
            $objectId > 0 &&
            $objectId === $currentContext['queried_object_id'] &&
            ($itemType === '' || $itemType === $currentContext['item_type'])
        ) {
            return true;
        }

        $url = (string) ($item['url'] ?? '');
        if ($url === '' || $url === '#') {
            return false;
        }

        $itemHost = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($itemHost !== '' && $currentContext['host'] !== '' && $itemHost !== $currentContext['host']) {
            return false;
        }

        return $this->normalizeUrlPath($url) === $currentContext['path'];
    }

    private function normalizeUrlPath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        $path = '/' . ltrim(rawurldecode($path), '/');
        return untrailingslashit($path) ?: '/';
    }

    /**
     * format a menu item and its children recursively.
     * 
     * 递归格式化菜单项及其子项。
     * 
     * @param array $item The menu item data.
     * @return array The formatted menu item.
     */
    private function formatMenuItem(array $item): array
    {
        $formattedItem = [
            'id'               => $item['ID'],
            'title'            => $item['title'],
            'url'              => $item['url'],
            'type'             => $this->getMenuItemDisplayType($item['ID']),
            'item_type'        => $item['type'] ?? '',
            'object'           => $item['object'] ?? '',
            'object_id'        => (int) ($item['object_id'] ?? 0),
            'menu_type'        => $this->getMenuItemMenuType($item['ID']),
            'target'           => $item['target'] ?? '',
            'description'      => $item['description'] ?? '',
            'classes'          => !empty($item['classes']) ? implode(' ', $item['classes']) : '',
            'xfn'              => $item['xfn'] ?? '',
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

    private function getMenuItemMenuType(int|string $itemId): string
    {
        return (string) get_post_meta((int) $itemId, '_menu_item_menu_type', true);
    }
}
