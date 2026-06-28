<?php
namespace JEALER\G3\Controllers;
use JEALER\G3\Core\Router\Controller;
use JEALER\G3\Service;
use JEALER\G3\Core\Attributes\RestRouter;
use JEALER\G3\Core\Attributes\Middleware;
use JEALER\G3\Core\Attributes\Schema;
use JEALER\G3\Middleware\RestAuthMiddleware;
use JEALER\G3\Middleware\RoleMiddleware;
use JEALER\G3\Middleware\RateLimitMiddleware;
use JEALER\G3\Services\AuthService;
use JEALER\G3\Services\WechatOAService;
use JEALER\G3\Utilities\Message;
use JEALER\G3\Utilities\Request;
use JEALER\G3\Utilities\Validator;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_User;

class AuthController extends Controller {
    private AuthService $authService;
    public function __construct()
    {
        parent::__construct();
        if ($this->container->has('authService')) {
            $this->authService = $this->service('authService');
        } else {
            $this->container->setRawDefinition('authService', AuthService::class);
            $this->authService = $this->container->get('authService');
        }
    }

    /**
     * Custom Admin Login API
     * 
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     * @since 1.0.0
     * @author Wang Shai
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: 'oa/admin/auth',
        methods: 'POST'
    )]
    #[Schema([
        'type'       => 'object',
        'required'   => ['username', 'password'],
        'properties' => [
            'username' => [
                'type'      => 'string',
                'minLength' => 5,
                'maxLength' => 64
            ],
            'password' => [
                'type'      => 'string',
                'minLength' => 5,
                'maxLength' => 64
            ]
        ]
    ])]
    #[Middleware(RateLimitMiddleware::class, [5, 300])]
    public function adminLogin(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data     = $request->get_json_params();
        $username = sanitize_text_field($data['username']);
        $password = sanitize_text_field($data['password']);

        // Compatible with email login
        if (is_email($username)) {
            $user = get_user_by('email', $username);
        } else {
            $user = get_user_by('login', $username);
        }

        if (!$user || !wp_check_password($password, $user->data->user_pass, $user->ID)) {
            return new WP_Error(
                401,
                __('Invalid username or password.', 'G3'),
                [
                    'status' => 401
                ]
            );
        }

        // Check if user is admin
        if (!user_can($user, 'manage_options')) {
            return new WP_Error(
                401,
                Message::forbidden(),
                [
                    'status' => 401
                ]
            );
        }

        $this->authService->doWPLogin($user);

        return rest_ensure_response([
            'code'    => 200,
            'message' => Message::loginSuccess()
        ]);
    }

    /**
     * Custom Admin Login API
     * 
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     * @since 1.0.0
     * @author Wang Shai
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: 'auth/login/',
        methods: 'POST'
    )]
    #[Schema([
        'type'       => 'object',
        'required'   => ['username', 'password'],
        'properties' => [
            'username' => [
                'type'      => 'string',
                'minLength' => 5,
                'maxLength' => 64
            ],
            'password' => [
                'type'      => 'string',
                'minLength' => 5,
                'maxLength' => 64
            ]
        ]
    ])]
    #[Middleware(RateLimitMiddleware::class, [10, 300])]
    public function userLogin(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data     = $request->get_json_params();
        $username = sanitize_text_field($data['username']);
        $password = sanitize_text_field($data['password']);

        // Compatible with email login
        if (is_email($username)) {
            $user = get_user_by('email', $username);
        } else {
            $user = get_user_by('login', $username);
        }

        if (!$user || !wp_check_password($password, $user->data->user_pass, $user->ID)) {
            return new WP_Error(
                401,
                __('Invalid username or password.', 'G3'),
                [
                    'status' => 401
                ]
            );
        }

        $this->authService->doWPLogin($user);

        return rest_ensure_response([
            'code'    => 200,
            'message' => Message::loginSuccess()
        ]);
    }

    #[RestRouter(
        namespace: 'api/v1',
        route: 'auth/logout/',
        methods: 'POST'
    )]
    public function loginOut(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        wp_logout();

        setcookie('g3-user', '', time() - 3600, '/');

        return rest_ensure_response([
            'code'    => 200,
            'message' => Message::logoutSuccess()
        ]);
    }

    /**
     * Get temporary Wechat OA Subscribe Login QRCode
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: 'auth/wechat/subscribe/qrcode',
        methods: 'POST'
    )]
    #[Schema([
        'type'       => 'object',
        'required'   => ['hash'],
        'properties' => [
            'hash' => [
                'type'   => 'string',
                'length' => 36,
            ],
        ]
    ])]
    #[Middleware(RateLimitMiddleware::class, [6, 60])]
    public function getSubscribeQrCode(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $valid = AuthService::subscribeLoginAvailable();
        if (!$valid) {
            return new WP_Error(
                'error',
                __('WeChat OA Subscription Login is not available', 'G3'),
                ['status' => 404]
            );
        }

        $params  = $request->get_json_params();
        $hash    = sanitize_text_field($params['hash'] ?? '');
        $toValid = substr($hash, 6);

        // Validate the hash (UUID v4)
        if (!Validator::isUUIDv4($toValid)) {
            return new WP_Error(400, __('Invalid Hash', 'G3'));
        }

        // 30 mins expires, empty transient
        $cacheKey   = AuthService::SUBSCRIBE_HASH_PREFIX . $hash;
        $expiration = 1800;
        set_transient($cacheKey, '', $expiration);

        $result = $this->authService->getSubscribeLoginQrCode($hash, $expiration);

        if (is_wp_error($result)) {
            return new WP_Error(
                500,
                $result->get_error_message(),
                ['status' => 500]
            );
        }

        return rest_ensure_response([
            'code' => 200,
            'data' => [
                'url' => $result['url']
            ]
        ]);
    }

    /**
     * Validate login status in WeChat OA subscribe login mode.
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: 'auth/wechat/subscribe/validate',
        methods: 'POST'
    )]
    #[Schema([
        'type'       => 'object',
        'required'   => ['hash'],
        'properties' => [
            'hash' => [
                'type'   => 'string',
                'length' => 36,
            ],
        ]
    ])]
    #[Middleware(RateLimitMiddleware::class, [60, 60])]
    public function validateSubscribeLogin(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $valid = AuthService::subscribeLoginAvailable();
        if (!$valid) {
            return new WP_Error(
                'error',
                __('WeChat OA Subscription Login is not available', 'G3'),
                ['status' => 404]
            );
        }

        $params  = $request->get_json_params();
        $hash    = sanitize_text_field($params['hash'] ?? '');
        $toValid = substr($hash, 6);

        // Validate the hash (UUID v4)
        if (!Validator::isUUIDv4($toValid)) {
            return new WP_Error(400, __('Invalid Hash', 'G3'));
        }

        $cacheKey = AuthService::SUBSCRIBE_HASH_PREFIX . $hash;
        $userId   = get_transient($cacheKey);

        // Hash expired
        if ($userId === false) {
            return rest_ensure_response([
                'success' => false,
                'status'  => 'expired',
                'message' => __('QRCode expired, Please refresh the page and try again.', 'G3')
            ]);
        }

        // Hash waiting for validation
        if ($userId === '') {
            return rest_ensure_response([
                'success' => false,
                'status'  => 'pending',
                'message' => __('Quest pending', 'G3')
            ]);
        }

        $user = new WP_User((int) $userId);
        if (!$user->exists()) {
            return new WP_Error(400, 'Unauthorized');
        }

        $this->authService->doWPLogin($user);
        delete_transient($cacheKey);

        return rest_ensure_response([
            'success' => true,
            'message' => Message::loginSuccess()
        ]);
    }

    /**
     * Get temporary Wechat OA Subscribe Bind QRCode
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: 'auth/wechat/bind/qrcode',
        methods: 'POST'
    )]
    #[Middleware(RestAuthMiddleware::class)]
    public function getBindQrCode(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $user = wp_get_current_user();
        if (!$user->exists()) {
            return new WP_Error(401, 'Unauthorized');
        }

        // 32位随机字符串
        $bindHash = 'bind:' . bin2hex(random_bytes(16));
        $cacheKey = WechatOAService::SUBSCRIBE_BIND_PREFIX . $bindHash;
        // 30 mins cache
        set_transient($cacheKey, $user->ID, 30 * MINUTE_IN_SECONDS);

        $result = $this->authService->getSubscribeLoginQrCode($bindHash, 1800);

        if (is_wp_error($result)) {
            return new WP_Error(
                500,
                $result->get_error_message()
            );
        }

        return rest_ensure_response([
            'code' => 200,
            'data' => [
                'url' => $result['url']
            ]
        ]);
    }
















    /**
     * Get Wechat Bind Auth URL
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     * @since 1.0.0
     * @author Wang Shai
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: 'auth/wechat/bind/url',
        methods: 'GET'
    )]
    #[Middleware(RateLimitMiddleware::class, [5, 60])]
    #[Middleware(RestAuthMiddleware::class)]
    public function getBindAuthUrl(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        if (!$this->authService->wechatOAService->available()) {
            return new WP_Error(
                503,
                __('WeChat service is not available.', 'G3'),
                ['status' => 503]
            );
        }

        $redirectUri = Request::restApi(AuthService::WECHAT_CALLBACK);
        $authUrl     = $this->authService->getOAuthUrl($redirectUri, 'bind');

        if (empty($authUrl)) {
            return new WP_Error(
                500,
                __('Failed to generate WeChat auth URL.', 'G3'),
                ['status' => 500]
            );
        }

        return rest_ensure_response([
            'code'    => 200,
            'message' => __('Success'),
            'data'    => [
                'url' => $authUrl
            ]
        ]);
    }

    /**
     * WeChat Auth Callback
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     * @since 1.0.0
     * @author Wang Shai
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: 'auth/wechat/callback',
        methods: 'GET'
    )]
    public function wechatAuthCallback(WP_REST_Request $request)
    {
    }
}
