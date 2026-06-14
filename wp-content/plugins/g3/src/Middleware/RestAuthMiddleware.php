<?php
namespace JEALER\G3\Middleware;
use JEALER\G3\Services\AuthService;
use WP_REST_Request;
use WP_Error;

/**
 * Authentication Middleware for REST API
 * 
 * 针对REST API的权限检查中间件
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class RestAuthMiddleware implements MiddlewareInterface {

    public function handle(WP_REST_Request $request): bool|WP_Error
    {
        if (is_user_logged_in()) {
            return true;
        }

        // try to authenticate user
        $this->attemptAuthentication();

        // Check if user is now logged in
        if (is_user_logged_in()) {
            return true;
        }

        return new WP_Error(
            401,
            __('Unauthorized', 'G3'),
            ['status' => 401]
        );
    }

    /**
     * Try to authenticate user using multiple methods.
     * 
     * 尝试通过多种方式认证用户
     * 
     * @return void
     */
    private function attemptAuthentication(): void
    {
        // 1. Check WordPress login cookie
        AuthService::checkWordPressCookie();

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
}
