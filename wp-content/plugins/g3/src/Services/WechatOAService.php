<?php
namespace JEALER\G3\Services;
use EasyWeChat\OfficialAccount\Application;
use JEALER\G3\Includes\EasyWechatCache;
use JEALER\G3\Services\SystemService;
use WP_Error;
class WechatOAService {

    /**
     * Menu Table Name for Wechat Official Account
     * 
     * 微信公众号菜单数据表名
     * 
     * @var string
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public const MENU_TABLE = 'g3_wechat_oa_menus';

    /**
     * Messages Table Name for Wechat Official Account
     * 
     * 微信公众号消息数据表名
     * 
     * @var string
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public const MESSAGES_TABLE = 'g3_wechat_oa_messages';

    /**
     * Option Key for Wechat OA
     * 
     * 微信公众号选项键
     * 
     * @var string
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public const OPTION_KEY = 'g3_option_wechatOA';

    /**
     * Cache Group for Wechat Official Account
     * 
     * 微信公众号缓存组
     * 
     * @var string
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public const CACHE_GROUP = 'wechat-oa';

    /**
     * Menu Cache Key for Wechat Official Account
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
     * Instance of WechatOAService
     * 
     * 微信公众号服务实例
     * 
     * @var WechatOAService
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
        $this->init();
    }

    private function init()
    {
        $service = get_option(self::OPTION_KEY)['service'] ?? false;
        if ($service) {
            $this->app = new Application($this->config());
            // Messages Handle
            $this->app->getServer()->withHandler(function ($message) {
                $this->processIncomingMessage($message);
                return $this->handleReply($message);
            });
        }
    }

    public function isAvailable(): bool
    {
        return isset($this->app) && $this->app instanceof Application;
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
        $data = get_option(SystemService::OPEN_WECHAT_OA_KEY);
        error_log('WeChat OA Config Data from SystemService::OPEN_WECHAT_OA_KEY: ' . print_r($data, true));

        $result = [
            'app_id' => $data['appId'] ?? '',
            'secret' => $data['appSecret'] ?? '',
            'cache'  => new EasyWechatCache()
        ];

        if (!empty($data['token'])) {
            $result['token'] = $data['token'];
        }
        if (!empty($data['encodingAESKey'])) {
            $result['aes_key'] = $data['encodingAESKey'];
        }

        error_log('WeChat OA Final Config: ' . print_r($result, true));
        return $result;
    }

    /**
     * Create menus for Wechat OA
     * 
     * 创建微信公众号菜单
     * 
     * @since 1.0.0
     * @author Wang Shai
     */
    public function createMenus(): bool
    {
        $buttons = self::formatMenusToTree(self::getMenus());
        $result  = $this->app->getClient()->postJson('cgi-bin/menu/create', [
            'button' => $buttons
        ])->toArray();

        return $result['errcode'] == 0 ? true : false;
    }

    /**
     * Delete menus for Wechat OA
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
            $result = $this->app->getClient()->get('cgi-bin/menu/delete')->toArray();
            error_log('Menu deleted result with get: ' . print_r($result, true));
        } else {
            $result = $this->app->getClient()->postJson('cgi-bin/menu/delconditional', [
                'menuid' => $menuId
            ]);
            error_log('Menu deleted result with postJson: ' . print_r($result, true));
        }
        return isset($result['errcode']) && $result['errcode'] == 0 ? true : false;
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
                $node['type'] = self::renderMenuType($item['type']);
                $node['name'] = $item['name'];

                // Set corresponding fields according to type
                match ($node['type']) {
                    'view' => $node['url'] = $item['value'],
                    'click' => $node['key'] = $item['value'],
                    default => $node['key'] = $item['value'],
                };
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

    /**
     * Render menu type
     * 
     * 渲染菜单类型
     * 
     * @param string $type Menu type
     * @return string Formatted menu type
     * @since 1.0.0
     * @author Wang Shai
     */
    private static function renderMenuType(string $type): string
    {
        return match ($type) {
            '1' => 'view',
            '2' => 'click',
            default => '-'
        };
    }


