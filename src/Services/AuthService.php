<?php
namespace JEALER\G3\Services;
use EasyWeChat\OfficialAccount\Application;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Services\WechatOAService;
use JEALER\G3\Utilities\Option;
use WP_User_Query;
use WP_User;
use WP_Error;
use Exception;
use wpdb;

class AuthService {
    const string INVITE_CODE_TABLE = 'g3_invite_codes';
    // General Auth Option Key
    const OPTION_KEY = 'g3_option_auth';
    // WeChat Auth Option Key
    const WECHAT_OPTION_KEY     = 'g3_option_auth_wechat';
    const OPENID_META_KEY       = 'g3_wechat_openId';
    const WX_OPEN_ID_KEY        = 'wx_openId';
    const WX_UNION_ID_KEY       = 'wx_unionId';
    const SUBSCRIBE_HASH_PREFIX = 'g3_SubscribeLoginHash_';
    const WECHAT_CALLBACK       = '/api/v1/auth/wechat/callback';

    // Wechat OA Application
    public Application $wechatOA;
    // Wechat OA Service
    public WechatOAService $wechatOAService;
    private wpdb           $wpdb;
    private string         $fullInviteCodesTable;

    public function __construct()
    {
        $this->wechatOAService = WechatOAService::run();
        global $wpdb;
        $this->wpdb                 = $wpdb;
        $this->fullInviteCodesTable = $wpdb->prefix . self::INVITE_CODE_TABLE;
    }

    /************************************************************
     * 
     * check authorization in REST API
     * 
     ************************************************************/
    /**
     * Check WordPress login cookie.
     * 
     * 检查WordPress登录cookie
     * 
     * @return void
     */
    public static function checkWordPressCookie(): void
    {
        // 确保必要的常量已定义
        if (!defined('COOKIEHASH')) {
            wp_cookie_constants();
        }

        // 检查标准的WordPress登录cookie
        $cookie_name = 'wordpress_logged_in_' . COOKIEHASH;

        if (isset($_COOKIE[$cookie_name])) {
            $cookie = $_COOKIE[$cookie_name];

            // 验证cookie
            $userId = wp_validate_auth_cookie($cookie, 'logged_in');

            if ($userId) {
                // 设置当前用户
                wp_set_current_user($userId);
            }
        }
    }
    /**
     * Try to authenticate user using multiple methods.
     * 
     * 尝试通过多种方式认证用户
     * 
     * @return void
     */
    public function attemptAuthentication(): void
    {
        // 1. Check WordPress login cookie
        self::checkWordPressCookie();

        // 2. If first step fails, try alternative authentication methods
        if (!is_user_logged_in()) {
            $this->checkAlternativeAuth();
        }
    }
    /**
     * Check alternative authentication methods.
     * 
     * 检查其他认证方式（如nonce、HTTP Basic Auth等）
     * 
     * @return void
     */
    private function checkAlternativeAuth(): void
    {
        // 检查是否存在认证相关的请求参数（如nonce）
        if (isset($_REQUEST['_wpnonce'])) {
            // 验证nonce
            $nonce = sanitize_text_field($_REQUEST['_wpnonce']);
            if (wp_verify_nonce($nonce, 'wp_rest')) {
                // 如果有有效的nonce，但用户仍未登录，可能需要检查其他参数来确定用户身份
                // @deprecated
            }
        }

        // 检查HTTP_AUTHORIZATION头部（如果存在）
        $redirect    = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $redirect;

        if ($auth_header && strpos($auth_header, 'Basic ') === 0) {
            // 处理基本认证
            $this->handleBasicAuth($auth_header);
        }
    }
    /**
     * Handle Basic Authentication.
     * 
     * 处理基本认证
     * 
     * @param string $auth_header HTTP_AUTHORIZATION头部内容
     * @return void
     */
    private function handleBasicAuth(string $auth_header): void
    {
        $auth = base64_decode(substr($auth_header, 6));
        if (!$auth) {
            return;
        }

        $credentials = explode(':', $auth, 2);
        if (\count($credentials) !== 2) {
            return;
        }

        $username = $credentials[0];
        $password = $credentials[1];

        // Check username and password against WordPress database
        $user = wp_authenticate($username, $password);

        if (!is_wp_error($user)) {
            // set current user
            wp_set_current_user($user->ID);
        }
    }

    /************************************************************
     * 
     * Wechat Auth Handle
     * 
     ************************************************************/

