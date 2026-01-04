<?php
namespace JEALER\G3\Controllers;

use JEALER\G3\Attributes\RestRouter;
use JEALER\G3\Attributes\Middleware;
use JEALER\G3\Attributes\Schema;
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

class AuthController {

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

        AuthService::doWPLogin($user);

        return rest_ensure_response([
            'code'    => 200,
            'message' => Message::successLogin()
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

        $params = $request->get_json_params();
        $hash   = sanitize_text_field($params['hash'] ?? '');
        $hash   = substr($hash, 6);

        // Validate the hash (UUID v4)
        if (!Validator::isUUIDv4($hash)) {
            return new WP_Error(400, __('Invalid Hash', 'G3'));
        }

        // 30 mins expires, empty transient
        $cacheKey   = AuthService::SUBSCRIBE_HASH_PREFIX . $hash;
        $expiration = 1800;
        set_transient($cacheKey, '', $expiration);

        $service = AuthService::run();
        $result  = $service->getSubscribeLoginQrCode($hash, $expiration);

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
    // #[Middleware(RateLimitMiddleware::class, [60, 60])]
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

        $params = $request->get_json_params();
        $hash   = sanitize_text_field($params['hash'] ?? '');
        $hash   = substr($hash, 6);

        // Validate the hash (UUID v4)
        if (!Validator::isUUIDv4($hash)) {
            return new WP_Error(400, __('Invalid Hash', 'G3'));
        }

        $cacheKey = AuthService::SUBSCRIBE_HASH_PREFIX . $hash;
        $userId   = get_transient($cacheKey);

        error_log('[G3] user id from cache: ' . $userId);

        // Hash expired
        if ($userId === false) {
            error_log('[G3] Hash expired: ' . $hash);
            return rest_ensure_response([
                'success' => false,
                'status'  => 'expired',
                'message' => __('QRCode expired, Please refresh the page and try again.', 'G3')
            ]);
        }

        // Hash waiting for validation
        if ($userId === '') {
            error_log('[G3] Hash pending: ' . $hash);
            return rest_ensure_response([
                'success' => false,
                'status'  => 'pending',
                'message' => __('Quest pending', 'G3')
            ]);
        }

        $user = new WP_User((int) $userId);
        if (!$user->exists()) {
            error_log('[G3] User not found: ' . $userId);
            return new WP_Error(400, 'Unauthorized');
        }

        AuthService::doWPLogin($user);
        delete_transient($cacheKey);

        return rest_ensure_response([
            'success' => true,
            'message' => Message::successLogin()
        ]);
    }

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
        $bindHash = bin2hex(random_bytes(16));
        $cacheKey = WechatOAService::SUBSCRIBE_BIND_PREFIX . $bindHash;
        // 30 mins cache
        set_transient($cacheKey, $user->ID, 30 * MINUTE_IN_SECONDS);

        $service = AuthService::run();
        $result  = $service->getSubscribeLoginQrCode($bindHash, 1800);

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
        $service = AuthService::run();
        if (!$service->wechatOAService->available()) {
            return new WP_Error(
                503,
                __('WeChat service is not available.', 'G3'),
                ['status' => 503]
            );
        }

        $redirectUri = Request::restApi(AuthService::WECHAT_CALLBACK);
        $authUrl     = $service->getOAuthUrl($redirectUri, 'bind');

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