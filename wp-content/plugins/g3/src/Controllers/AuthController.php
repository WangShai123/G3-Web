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
     * Get Wechat OA Follow Login QRCode URL
     * 
     * 获取微信关注登录的二维码url
     *
     * @param array $request
     * @return array|WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: 'auth/wechat/login/qrcode',
        methods: 'GET'
    )]
    public function getFollowLoginQrCode(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $service  = AuthService::run();
        $qrResult = $service->getFollowLoginQrCode();

        if (is_wp_error($qrResult)) {
            return new WP_Error(
                500,
                $qrResult->get_error_message(),
                ['status' => 500]
            );
        }

        return rest_ensure_response([
            'code' => 200,
            'data' => [
                'qrcode_url' => $qrResult['url'],
                'note'       => 'Scan to follow and auto-login'
            ]
        ]);
    }

    /**
     * Get Follow Login URL for qrcode
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     * @since 1.0.0
     * @author Wang Shai
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: 'auth/wechat/follow/url',
        methods: 'GET'
    )]
    #[Middleware(RateLimitMiddleware::class, [10, 60])]
    public function getFollowAuthUrl(WP_REST_Request $request): WP_Error|WP_REST_Response
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
        $authUrl     = $service->getOAuthUrl($redirectUri, 'login');

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
            'data'    => ['url' => $authUrl]
        ]);
    }

    /**
     * Handle WeChat OAuth2 Callback
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     * @since 1.0.0
     * @author Wang Shai
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: 'auth/wechat/callback',
        methods: 'GET'
    )]
    public function handleWechatCallback(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $code  = $request->get_param('code');
        $state = $request->get_param('state') ?: '';

        if (empty($code)) {
            return new WP_Error(
                400,
                __('Authorization code is missing.', 'G3'),
                ['status' => 400]
            );
        }

        $service = AuthService::run();
        if (!$service->wechatOAService->isAvailable()) {
            return new WP_Error(
                503,
                __('WeChat service is not configured.', 'G3'),
                ['status' => 503]
            );
        }

        // Get OpenID by code
        $openid = $service->getOpenIdByCode($code);
        if (is_wp_error($openid)) {
            return new WP_Error(500, $openid->get_error_message(), ['status' => 500]);
        }

        // By state param, decide login or bind
        if ($state === 'bind') {
            // --- Bind Flow ---
            if (!is_user_logged_in()) {
                // 这里可以选择重定向到登录页，或者返回错误
                /**
                 * @todo custom user login page
                 */
                $redirectUrl = add_query_arg('error', 'session_expired', wp_login_url());
                return rest_ensure_response([
                    'code'         => 200,
                    'redirect_url' => $redirectUrl,
                    'message'      => __('Session expired.')
                ]);
            }

            $currentUserId = get_current_user_id();
            $bindResult    = AuthService::bindOpenIdToUser($currentUserId, $openid);

            if (is_wp_error($bindResult)) {
                $redirectUrl = add_query_arg('error', 'bind_failed', home_url('/profile'));
                return rest_ensure_response([
                    'code'         => 200,
                    'redirect_url' => $redirectUrl,
                    'message'      => $bindResult->get_error_message()
                ]);
            }

            // Bind success, redirect to frontend success page
            $redirectUrl = add_query_arg('wechat_bind', 'success', home_url('/profile'));
            return rest_ensure_response([
                'code'         => 200,
                'redirect_url' => $redirectUrl,
                'message'      => __('WeChat account bound successfully.')
            ]);

        } else {
            // --- Login/Register Flow ---
            $user = AuthService::findUserByOpenId($openid);

            if ($user instanceof WP_User) {
                // Login if the user exists
                AuthService::performWpLogin($user);
            } else {
                // Generate a new WordPress user if the user does not exist
                $newUserId = AuthService::createWpUserByOpenId($openid);
                if (is_wp_error($newUserId)) {
                    return new WP_Error(
                        500,
                        __('Failed to create new user.', 'G3'),
                        ['status' => 500]
                    );
                }
                $user = new WP_User($newUserId);
                AuthService::performWpLogin($user);
            }

            // Login Success, redirect to frontend user center
            $redirectUrl = add_query_arg('login', 'wechat_success', home_url('/user-dashboard'));
            return rest_ensure_response([
                'code'    => 200,
                'message' => __('Success'),
                'data'    => [
                    'redirect' => $redirectUrl
                ]
            ]);
        }
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