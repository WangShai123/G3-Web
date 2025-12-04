<?php
namespace JEALER\G3\Middleware;
use JEALER\G3\Middleware\RestAuthMiddleware;
use WP_REST_Request;
use WP_Error;

class RoleMiddleware implements MiddlewareInterface {
    private string $requiredRole;
    private string|array $allowedRoles;

    public function __construct(string|array $allowedRoles = 'administrator')
    {
        $this->allowedRoles = $allowedRoles;
    }

    /**
     * Check if user has any of the allowed roles.
     * 
     * 检查用户是否具有指定角色（适用于REST API）
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     * 
     * @since 1.0.0
     * @author Wang Shai
     */
    public function handle(WP_REST_Request $request)
    {
        // Check if user is already logged in
        $authMiddleware = new RestAuthMiddleware();
        $authResult     = $authMiddleware->handle($request);

        if ($authResult !== true) {
            return $authResult;
        }

        // Get current user
        $user = wp_get_current_user();

        // standardize allowed roles to array
        $allowed = \is_array($this->allowedRoles)
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
}