    /**
     * Save received message to database
     * 
     * 保存接收到的消息到数据库
     * 
     * @param array $message Message data
     * @return bool|int Number of rows affected, or false on error
     * @since 1.0.0
     * @author Wang Shai
     */
    public function saveMessage(array $message)
    {
        global $wpdb;
        $table = $wpdb->prefix . self::MESSAGES_TABLE;

        // Prepare message data
        $data = [
            'msgid'    => $message['MsgID'] ?? '',
            'openid'   => $message['FromUserName'] ?? '',
            'nickname' => $message['Nickname'] ?? '',
            'type'     => $message['MsgType'] ?? '',
            'content'  => '',
            'created'  => $message['CreateTime'] ?? 0,
        ];

        // Handle different message types
        switch ($message['MsgType']) {
            case 'text':
                $data['content'] = $message['Content'] ?? '';
                break;
            case 'image':
                $data['content'] = $message['PicUrl'] ?? '';
                break;
            case 'voice':
                $data['content'] = $message['Recognition'] ?? $message['MediaId'] ?? '';
                break;
            case 'video':
            case 'shortvideo':
                $data['content'] = $message['MediaId'] ?? '';
                break;
            case 'location':
                $data['content'] = sprintf(
                    'Location: (%s, %s), Label: %s',
                    $message['Location_X'] ?? '',
                    $message['Location_Y'] ?? '',
                    $message['Label'] ?? ''
                );
                break;
            case 'link':
                $data['content'] = sprintf(
                    'Title: %s, Description: %s, Url: %s',
                    $message['Title'] ?? '',
                    $message['Description'] ?? '',
                    $message['Url'] ?? ''
                );
                break;
            default:
                $data['content'] = 'Unsupported message type';
                break;
        }

        // Insert message into database
        $result = $wpdb->insert($table, $data);

        // Clear message cache
        wp_cache_delete('messages:count', self::CACHE_GROUP);
        wp_cache_delete('messages:latest', self::CACHE_GROUP);

        return $result;
    }

