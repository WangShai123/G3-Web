<?php
namespace JEALER\G3\Controllers;

use JEALER\G3\Attributes\RestRouter;
use JEALER\G3\Attributes\Middleware;
use JEALER\G3\Attributes\Schema;
use JEALER\G3\Middleware\RestAuthMiddleware;
use JEALER\G3\Middleware\RoleMiddleware;
use JEALER\G3\Middleware\RateLimitMiddleware;
use WP_REST_Request;
use WP_Error;

class LoginController {
    #[RestRouter(
        namespace: 'api/v1',
        route: 'login/admin',
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
    #[Middleware(RateLimitMiddleware::class, [10, 60])]
    public function adminLogin(WP_REST_Request $request)
    {
        $data     = $request->get_json_params();
        $username = sanitize_text_field($data['username']);
        $password = sanitize_text_field($data['password']);

        $user = get_user_by('login', $username);
        if (!$user) {
            return new WP_Error(
                401,
                __('Invalid username or password.', 'G3'),
                [
                    'status' => 401
                ]
            );
        }

        if (!wp_check_password($password, $user->data->user_pass, $user->ID)) {
            return new WP_Error(
                401,
                __('Invalid username or password.', 'G3'),
                [
                    'status' => 401
                ]
            );
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
        do_action('wp_login', $user->user_login, $user);

        return rest_ensure_response([
            'code'    => 200,
            'message' => __('Success')
        ]);
    }
}