<?php
namespace JEALER\G3\Middleware;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Middleware\RestAuthMiddleware;
use JEALER\G3\Services\AuthService;
use WP_REST_Request;
use WP_Error;

/**
 * Check if user has any of the allowed roles.
 * 
 * 检查用户是否具有指定角色（适用于REST API）
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class RoleMiddleware implements MiddlewareInterface {

    /**
     * Allowed roles
     * 
     * 允许的角色
     *
     * @var string|array
     */
    private string|array $allowedRoles;
    private AuthService  $auth;

    public function __construct(string|array $allowedRoles = 'administrator')
    {
        $this->allowedRoles = $allowedRoles;
        $this->auth         = Container::use(AuthService::class);
    }

    public function handle(WP_REST_Request $request): bool|WP_Error
    {
        // Check if user is already logged in
        // $authMiddleware = new RestAuthMiddleware();
        // $authResult     = $authMiddleware->handle($request);

        $authResult = $this->checkLoginStatus();

        if ($authResult !== true) {
            return $authResult;
        }

        // Get current user
        $user = wp_get_current_user();

        // standardize allowed roles to array
        $allowed = is_array($this->allowedRoles)
            ? $this->allowedRoles
            : [$this->allowedRoles];

        // check if user has any of the allowed roles
        $matched = array_intersect($allowed, $user->roles);

        if (empty($matched)) {
            return new WP_Error(
                403,
                __('Forbidden', 'G3'),
                [
                    'status' => 403,
                ]
            );
        }

        return true;
    }

    private function checkLoginStatus(): bool|WP_Error
    {
        if (is_user_logged_in()) {
            return true;
        }

        $this->auth->attemptAuthentication();

        if (is_user_logged_in()) {
            return true;
        }

        return new WP_Error(
            401,
            __('Unauthorized', 'G3'),
            ['status' => 401]
        );
    }
}