    /**
     * Whether Wechat OA Subscribe Login is available
     * 
     * 微信公众号订阅登录功能是否可用
     * 
     * @return bool
     */
    public static function subscribeLoginAvailable(): bool
    {
        $loader = Container::run()->get('loader')->y();
        $data   = get_option(AuthService::WECHAT_OPTION_KEY)['subscribe'] ?? false;
        return ($data === '1' && $loader) ? true : false;
    }

    /**
     * Get temporary WeChat OA Subscribe Login QRCode
     * 
     * 获取微信公众号关注登录用的临时二维码
     * 
     * @param string $hash
     * @param int $seconds
     * @return array|WP_Error
     */
    public function getSubscribeLoginQrCode(string $hash, int $seconds): array|WP_Error
    {
        return $this->wechatOAService->getSubscribeLoginQrCode($hash, $seconds);
    }

    /**
     * Handle Post Subscribe Login
     * 
     * 标记用户已绑定，供前端轮训
     * 
     * @return void
     */
    public function handlePostSubscribeLogin(string $openid, string $hash): void
    {
        // Find or Create WordPress User
        $user = self::findUserByOpenId($openid);
        if (!$user) {
            $userId = self::createWpUserByOpenId($openid);
            if (!is_wp_error($userId)) {
                $user = new WP_User($userId);
            }
        }
        if (!$user instanceof WP_User) {
            return;
        }

        $cacheKey = self::SUBSCRIBE_HASH_PREFIX . $hash;
        set_transient($cacheKey, $user->ID, 1800);
    }


    /**
     * Get OAuth2 Authorize URL
     * 
     * 获取微信 OAuth2 授权 URL
     *
     * @param string $redirectUri 回调地址
     * @param string $state 状态参数，用于区分登录或绑定场景
     * @return string 授权 URL
     */
    public function getOAuthUrl(string $redirectUri, string $state = ''): string
    {
        if (!$this->wechatOAService->available()) {
            return '';
        }

        $oauth = $this->wechatOAService->app->getOAuth();
        if ($state !== '') {
            return $oauth->withState($state)->redirect($redirectUri);
        }
        return $oauth->redirect($redirectUri);
    }

    /**
     * Get user openId by code
     * 
     * 通过授权码 (code) 换取用户 OpenID (使用 snsapi_base 静默授权)
     *
     * @param string $code 微信返回的授权码
     * @return string|WP_Error OpenID 或错误对象
     */
    public function getOpenIdByCode(string $code)
    {
        if (!$this->wechatOAService->available()) {
            return new WP_Error(
                'wechat_unavailable',
                __('WeChat service is not available.', 'G3')
            );
        }

        try {
            $oauth = $this->wechatOAService->app->getOAuth();
            // 由于我们使用的是 snsapi_base，user 对象只包含 openid
            $user = $oauth->userFromCode($code); // 更明确的方法
            return $user->getId(); // 返回 OpenID
        }
        catch (Exception $e) {
            error_log('WeChat OA OAuth Error: ' . $e->getMessage());
            return new WP_Error('wechat_oauth_error', $e->getMessage());
        }
    }

    /**
     * Find WordPress User By OpenID
     * 
     * 根据 OpenID 查找 WordPress 用户
     *
     * @param string $openid 微信 OpenID
     * @return WP_User|false 找到的用户对象，否则 false
     */
    public static function findUserByOpenId(string $openid)
    {
        $userQuery = new WP_User_Query([
            'meta_key'   => self::OPENID_META_KEY,
            'meta_value' => $openid,
            'number'     => 1
        ]);

        $users = $userQuery->get_results();
        return !empty($users) ? $users[0] : false;
    }

    /**
     * Generate a new WordPress user by the given OpenID
     * 
     * 根据 OpenID 创建一个新的 WordPress 用户
     *
     * @param string $openid 微信 OpenID
     * @return int|WP_Error 新用户的 ID 或错误对象
     */
    public static function createWpUserByOpenId(string $openid): int|WP_Error
    {
        // Generate a unique username (use the hash of the openid to ensure uniqueness)
        $username = 'wx_' . substr(md5($openid), 8, 12);
        // Generate a virtual but unique email
        $email = $username . '@' . wp_parse_url(home_url(), PHP_URL_HOST);

        $userData = [
            'user_login' => $username,
            'user_pass'  => wp_generate_password(24, true, true),
            'user_email' => $email,
            'role'       => 'subscriber',
            'meta_input' => [
                self::OPENID_META_KEY => $openid
            ]
        ];

        $result = wp_insert_user($userData);
        return $result;
    }

