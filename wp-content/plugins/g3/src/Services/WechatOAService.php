<?php
namespace JEALER\G3\Services;

use EasyWeChat\OfficialAccount\Application;
use EasyWeChat\Kernel\Message;
use JEALER\G3\Includes\EasyWechatCache;
use JEALER\G3\Services\SystemService;
use JEALER\G3\Services\PostService;
use JEALER\G3\Utilities\Common;
use WP_Error;
use Exception;
use Closure;
use WP_User_Query;

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
     * Reply Table Name for Wechat Official Account
     * 
     * 微信公众号回复数据表名
     * 
     * @var string
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public const REPLY_TABLE = 'g3_wechat_oa_reply';

    /**
     * Keyword Table Name for Wechat Official Account
     * 
     * 微信公众号关键词数据表名
     * 
     * @var string
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public const KEYWORD_TABLE = 'g3_wechat_oa_reply_keyword';

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
     * Option Key for Wechat OA Event
     * 
     * 微信公众号事件选项键
     * 
     * @var string
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public const EVENT_OPTION_KEY = 'g3_option_wechatOA_event';

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
     * Callback URL for Wechat Official Account
     * 
     * 微信公众号回调URL
     * 
     * @var string
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public const CALLBACK = '/dev/wechat_oa/callback';

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

    public array $option;

    public function __construct()
    {
        $this->init();
    }

    private function init(): void
    {
        $this->option  = get_option(self::OPTION_KEY);
        $serviceEnable = $this->option['service'] ?? false;

        if ($serviceEnable) {
            $this->app = new Application($this->config());
            $server    = $this->app->getServer();

            // Middleware to process incoming message
            $server->with(function ($message, Closure $next) {
                // Process incoming message
                $this->processIncomingMessage($message);
                return $next($message);
            })->with(function ($message, Closure $next) {
                // Handle reply
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

        return $result;
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
    public function saveMessage(array $message): bool|int
    {
        global $wpdb;
        $table = $wpdb->prefix . self::MESSAGES_TABLE;

        // Get user nickname from WeChat API if possible
        $openid   = $message['FromUserName'] ?? '';
        $nickname = '';

        if (!empty($openid) && $this->isAvailable()) {
            try {
                $userInfo = $this->getUserInfo($openid);
                $nickname = $userInfo['nickname'] ?? '-';
            }
            catch (Exception $e) {
                error_log('saveMessage Notice, Failed to get user info: ' . $e->getMessage());
            }
        }

        // Prepare message data
        $data = [
            'msgid'    => $message['MsgID'] ?? $message['MsgId'] ?? '',
            'openid'   => $openid,
            'nickname' => $nickname,
            'type'     => $message['MsgType'] ?? '',
            'content'  => '',
            'created'  => !empty($message['CreateTime']) ? gmdate('Y-m-d H:i:s', $message['CreateTime']) : current_time('mysql'),
        ];

        // Handle timestamp conversion if available
        if (!empty($message['CreateTime'])) {
            // Convert timestamp to MySQL datetime format
            $data['created'] = date('Y-m-d H:i:s', $message['CreateTime']);
        }

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

    public static function getMessageContent(int $id): string
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::MESSAGES_TABLE;
        $message = $wpdb->get_row($wpdb->prepare("SELECT content FROM $table WHERE id = %d", $id), ARRAY_A);
        return $message['content'] ?? '';
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
        if (!empty($params)) {
            $prepared_query = $wpdb->prepare($query, $params);
            $messages       = $wpdb->get_results($prepared_query, ARRAY_A);
        } else {
            $messages = $wpdb->get_results($wpdb->prepare($query, $per_page, $offset), ARRAY_A);
        }

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
        // 根据条件是否为空使用不同的缓存键
        $cache_key = empty($conditions) ? 'messages:count' : 'messages:count:query_' . md5(serialize($conditions));
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
            $query = "SELECT COUNT(*) FROM {$table} {$where}";

            if (!empty($params)) {
                // 如果有条件参数，使用 prepare
                $prepared_query = $wpdb->prepare($query, $params);
                $count          = (int) $wpdb->get_var($prepared_query);
            } else {
                // 如果没有条件参数，直接执行查询
                $count = (int) $wpdb->get_var($query);
            }

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
     * Delete message by ID (alias for deleteMessages)
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
        return self::deleteMessages($id);
    }

    /**
     * Delete message(s) by ID(s)
     * 
     * 根据ID删除消息（单条或多条）
     * 
     * @param int|array $ids Message ID or array of Message IDs
     * @return bool|int Number of rows affected, or false on error
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function deleteMessages(int|array $ids): bool|int
    {
        // 如果是单个ID，转换为数组
        if (!is_array($ids)) {
            $ids = [$ids];
        }

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

        // 如果只有一个ID，使用DELETE ... WHERE id = ? 查询
        if (count($ids) == 1) {
            $id = reset($ids);

            // 清除相关缓存
            wp_cache_delete('wechat_message_' . $id, self::CACHE_GROUP);
            wp_cache_delete('messages:count', self::CACHE_GROUP);
            wp_cache_delete('wechat_messages_latest', self::CACHE_GROUP);

            return $wpdb->delete($table, ['id' => $id]);
        } else {
            // 如果有多个ID，使用 DELETE ... WHERE id IN (...) 查询
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $query        = "DELETE FROM {$table} WHERE id IN ({$placeholders})";

            // 清除相关缓存
            foreach ($ids as $id) {
                wp_cache_delete('wechat_message_' . $id, self::CACHE_GROUP);
            }
            wp_cache_delete('messages:count', self::CACHE_GROUP);
            wp_cache_delete('wechat_messages_latest', self::CACHE_GROUP);

            // 确保有参数传递给 prepare 方法
            if (!empty($ids)) {
                return $wpdb->query($wpdb->prepare($query, $ids));
            } else {
                return false;
            }
        }
    }

    /**
     * Process incoming message from WeChat server
     * 
     * 处理来自微信服务器的入站消息
     * 
     * @param mixed $message Message data from EasyWeChat
     * @return bool Whether the message was processed successfully
     * @since 1.0.0
     * @author Wang Shai
     */
    public function processIncomingMessage(mixed $message): bool
    {
        try {
            $messageArray = $this->normalizeMessage($message);

            // Validate message
            if (empty($messageArray['FromUserName']) || empty($messageArray['MsgType'])) {
                return false;
            }

            // Save message to database
            $result = $this->saveMessage($messageArray);

            if (false === $result) {
                return false;
            }

            // Trigger action for other plugins or modules to hook into
            do_action('g3_action_wechat_message_received', $messageArray);

            return true;
        }
        catch (\Exception $e) {
            error_log('Error processing WeChat message: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle reply for WeChat
     * 
     * 处理微信回复
     * 
     * @param $message
     * @return string
     * @since 1.0.0
     * @author Wang Shai
     */
    private function handleReply($message)
    {
        /**
         * @deprecated use $message directly instead
         */
        // $message = $this->normalizeMessage($message);

        $reply = null;

        $reply = match ($message->MsgType) {
            'text' => $this->handleTextMessage($message),
            'event' => $this->handleEventMessage($message),
            default => 'Hello, thanks for your message!',
        };
        return $reply;
    }

    /**
     * Build Search URL
     * 
     * 构建搜索URL
     * 
     * @param string $keyword
     * @return string
     * @since 1.0.0
     * @author Wang Shai
     */
    private function searchUrl(string $keyword): string
    {
        return home_url('/') . '?s=' . urlencode($keyword);
    }
    /**
     * Search message reply
     * 
     * 搜索消息回复
     * 
     * @param string $content
     * @return string
     * @since 1.0.0
     * @author Wang Shai
     */
    private function searchReply(string $content): string
    {
        $title       = __('Search for: ', 'G3') . $content;
        $description = sprintf(__('Click here to view "%s" search results.', 'G3'), $content);
        $url         = $this->searchUrl($content);

        // Build text with link
        $text = sprintf(
            "%s\n\n<a href=\"%s\">%s</a>",
            $title,
            $url,
            $description,
        );
        return $text;
    }

    /**
     * Handle text message from WeChat
     * 
     * 处理微信文本消息
     * 
     * @param $message
     * @return string
     * @since 1.0.0
     * @author Wang Shai
     */
    private function handleTextMessage($message): string
    {
        $content = $message->Content ?? '';

        // Check keyword length
        if ($this->checkKeywordLength($content) !== true) {
            return $this->checkKeywordLength($content);
        }

        $defaultReply = $this->option['defaultMessage'] ?? __('Message received, thanks for your advice!', 'G3');

        return $this->handleTextReply($content, $defaultReply);
    }

    /**
     * Handle text message reply from WeChat
     * 
     * 处理微信文本消息回复
     * 
     * @param string $content
     * @param string $defaultReply
     * @return string
     * @since 1.0.0
     * @author Wang Shai
     */
    private function handleTextReply(string $content, string $defaultReply): string
    {
        // Try to get auto reply by content
        $reply  = self::getReply($content);
        $status = (int) $reply['status'] ?? 0;

        // Check if the keyword reply is enabled
        if ($reply !== false && $status === 1) {
            return $reply['content'] ?? $defaultReply;
        }

        // Check if search is enabled
        if ($this->isSearchEnabled()) {
            return $this->searchReply($content);
        }

        return $defaultReply;
    }

    /**
     * Handle event message from WeChat
     * 
     * 处理微信事件消息
     * 
     * @param $message
     * @return string
     * @since 1.0.0
     * @author Wang Shai
     */
    private function handleEventMessage($message)
    {
        $event = $message->Event ?? '';

        return match ($event) {
            'subscribe' => $this->handleSubscribeEvent($message),
            'CLICK' => $this->handleClickEvent($message),
            default => null,
        };
    }
    private function handleSubscribeEvent($message)
    {
        $message  = $this->option['followMessage'] ?? __('Welcome! Thanks for your attention.', 'G3');
        $sceneStr = $message->EventKey ?? '';

        if (strpos($sceneStr, 'qrscene_') === 0) {
            $realScene = substr($sceneStr, 8);
            if ($realScene === 'g3_login_subscribe') {
                $openid = $message->FromUserName;
                $this->triggerLoginAfterSubscribe($openid);
            }
            return;
        } else {
            return $message;
        }
    }
    private function triggerLoginAfterSubscribe(string $openid)
    {
        // Notify AuthService: this openid user has subscribed, please login
        $authService = AuthService::run();
        $authService->handlePostSubscribeLogin($openid);
    }

    /**
     * Handle click event from WeChat menu
     * 
     * 处理微信菜单点击事件
     * 
     * @param 
     * @return 
     * @since 1.0.0
     * @author Wang Shai
     */
    private function handleClickEvent($message)
    {
        $eventKey = $message->EventKey ?? '';

        return match ($eventKey) {
            'n' => $this->getLatestPosts(),
            default => 'Event received, thank you!',
        };

        // switch ($eventKey) {
        //     case 'n': // 获取最新文章列表
        //         return $this->getLatestPosts();
        //     // case 'h': // 获取热门文章
        //     //     return $this->getHotPosts();
        //     // case 'c': // 获取分类文章
        //     //     return $this->getCategoryPosts();
        //     // case 's': // 搜索功能
        //     //     return $this->showSearchTips();
        //     default:
        //         // 尝试匹配自定义回复
        //         $reply = self::getReply($eventKey);
        //         if ($reply !== false && $reply['status'] == '1') {
        //             return $reply['content'] ?? 'Event received, thank you!';
        //         }
        //         return 'Event received, thank you!';
        // }
    }

    /**
     * Convert message to array format
     * 
     * 将消息转换为数组格式
     * 
     * @param mixed $message Message data (array or object)
     * @return array Message data in array format
     */
    private function normalizeMessage($message): array
    {
        if (is_array($message)) {
            return $message;
        }

        if (is_object($message)) {
            // Handle EasyWeChat Message object
            if (method_exists($message, 'toArray')) {
                return $message->toArray();
            } else {
                // Fallback: manually extract properties
                $result = [];
                foreach (get_object_vars($message) as $key => $value) {
                    $result[$key] = $value;
                }
                return $result;
            }
        }

        return [];
    }

    public function isSearchEnabled(): bool
    {
        $option = $this->option['search'] ?? false;
        return $option === '1';
    }

    /************************************************************
     * 
     * Wechat OA Reply Handle
     * 
     ************************************************************/

    public static function getReply(string $keyword)
    {
        if (empty($keyword)) {
            return false;
        }

        $cacheKey = 'reply:' . md5($keyword);
        $reply    = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if (false === $reply) {
            global $wpdb;

            $keywordTable = $wpdb->prefix . self::KEYWORD_TABLE;
            $replyId      = $wpdb->get_var($wpdb->prepare(
                "SELECT reply_id FROM {$keywordTable} WHERE keyword = %s",
                $keyword
            ));
            if (!$replyId) return false;

            $replyTable = $wpdb->prefix . self::REPLY_TABLE;
            $reply      = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$replyTable} WHERE id = %d",
                $replyId
            ), ARRAY_A);
            if (!$reply) return false;
            wp_cache_set($cacheKey, $reply, self::CACHE_GROUP);
        }

        return $reply;
    }

    /**
     * Update a reply rule. If id is 0 or does not exist, a new rule will be inserted.
     *
     * 更新一条自动回复规则，如果 id 为 0 或 不存在 则插入新规则
     *
     * @param array $data {
     *     @type string|array $keywords  关键词（字符串逗号分隔 或 数组）
     *     @type string       $content   回复内容
     *     @type string       $type      类型（如 'text', 'news'）
     *     @type int          $status    状态（1=启用, 0=禁用）
     * }
     * @return int|WP_Error reply_id | WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function updateReply(array $data): int|WP_Error
    {
        global $wpdb;

        // Normalize and validate keywords (max 100 chars per keyword)
        $keywords = self::normalizeKeywords($data['keywords'] ?? '');
        if (empty($keywords)) {
            return new WP_Error(
                'no_keywords',
                __('At least one keyword is required', 'G3')
            );
        }

        // Validate reply content
        $content = trim($data['content'] ?? '');
        if (empty($content)) {
            return new WP_Error(
                'empty_content',
                __('Reply content cannot be empty', 'G3')
            );
        }

        // Determine if it's an update or insert based on $data['id']
        $id        = $data['id'] ?? null;
        $isValidId = (is_numeric($id) && ($id = (int) $id) > 0);

        // === Keyword uniqueness check ===
        if ($isValidId) {
            // Update: allow self-keywords, exclude current reply_id
            foreach ($keywords as $keyword) {
                if (self::isKeywordExistsExcluding($keyword, $id)) {
                    return new WP_Error(
                        'keyword_exists',
                        sprintf(
                            __('Keyword %s already exists', 'G3'),
                            esc_html($keyword)
                        )
                    );
                }
            }
        } else {
            // Insert: strict global uniqueness
            foreach ($keywords as $keyword) {
                if (self::isKeywordExists($keyword)) {
                    return new WP_Error(
                        'keyword_exists',
                        sprintf(
                            __('Keyword %s already exists', 'G3'),
                            esc_html($keyword)
                        )
                    );
                }
            }
        }

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            if ($isValidId) {
                $result = self::_updateReply($id, $data, $keywords);
                foreach ($keywords as $keyword) {
                    $cacheKey = 'reply:' . md5($keyword);
                    wp_cache_delete($cacheKey, self::CACHE_GROUP);
                }
            } else {
                $result = self::_insertReply($data, $keywords);
                foreach ($keywords as $keyword) {
                    $cacheKey = 'reply:' . md5($keyword);
                    wp_cache_set($cacheKey, $result, self::CACHE_GROUP);
                }
            }

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            $wpdb->query('COMMIT');
            return $result;
        }
        catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('G3 WeChat OA - updateReply failed: ' . $e->getMessage());
            return new WP_Error(
                'db_error',
                __('Save failed, please try again later', 'G3')
            );
        }
    }

    /**
     * Normalize keyword input
     * 
     * 标准化关键词输入
     * 
     * @param mixed $input
     * @return array 去重、去空、trim 后的关键词数组（每个 ≤100 字符）
     * @since 1.0.0
     * @author Wang Shai
     */
    private static function normalizeKeywords($input): array
    {
        $keywords = [];

        if (is_array($input)) {
            $keywords = $input;
        } elseif (is_string($input)) {
            $keywords = explode(',', $input);
        }

        $keywords = array_map('trim', $keywords);
        $keywords = array_filter($keywords, function ($kw) {
            return !empty($kw) && strlen($kw) <= 100; // VARCHAR(100)
        });

        return array_unique($keywords);
    }

    /**
     * Check if keyword exists in keyword table (global uniqueness)
     * 
     * 检查关键词是否已存在于 keyword 表（全局唯一）
     * 
     * @param string $keyword
     * @return bool
     * @since 1.0.0
     * @author Wang Shai
     */
    private static function isKeywordExists(string $keyword): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . self::KEYWORD_TABLE;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$table} WHERE keyword = %s",
            $keyword
        ));
    }

    /**
     * Check if keyword exists for another reply_id (excluding self)
     * 
     * 检查关键词是否被其他 reply_id 占用（排除自身）
     * 
     * @param string $keyword
     * @param int $excludeReplyId
     * @return bool
     * @since 1.0.0
     * @author Wang Shai
     */
    private static function isKeywordExistsExcluding(string $keyword, int $excludeReplyId): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . self::KEYWORD_TABLE;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$table} WHERE keyword = %s AND reply_id != %d",
            $keyword,
            $excludeReplyId
        ));
    }

    /**
     * Insert a new reply rule
     * 
     * 插入新回复规则
     * 
     * @param array $data
     * @param array $keywords
     * @return int|WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    private static function _insertReply(array $data, array $keywords): int|WP_Error
    {
        $replyId = self::insertReply([
            'type'    => sanitize_text_field($data['type'] ?? 'text'),
            'content' => trim($data['content']),
            'status'  => (int) ($data['status'] ?? 1),
            'created' => current_time('mysql', true),
            'updated' => current_time('mysql', true)
        ]);

        if (!$replyId) {
            return new WP_Error('db_error', __('Failed to insert reply into main table', 'G3'));
        }

        self::batchInsertKeywords($replyId, $keywords);
        return $replyId;
    }

    /**
     * Update an existing reply rule
     * 
     * 更新现有回复规则
     * 
     * @param int $id
     * @param array $data
     * @param array $keywords
     * @return int|WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    private static function _updateReply(int $id, array $data, array $keywords): int|WP_Error
    {
        global $wpdb;
        $updated = $wpdb->update(
            $wpdb->prefix . self::REPLY_TABLE,
            [
                'type'    => sanitize_text_field($data['type'] ?? 'text'),
                'content' => trim($data['content']),
                'status'  => (int) ($data['status'] ?? 1),
                'updated' => current_time('mysql', true)
            ],
            ['id' => $id],
            ['%s', '%s', '%d', '%s'],
            ['%d']
        );

        if ($updated === false) {
            return new WP_Error('db_error', __('Failed to update reply in main table', 'G3'));
        }

        if ($updated === 0) {
            return new WP_Error('not_found', __('Reply not found', 'G3'));
        }

        // Synchronize keywords: delete all then re-insert
        self::batchUpdateKeywords($id, $keywords);

        return $id;
    }

    /**
     * Insert a new reply record into the main table
     * 
     * 插入主表记录
     * 
     * @param array $data
     * @return int|false
     * @since 1.0.0
     * @author Wang Shai
     */
    private static function insertReply(array $data): bool|int
    {
        global $wpdb;
        $table  = $wpdb->prefix . self::REPLY_TABLE;
        $result = $wpdb->insert($table, $data, ['%s', '%s', '%d', '%s', '%s']);
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Batch insert keywords (for new records)
     * 
     * 批量插入关键词（用于新增场景）
     * 
     * @param int $replyId
     * @param array $keywords
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    private static function batchInsertKeywords(int $replyId, array $keywords): void
    {
        if (empty($keywords)) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . self::KEYWORD_TABLE;

        $values       = [];
        $placeholders = [];

        foreach ($keywords as $keyword) {
            $values[]       = $replyId;
            $values[]       = $keyword;
            $placeholders[] = '(%d, %s)';
        }

        $sql      = "INSERT INTO {$table} (reply_id, keyword) VALUES " . implode(', ', $placeholders);
        $prepared = $wpdb->prepare($sql, $values);

        if (false === $wpdb->query($prepared)) {
            throw new Exception(__('Batch insert keywords failed', 'G3'));
        }
    }

    /**
     * Batch update keywords (for existing records)
     * 
     * 批量更新关键词（先删除该 reply_id 的所有关键词，再重新插入）
     * 
     * @param int $replyId
     * @param array $keywords
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    private static function batchUpdateKeywords(int $replyId, array $keywords): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::KEYWORD_TABLE;

        /**
         * 先删除后插入，替代差异同步。
         */

        // Delete all existing keywords for this reply_id
        $wpdb->delete($table, ['reply_id' => $replyId], ['%d']);

        // Re-insert new keywords
        if (!empty($keywords)) {
            self::batchInsertKeywords($replyId, $keywords);
        }
    }

    /**
     * Delete one or multiple reply rules by ID(s).
     *
     * 删除一条或多条自动回复规则及其所有关联关键词
     *
     * @param array $data {
     *     @type int    $id  可选，单个 reply ID
     *     @type int[]  $ids 可选，多个 reply IDs
     * }
     * @return true|WP_Error
     */
    public static function deleteReply(array $data): bool|WP_Error
    {
        global $wpdb;

        // 解析 ID 列表
        $ids = [];
        if (isset($data['id']) && is_numeric($data['id'])) {
            $ids[] = (int) $data['id'];
        }
        if (isset($data['ids']) && is_array($data['ids'])) {
            foreach ($data['ids'] as $id) {
                if (is_numeric($id) && ($id = (int) $id) > 0) {
                    $ids[] = $id;
                }
            }
        }

        $ids = array_unique(array_filter($ids, fn($id) => $id > 0));

        if (empty($ids)) {
            return new WP_Error('invalid_ids', __('No valid reply ID(s) provided.', 'G3'));
        }

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            $replyTable   = $wpdb->prefix . self::REPLY_TABLE;
            $keywordTable = $wpdb->prefix . self::KEYWORD_TABLE;

            // 检查所有 ID 是否都存在（可选，提升用户体验）
            $placeholders  = implode(',', array_fill(0, count($ids), '%d'));
            $existingCount = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$replyTable} WHERE id IN ({$placeholders})",
                $ids
            ));

            if ((int) $existingCount !== count($ids)) {
                // 可选择是否严格要求全部存在；这里允许部分不存在？但通常应全存在
                // 为安全起见，我们要求全部存在，否则报错
                throw new Exception('One or more replies not found.');
            }

            // Step 1: Delete all associated keywords for these reply_ids
            $deletedKeywords = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$keywordTable} WHERE reply_id IN ({$placeholders})",
                $ids
            ));

            // Step 2: Delete the main reply records
            $deletedReplies = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$replyTable} WHERE id IN ({$placeholders})",
                $ids
            ));

            if ($deletedReplies === false) {
                throw new Exception(__('Failed to delete replies from main table.', 'G3'));
            }

            $wpdb->query('COMMIT');
            return true;

        }
        catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('G3 WeChat OA - deleteReply failed: ' . $e->getMessage());
            return new WP_Error('db_error', $e->getMessage() ?: __('Failed to delete reply(s).', 'G3'));
        }
    }

    /**
     * Batch enable auto reply rules
     * 
     * 批量启用自动回复规则
     *
     * @param array $data {
     *     @type int[] $ids 要启用的 reply ID 列表
     * }
     * @return true|WP_Error
     */
    public static function enableReply(array $data): bool|WP_Error
    {
        return self::batchUpdateStatus($data['ids'] ?? [], 1);
    }

    /**
     * Batch disable auto reply rules
     * 
     * 批量禁用自动回复规则
     *
     * @param array $data {
     *     @type int[] $ids 要禁用的 reply ID 列表
     * }
     * @return true|WP_Error
     */
    public static function disableReply(array $data): bool|WP_Error
    {
        return self::batchUpdateStatus($data['ids'] ?? [], 0);
    }

    /**
     * Batch update reply status in the main table
     * 
     * 批量更新回复规则状态
     *
     * @param array $ids
     * @param int   $status (0 或 1)
     * @return true|WP_Error
     */
    private static function batchUpdateStatus(array $ids, int $status): bool|WP_Error
    {
        if (empty($ids)) {
            return new WP_Error('no_ids', __('No reply IDs provided', 'G3'));
        }

        // Filter and convert to integer, remove duplicates
        $ids = array_filter(array_map('intval', $ids), fn($id) => $id > 0);
        $ids = array_unique($ids);

        if (empty($ids)) {
            return new WP_Error('invalid_ids', __('All provided IDs are invalid', 'G3'));
        }

        global $wpdb;
        $table        = $wpdb->prefix . self::REPLY_TABLE;
        $keywordTable = $wpdb->prefix . self::KEYWORD_TABLE;

        // 构造 IN 占位符
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        // Get keywords for these reply IDs
        $keywords = $wpdb->get_col($wpdb->prepare(
            "SELECT keyword FROM {$keywordTable} WHERE reply_id IN ({$placeholders})",
            $ids
        ));

        // Update status in the main table
        $sql    = "UPDATE {$table} SET status = %d, updated = %s WHERE id IN ({$placeholders})";
        $result = $wpdb->query($wpdb->prepare(
            $sql,
            array_merge([$status, current_time('mysql', true)], $ids)
        ));

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update reply status', 'G3'));
        }

        // Clear cache for these keywords
        foreach ($keywords as $keyword) {
            $cacheKey = 'reply:' . md5($keyword);
            wp_cache_delete($cacheKey, self::CACHE_GROUP);
        }

        return true;
    }



    /************************************************************
     * 
     * Wechat OA User Handle
     * 
     ************************************************************/

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
                    'openid' => $openid,
                    'lang'   => 'zh_CN'
                ]);

                $result = $response->toArray();

                if (isset($result['errcode']) && $result['errcode'] != 0) {
                    return null;
                }

                $user_info = $result;
                // Cache for 1 hour
                wp_cache_set($cache_key, $user_info, self::CACHE_GROUP, 3600);
            }

            return $user_info ?: null;
        }
        catch (\Exception $e) {
            error_log('Error getting user info: ' . $e->getMessage());
            return null;
        }
    }


    /************************************************************
     * 
     * Wechat OA Menus Handle
     * 
     ************************************************************/

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
        } else {
            $result = $this->app->getClient()->postJson('cgi-bin/menu/delconditional', [
                'menuid' => $menuId
            ]);
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
                $node['type'] = self::getMenuType($item['type']);
                $node['name'] = $item['name'];

                // Set corresponding fields according to type
                match ($node['type']) {
                    'view' => $node['url'] = $item['value'],
                    'click' => $node['key'] = $item['value'],
                    'scancode_push' => $node['key'] = $item['value'],
                    'scancode_waitmsg' => $node['key'] = $item['value'],
                    'pic_sysphoto' => $node['key'] = $item['value'],
                    'pic_photo_or_album' => $node['key'] = $item['value'],
                    'pic_weixin' => $node['key'] = $item['value'],
                    'location_select' => $node['key'] = $item['value'],
                    'media_id' => $node['media_id'] = $item['value'],
                    'view_limited' => $node['media_id'] = $item['value'],
                    'article_id' => $node['article_id'] = $item['value'],
                    'article_view_limited' => $node['article_id'] = $item['value'],
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
            wp_cache_delete(self::MENU_CACHE_KEY . ':' . $id, self::CACHE_GROUP);
            return $wpdb->insert($table, $data);
        }
        $result = $wpdb->update($table, $data, ['id' => $id]);
        if ($result) {
            wp_cache_delete(self::MENU_CACHE_KEY, self::CACHE_GROUP);
            wp_cache_delete(self::MENU_CACHE_KEY . ':' . $id, self::CACHE_GROUP);
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
            wp_cache_delete(self::MENU_CACHE_KEY . ':' . $id, self::CACHE_GROUP);
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
    public static function renderMenuType(string $type): string
    {
        return match ($type) {
            '1' => __('View URL', 'G3'),                // view
            '2' => __('Click Event', 'G3'),             // click
            '3' => __('Scan Code', 'G3'),               // scancode_push
            '4' => __('Scan & Alert', 'G3'),            // scancode_waitmsg
            '5' => __('System Camera', 'G3'),           // pic_sysphoto
            '6' => __('Photo or Album', 'G3'),          // pic_photo_or_album
            '7' => __('WeChat Album', 'G3'),            // pic_weixin
            '8' => __('Location Selector', 'G3'),       // location_select
            '9' => __('Mini Program', 'G3'),            // view_miniprogram
            '10' => __('Media Library', 'G3'),          // media_id
            '11' => __('Article URL', 'G3'),            // view_limited
            '12' => __('Article ID', 'G3'),             // article_id
            '13' => __('Limited Article URL', 'G3'),    // article_view_limited
            default => '-'
        };
    }

    public static function getMenuType(string $type): string
    {
        return match ($type) {
            '1' => 'view',
            '2' => 'click',
            '3' => 'scancode_push',
            '4' => 'scancode_waitmsg',
            '5' => 'pic_sysphoto',
            '6' => 'pic_photo_or_album',
            '7' => 'pic_weixin',
            '8' => 'location_select',
            '9' => 'view_miniprogram',
            '10' => 'media_id',
            '11' => 'view_limited',
            '12' => 'article_id',
            '13' => 'article_view_limited',
            default => '-'
        };
    }

    /**
     * Check keyword length
     * 
     * 检查关键词长度
     * 
     * @param string $keyword Keyword
     * @return bool|string True if valid, or error message
     * @since 1.0.0
     * @author Wang Shai
     */
    private function checkKeywordLength(string $keyword): bool|string
    {
        $valid = $this->option['length'] ?? false;
        if ($valid && mb_strlen($keyword, 'UTF-8') > $valid) {
            return sprintf(
                __('Keyword too long, max %s characters.', 'G3'),
                $valid
            );
        }
        return true;
    }

    /**
     * Get latest posts as news message
     * 
     * 获取最新文章列表作为图文消息
     * 
     * @return array Formatted news message for WeChat
     * @since 1.0.0
     * @author Wang Shai
     */
    private function getLatestPosts(): array
    {
        $posts = get_posts([
            'numberposts' => $this->option['count'] ?? 5,
            'post_status' => 'publish',
            'post_type'   => 'post',
            'orderby'     => 'date',
            'order'       => 'DESC'
        ]);

        $articles     = [];
        $defaultCover = get_option(SystemService::OPTION_KEY)['cover'] ?? '';

        foreach ($posts as $post) {
            $thumbnail = get_the_post_thumbnail_url($post->ID, 'medium');
            $thumbnail = !$thumbnail ? $defaultCover : $thumbnail;

            $excerpt = $post->post_excerpt;
            if (empty($excerpt)) {
                $excerpt = Common::truncate($post->post_content, 20);
            }

            $articles[] = [
                'Title'       => $post->post_title,
                'Description' => $excerpt,
                'PicUrl'      => $thumbnail,
                'Url'         => get_permalink($post->ID)
            ];
        }

        return [
            'MsgType'      => 'news',
            'ArticleCount' => count($articles),
            'Articles'     => $articles
        ];
    }


    /**
     * 获取用于“关注登录”的临时二维码
     *
     * @return 
     */
    public function getFollowLoginQrCode()
    {
        if (!$this->isAvailable()) {
            return new WP_Error(
                'wechat_unavailable',
                __('WeChat service is not available.', 'G3')
            );
        }

        // 先尝试从缓存读取（避免重复创建）
        $cacheKey = 'g3_wechat_follow_login_qrcode';
        $cached   = get_transient($cacheKey);

        if ($cached && isset($cached['ticket'], $cached['url'])) {
            // 可选：验证 ticket 是否仍有效（通常永久有效）
            return $cached;
        }

        try {
            // 最大支持 2592000 秒（30 天）
            $expireSeconds = 2592000;
            // 固定场景值
            $sceneStr = 'g3_login_subscribe';
            // $sceneId  = crc32('g3_login_subscribe') & 0x7FFFFFFF;
            $sceneId = 315696136;

            $response = $this->app->getClient()->post(
                'https://api.weixin.qq.com/cgi-bin/qrcode/create',
                [
                    'expire_seconds' => $expireSeconds,
                    'action_name'    => 'QR_SCENE', // QR_STR_SCENE 临时字符串类型
                    'action_info'    => [
                        'scene' => [
                            // 'scene_str' => $sceneStr
                            'scene_id' => $sceneId
                        ]
                    ]
                ]
            );

            $data = $response->toArray();
            // 在 error_log 打印数组 $data
            error_log(var_export($data, true));

            if (!empty($data['errcode'])) {
                throw new Exception($data['errmsg'] ?? 'Unknown error');
            }

            $result = [
                'ticket'     => $data['ticket'],
                'url'        => 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . urlencode($data['ticket']),
                'expires_at' => time() + $expireSeconds,
            ];

            set_transient($cacheKey, $result, $expireSeconds);

            return [
                'ticket' => $result['ticket'],
                'url'    => $result['url'],
            ];
        }
        catch (Exception $e) {
            error_log('Failed to create login QR code: ' . $e->getMessage());
            return new WP_Error('qrcode_permanent_error', $e->getMessage());
        }
    }
}