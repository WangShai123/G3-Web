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
                'minLength' => 1,
                'maxLength' => 64
            ],
            'password' => [
                'type'      => 'string',
                'minLength' => 5,
                'maxLength' => 64
            ]
        ]
    ])]
    #[Middleware(RateLimitMiddleware::class, [6, 60])]
    public function adminLogin(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data     = $request->get_json_params();
        $username = sanitize_text_field($data['username']);
        $password = sanitize_text_field($data['password']);

        $user = get_user_by('login', $username);
        if (!$user || !wp_check_password($password, $user->data->user_pass, $user->ID)) {
            return new WP_Error(
                401,
                __('Invalid username or password.', 'G3'),
                [
                    'status' => 401
                ]
            );
        }

        AuthService::performWpLogin($user);

        return rest_ensure_response([
            'code'    => 200,
            'message' => __('Success')
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
        route: 'auth/wechat/login/subscribe/qrcode',
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
        $params = $request->get_json_params();
        $hash   = sanitize_text_field($params['hash'] ?? '');

        // Validate the hash (UUID v4)
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $hash)) {
            return new WP_Error(400, 'Invalid hash format');
        }
        // 30分钟过期 空 transient
        set_transient("g3_SubscribeLoginHash_{$hash}", '', 1800);

        $service = AuthService::run();
        $result  = $service->getSubscribeLoginQrCode($hash, 1800);

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
        route: 'auth/wechat/login/subscribe/validate',
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
    #[Middleware(RateLimitMiddleware::class, [30, 60])]
    public function validateSubscribeLogin(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $params = $request->get_json_params();
        $hash   = sanitize_text_field($params['hash'] ?? '');

        // Validate the hash (UUID v4)
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $hash)) {
            return new WP_Error(400, 'Invalid hash');
        }

        $userId = get_transient("g3_SubscribeLoginHash_{$hash}");

        // Hash expired
        if ($userId === false) {
            return rest_ensure_response([
                'success' => false,
                'message' => __('Quest expired', 'G3')
            ]);
        }
        // Hash waiting for validation
        if ($userId === '') {
            return rest_ensure_response([
                'success' => false,
                'message' => __('Quest pending', 'G3')
            ]);
        }

        $user = new WP_User((int) $userId);
        if (!$user->exists()) {
            return new WP_Error(400, 'User not found');
        }

        AuthService::performWpLogin($user);
        return rest_ensure_response([
            'success' => true,
            'message' => __('Login Success', 'G3')
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
        if (!$service->wechatOAService->isAvailable()) {
            return new WP_Error(
                503,
                __('WeChat service is not configured.', 'G3'),
                ['status' => 503]
            );
        }

        $redirectUri = home_url(AuthService::WECHAT_CALLBACK);
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



}