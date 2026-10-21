<?php
namespace JEALER\G3\Middleware;
use JEALER\G3\Services\AuthService;
use JEALER\G3\Utilities\Message;
use WP_REST_Request;
use WP_Error;

/**
 * Authentication Middleware for Native Auth
 * 
 * 针对WordPress默认的权限检查中间件
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class NativeAuthMiddleware implements MiddlewareInterface {
    public function __construct(private AuthService $service) {}

    public function handle(WP_REST_Request $request): bool|WP_Error
    {
        if (is_user_logged_in()) {
            return true;
        }

        // try to authenticate user
        $this->service->attemptAuthentication();

        // Check if user is now logged in
        if (is_user_logged_in()) {
            return true;
        }

        return new WP_Error(
            401,
            Message::unauthorized(),
            ['status' => 401]
        );
    }
}
