<?php
namespace JEALER\G3\Services;
use EasyWeChat\OfficialAccount\Application;
use JEALER\G3\Includes\EasyWechatCache;
use JEALER\G3\Services\SystemService;
class WechatMPService {

    /**
     * Menu Table Name for Wechat MP
     * 
     * 微信公众号菜单数据表名
     * 
     * @var string
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public const MENU_TABLE = 'g3_wechat_mp_menus';

    /**
     * Option Key for Wechat MP
     * 
     * 微信公众号选项键
     * 
     * @var string
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public const OPTION_KEY = 'g3_option_wechatMP';

    /**
     * Cache Group for Wechat MP
     * 
     * 微信公众号缓存组
     * 
     * @var string
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public const CACHE_GROUP = 'wechat-MP';

    /**
     * Menu Cache Key for Wechat MP
     * 
     * 微信公众号菜单缓存键
     * 
     * @var string
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public const MENU_CACHE_KEY = 'menus';

    /**
     * Instance of WechatMPService
     * 
     * 微信公众号服务实例
     * 
     * @var WechatMPService
     * @access private
     * @since 1.0.0
     * @author Wang Shai
     */
    public static $instance = null;

    /**
     * EasyWeChat Application Instance
     * 
     * EasyWeChat 应用实例
     * 
     * @var Application
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public Application $app;

    public function __construct()
    {
        $this->app = new Application($this->config());
    }

    public static function run()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function config(): array
    {
        $data = get_option(SystemService::OPEN_MP_KEY);
        return [
            'app_id' => $data['appId'] ?? '',
            'secret' => $data['appSecret'] ?? '',
            // 'token'  => $data['token'] ?? '',
            // 'aes_key' => $data['encodingAESKey'] ?? 'EncodingAESKey',
            'cache'  => new EasyWechatCache()
        ];
    }

    /**
     * Create menus for Wechat MP
     * 
     * 创建微信公众号菜单
     * 
     * @since 1.0.0
     * @author Wang Shai
     */
    public function createMenus(): bool
    {
        $buttons  = self::formatMenusToTree(self::getMenus());
        $result   = $this->app->getClient()->postJson('cgi-bin/menu/create', [
            'button' => $buttons
        ]);
        $response = $result->toArray();
        return $response['errcode'] == 0 ? true : false;
    }

    /**
     * Delete menus for Wechat MP
     * 
     * 删除微信公众号菜单
     * 
     * @param int $menuId Menu ID
     * @return bool
     * @since 1.0.0
     * @author Wang Shai
     */
    public function deleteMenus(int $menuId = 0): bool
    {
        if ($menuId == 0) {
            $result = $this->app->getClient()->get('cgi-bin/menu/delete');
        } else {
            $result = $this->app->getClient()->postJson('cgi-bin/menu/delconditional', [
                'menuid' => $menuId
            ]);
        }
        $response = $result->toArray();
        return $response['errcode'] == 0 ? true : false;
    }

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
            $table = $wpdb->prefix . self::MENU_TABLE;
            $menus = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
            if (!empty($menus)) {
                wp_cache_set(self::MENU_CACHE_KEY, $menus, self::CACHE_GROUP);
            }
        }
        return $menus;
    }

    /**
     * Format menus to tree
     * 
     * 将菜单数据格式化为树形结构，满足微信公众号菜单创建的数据格式要求
     * 
     * @param array $menus Menu data
     * @return array Tree structure
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function formatMenusToTree(array $menus): array
    {
        // Build a group with parent as the key
        $grouped = [];
        foreach ($menus as $menu) {
            $grouped[$menu['parent']][] = $menu;
        }

        // Build tree according to parent=0
        return self::buildTree($grouped, 0);
    }

    /**
     * Recursively build tree
     * 
     * 递归构建树结构
     * 
     * @param array $grouped Grouped menu data
     * @param int $parentId Parent ID
     * @return array Tree structure
     */
    private static function buildTree(array &$grouped, int $parentId): array
    {
        if (!isset($grouped[$parentId])) {
            return [];
        }

        // Sort by sort
        usort($grouped[$parentId], function ($a, $b) {
            return ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0);
        });

        $tree = [];

        foreach ($grouped[$parentId] as $item) {
            $node = [];

            // Has sub menu
            if (isset($grouped[$item['id']])) {
                $node['name']       = $item['name'];
                $node['sub_button'] = self::buildTree($grouped, $item['id']);
            } else {
                // Last level button
                $node = [
                    'type' => self::renderMenuType($item['type']),
                    'name' => $item['name'],
                    'key'  => $item['value'],
                ];
            }

            $tree[] = $node;
        }

        return $tree;
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
            $table = $wpdb->prefix . self::MENU_TABLE;
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
        $table = $wpdb->prefix . self::MENU_TABLE;
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
        $table  = $wpdb->prefix . self::MENU_TABLE;
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