    /**
     * Bind OpenID to WordPress user
     * 
     * 将指定的 WordPress 用户与 OpenID 绑定
     *
     * @param int $userId WordPress 用户 ID
     * @param string $openid 微信 OpenID
     * @return bool|WP_Error 成功返回 true，失败返回错误
     */
    public static function bindOpenIdToUser(int $userId, string $openid)
    {
        // Check if this OpenID is already bound to another user
        $existingUser = self::findUserByOpenId($openid);
        if ($existingUser && (int) $existingUser->ID !== $userId) {
            return new WP_Error(
                'openid_already_bound',
                __('This WeChat account is already bound to another user.', 'G3')
            );
        }

        $result = update_user_meta($userId, self::OPENID_META_KEY, $openid);
        return $result !== false;
    }

    /**
     * Perform WordPress user login
     * 
     * 执行 WordPress 用户登录
     *
     * @param WP_User $user
     * @return void
     */
    public function doWPLogin(WP_User $user): void
    {
        wp_set_current_user($user->ID, $user->user_login);
        wp_set_auth_cookie($user->ID);
        do_action('wp_login', $user->user_login, $user);

        setcookie('g3-user', $user->ID, time() + 86400 * 365, '/');
    }

    /**
     * Generate invite code
     * 
     * 生成邀请码
     *
     * @param bool $source 是否为系统生成源
     * @return string|false
     */
    public function generateInviteCode(bool $source = false): string|false
    {
        // Define character set: 0-9, a-G, A-G (Hexadecimal characters, case-sensitive)
        $characters = '0123456789abcdefgABCDEFG';
        $length     = 16;
        // Prevent infinite loop in case of extreme collision or DB issues
        $maxRetries = 10;
        $code       = '';
        $isUnique   = false;

        for ($i = 0; $i < $maxRetries; $i++) {
            // Generate random code
            $code      = '';
            $charCount = strlen($characters);
            for ($j = 0; $j < $length; $j++) {
                // Use random_int for cryptographically secure pseudo-random integer
                $index  = random_int(0, $charCount - 1);
                $code  .= $characters[$index];
            }

            // Check for uniqueness
            $existing = $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$this->fullInviteCodesTable} WHERE code = %s", $code));

            if ($existing == 0) {
                $isUnique = true;
                break;
            }
        }
        if (!$isUnique) {
            // Could not generate a unique code after max retries
            return false;
        }

        $expireDays    = (int) (get_option(self::OPTION_KEY)['expire'] ?? '7');
        $expireSeconds = $expireDays * 86400;

        $now = gmdate('Y-m-d H:i:s');

        $data   = [
            'code'       => $code,
            'source'     => $source ? 1 : 2,
            'start_time' => $now,
            'end_time'   => gmdate('Y-m-d H:i:s', time() + $expireSeconds),
            'status'     => 0,
            'creator_id' => get_current_user_id(),
            'created_at' => $now
        ];
        $result = $this->wpdb->insert($this->fullInviteCodesTable, $data);
        if ($result !== false) {
            return $code;
        } else {
            error_log('[G3 AuthService] Failed to insert invitation code into database.');
            return false;
        }
    }

    public function getInviteCode(string $code)
    {

    }

    /**
     * Delete an invitation code from the database.
     *
     * @param int|string $id The ID of the invitation code or the code itself.
     * @return int|false The number of rows deleted, or false on failure.
     */
    public function deleteInviteCode(int|string $id): int|false
    {
        if (is_int($id)) {
            // Delete by primary key ID
            return $this->wpdb->delete(
                $this->fullInviteCodesTable,
                ['id' => $id],
                ['%d']
            );
        } elseif (is_string($id)) {
            // Delete by invitation code
            return $this->wpdb->delete(
                $this->fullInviteCodesTable,
                ['code' => $id],
                ['%s']
            );
        }

        return false;
    }

    public static function renderCodeSource(int $id): string
    {
        return match ($id) {
            1       => __('System Generated', 'G3'),
            2       => __('User Bought', 'G3'),
            default => __('Unknown'),
        };
    }


    /************************************************************
     * 
     * Auth UI
     * 
     ************************************************************/
    /**
     * Login Element
     * @param string $element Login HTML element
     * @param bool $modal Whether to use modal for login
     * @return string Login HTML element
     */
    public static function loginElement(string $element, bool $modal = false): string
    {
        if (!$modal) {
            $element = '<a href="' . get_site_url() . '/user/login" title="' . __('Login', 'G3') . '">' . $element . '</a>';
        } else {
            load_template(G3_TEMPLATE_DIR . '/user/login-modal.php', true);
        }
        return $element;
    }
}
