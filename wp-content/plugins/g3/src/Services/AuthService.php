<?php
namespace JEALER\G3\Services;

use EasyWeChat\OfficialAccount\Application;
use JEALER\G3\Services\WechatOAService;
use WP_User_Query;
use WP_User;
use WP_Error;
use Exception;

class AuthService {

    /**
     * General Auth Option Key
     * 
     * 常规授权配置项键名
     * 
     * @var string
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public const OPTION_KEY = 'g3_option_auth';

    /**
     * Subscribe Auth Option Key
     * 
     * 关注公众号登录的配置项键名
     * 
     * @var string
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public const SUBSCRIBE_OPTION_KEY = 'g3_option_auth_subscribe';

    /**
     * Wechat OpenId User Meta Key
     * 
     * 微信OpenId User Meta键名
     * 
     * @var string
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public const OPENID_META_KEY = 'g3_wechat_openId';

    public const SUBSCRIBE_HASH_PREFIX = 'g3_SubscribeLoginHash_';

    public const WECHAT_CALLBACK = '/wp-json/api/v1/auth/wechat/callback';

    /**
     * Wechat OA Application
     * 
     * 微信公众号应用
     * 
     * @var Application
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public Application $wechatOA;

    /**
     * Wechat OA Service
     * 
     * @var WechatOAService
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public $wechatOAService;

    /**
     * Auth Instance
     * 
     * @var AuthService
     * @access public
     * @since 1.0.0
     * @author Wang Shai
     */
    public static $instance = null;

    public function __construct()
    {
        $this->initService();
    }

    public static function run()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initService()
    {
        $this->wechatOAService = WechatOAService::run();
    }

    /************************************************************
     * 
     * Wechat OA Auth Handle
     * 
     ************************************************************/

    /**
     * Whether Wechat OA Subscribe Login is available
     * 
     * 微信公众号订阅登录功能是否可用
     * 
     * @return bool
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function subscribeLogin(): bool
    {
        $data = get_option(AuthService::SUBSCRIBE_OPTION_KEY)['wechatOA'] ?? false;
        return $data === '1' ? true : false;
    }

    /**
     * Get temporary WeChat OA Follow Login QRCode
     * 
     * 获取微信公众号关注登录用的临时二维码
     * 
     * @param string $hash
     * @param int $seconds
     * @return array|WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    public function getSubscribeLoginQrCode(string $hash, int $seconds): array|WP_Error
    {
        return $this->wechatOAService->getSubscribeLoginQrCode($hash, $seconds);
    }

    public function handlePostSubscribeLogin(string $openid, string $hash)
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

        set_transient("g3_SubscribeLoginHash_{$hash}", $user->ID, 1800);
        // 方案：仅标记用户已绑定，前端轮询 /api/v1/auth/me
        // （只要 openid 绑定成功，/me 就会返回用户信息）
    }


    /**
     * Get OAuth2 Authorize URL
     * 
     * 获取微信 OAuth2 授权 URL
     *
     * @param string $redirectUri 回调地址
     * @param string $state 状态参数，用于区分登录或绑定场景
     * @return string 授权 URL
     * @since 1.0.0
     * @author Wang Shai
     */
    public function getOAuthUrl(string $redirectUri, string $state = ''): string
    {
        if (!$this->wechatOAService->isAvailable()) {
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
     * @since 1.0.0
     * @author Wang Shai
     */
    public function getOpenIdByCode(string $code)
    {
        if (!$this->wechatOAService->isAvailable()) {
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
     * @return \WP_User|false 找到的用户对象，否则 false
     * @since 1.0.0
     * @author Wang Shai
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
     * @since 1.0.0
     * @author Wang Shai
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
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function bindOpenIdToUser(int $userId, string $openid)
    {
        // Check if this OpenID is already bound to another user
        $existingUser = self::findUserByOpenId($openid);
        if ($existingUser && (int) $existingUser->ID !== $userId) {
            return new WP_Error('openid_already_bound', __('This WeChat account is already bound to another user.', 'G3'));
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
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function performWpLogin(WP_User $user): void
    {
        wp_set_current_user($user->ID, $user->user_login);
        wp_set_auth_cookie($user->ID);
        do_action('wp_login', $user->user_login, $user);
    }
}