    /**
     * Get messages from database
     * 
     * 从数据库获取消息
     * 
     * @param int $page Page number
     * @param int $per_page Number of items per page
     * @param array $conditions Query conditions
     * @return array Messages data
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function getMessages(int $page = 1, int $per_page = 20, array $conditions = []): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::MESSAGES_TABLE;

        // Build query conditions
        $where  = 'WHERE 1=1';
        $params = [];

        if (!empty($conditions['openid'])) {
            $where    .= ' AND openid = %s';
            $params[]  = $conditions['openid'];
        }

        if (!empty($conditions['type'])) {
            $where    .= ' AND type = %s';
            $params[]  = $conditions['type'];
        }

        // Calculate offset
        $offset = ($page - 1) * $per_page;

        // Prepare query
        $query    = "SELECT * FROM {$table} {$where} ORDER BY created DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        // Execute query
        $prepared_query = $wpdb->prepare($query, $params);
        $messages       = $wpdb->get_results($prepared_query, ARRAY_A);

        return $messages ?: [];
    }

    /**
     * Get total count of messages
     * 
     * 获取消息总数
     * 
     * @param array $conditions Query conditions
     * @return int Total count of messages
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function getMessageCount(array $conditions = []): int
    {
        $cache_key = 'messages:count:query_' . md5(serialize($conditions));
        $count     = wp_cache_get($cache_key, self::CACHE_GROUP);

        if (false === $count) {
            global $wpdb;
            $table = $wpdb->prefix . self::MESSAGES_TABLE;

            // Build query conditions
            $where  = 'WHERE 1=1';
            $params = [];

            if (!empty($conditions['openid'])) {
                $where    .= ' AND openid = %s';
                $params[]  = $conditions['openid'];
            }

            if (!empty($conditions['type'])) {
                $where    .= ' AND type = %s';
                $params[]  = $conditions['type'];
            }

            // Prepare query
            $query          = "SELECT COUNT(*) FROM {$table} {$where}";
            $prepared_query = $wpdb->prepare($query, $params);
            $count          = (int) $wpdb->get_var($prepared_query);

            // Cache result for 5 minutes
            wp_cache_set($cache_key, $count, self::CACHE_GROUP, 300);
        }

        return $count;
    }

    /**
     * Get message by ID
     * 
     * 根据ID获取消息
     * 
     * @param int $id Message ID
     * @return array|null Message data or null if not found
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function getMessageById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $cache_key = 'wechat_message_' . $id;
        $message   = wp_cache_get($cache_key, self::CACHE_GROUP);

        if (false === $message) {
            global $wpdb;
            $table = $wpdb->prefix . self::MESSAGES_TABLE;

            $message = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
                ARRAY_A
            );

            if ($message) {
                wp_cache_set($cache_key, $message, self::CACHE_GROUP, 3600);
            }
        }

        return $message ?: null;
    }

    /**
     * Delete message by ID
     * 
     * 根据ID删除消息
     * 
     * @param int $id Message ID
     * @return bool|int Number of rows affected, or false on error
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function deleteMessage(int $id): bool|int
    {
        if ($id <= 0) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . self::MESSAGES_TABLE;

        $result = $wpdb->delete($table, ['id' => $id]);

        if ($result) {
            // Clear related caches
            wp_cache_delete('wechat_message_' . $id, self::CACHE_GROUP);
            wp_cache_delete('messages:count', self::CACHE_GROUP);
            wp_cache_delete('wechat_messages_latest', self::CACHE_GROUP);
        }

        return $result;
    }

    /**
     * Delete multiple messages
     * 
     * 删除多条消息
     * 
     * @param array $ids Message IDs
     * @return bool|int Number of rows affected, or false on error
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function deleteMessages(array $ids): bool|int
    {
        if (empty($ids)) {
            return false;
        }

        // Sanitize IDs
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, function ($id) {
            return $id > 0;
        });

        if (empty($ids)) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . self::MESSAGES_TABLE;

        // Create placeholders for prepared statement
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $query        = "DELETE FROM {$table} WHERE id IN ({$placeholders})";

        // Clear related caches
        foreach ($ids as $id) {
            wp_cache_delete('wechat_message_' . $id, self::CACHE_GROUP);
        }
        wp_cache_delete('messages:count_', self::CACHE_GROUP);
        wp_cache_delete('wechat_messages_latest', self::CACHE_GROUP);

        return $wpdb->query($wpdb->prepare($query, $ids));
    }

    /**
     * Process incoming message from WeChat server
     * 
     * 处理来自微信服务器的入站消息
     * 
     * @param array $message Message data from EasyWeChat
     * @return bool Whether the message was processed successfully
     * @since 1.0.0
     * @author Wang Shai
     */
    public function processIncomingMessage(array $message): bool
    {
        try {
            // Validate message
            if (empty($message['FromUserName']) || empty($message['MsgType'])) {
                error_log('Invalid WeChat message: missing required fields');
                return false;
            }

            // Save message to database
            $result = $this->saveMessage($message);

            if (false === $result) {
                error_log(message: 'Failed to save WeChat message to database');
                return false;
            }

            // Trigger action for other plugins or modules to hook into
            do_action('g3_wechat_message_received', $message);

            return true;
        }
        catch (\Exception $e) {
            error_log('Error processing WeChat message: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user information by OpenID
     * 
     * 根据OpenID获取用户信息
     * 
     * @param string $openid User OpenID
     * @return array|null User information or null if failed
     * @since 1.0.0
     * @author Wang Shai
     */
    public function getUserInfo(string $openid): ?array
    {
        if (empty($openid)) {
            return null;
        }

        try {
            $cache_key = 'users:' . md5($openid);
            $user_info = wp_cache_get($cache_key, self::CACHE_GROUP);

            if (false === $user_info) {
                $response = $this->app->getClient()->get('cgi-bin/user/info', [
                    'query' => [
                        'openid' => $openid,
                        'lang'   => 'zh_CN'
                    ]
                ]);

                $result = $response->toArray();

                if (isset($result['errcode']) && $result['errcode'] != 0) {
                    error_log('Failed to get user info: ' . $result['errmsg']);
                    return null;
                }

                $user_info = $result;
                // Cache for 30 minutes
                wp_cache_set($cache_key, $user_info, self::CACHE_GROUP, 1800);
            }

            return $user_info ?: null;
        }
        catch (\Exception $e) {
            error_log('Error getting user info: ' . $e->getMessage());
            return null;
        }
    }
    private function handleReply(array $message): ?string
    {
        // 根据消息类型进行回复
        switch ($message['MsgType']) {
            case 'text':
                // 文本消息处理
                return $this->handleTextMessage($message);
            case 'event':
                // 事件消息处理
                return $this->handleEventMessage($message);
            default:
                // 默认回复
                return __('Hello, thanks for your message!', 'G3');
        }
    }

    private function handleTextMessage(array $message): ?string
    {
        $content = $message['Content'] ?? '';

        // 关键词回复示例
        if (strpos($content, '你好') !== false) {
            return __('Hello! How can I help you?', 'G3');
        }

        // 默认回复
        return __('Message received, thank you!', 'G3');
    }

    private function handleEventMessage(array $message): ?string
    {
        $event = $message['Event'] ?? '';

        switch ($event) {
            case 'subscribe':
                // 关注事件
                return __('Welcome! Thanks for subscribing to our account.', 'G3');
            case 'unsubscribe':
                // 取消关注事件
                return null; // 不回复
            default:
                return null;
        }
    }


}