<?php
namespace JEALER\G3\Controllers;
use JEALER\G3\Core\Attributes\Middleware;
use JEALER\G3\Core\Attributes\RestRouter;
use JEALER\G3\Core\Attributes\Schema;
use JEALER\G3\Core\Router\Controller;
use JEALER\G3\Middleware\RateLimitMiddleware;
use JEALER\G3\Middleware\RestAuthMiddleware;
use JEALER\G3\Services\UserPostActionService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class UserController extends Controller {
    public function __construct(private UserPostActionService $postActionService)
    {
        parent::__construct();
    }

    // #[RestRouter(
    //     namespace: 'api/user',
    //     route: 'v1/locale',
    //     methods: 'POST'
    // )]
    // #[Schema([
    //     'type'       => 'object',
    //     'required'   => ['id'],
    //     'properties' => [
    //         'id' => [
    //             'type'    => 'integer',
    //             'minimum' => 0,
    //             'maximum' => 100
    //         ],
    //     ]
    // ])]
    // #[Middleware(RateLimitMiddleware::class, [15, 60])]
    // public function locale(WP_REST_Request $request): WP_REST_Response|WP_Error
    // {
    //     // check config
    //     $option = get_option(PostService::OPTION_KEY, []);
    //     if (!isset($option['language']) || $option['language'] !== '1') {
    //         return new WP_Error('invalid_param', __('Feature Disabled', 'G3'), ['status' => 400]);
    //     }

    //     // check params
    //     $data = $request->get_json_params();
    //     // 1: zh-CN, 0: en-US
    //     $id = $data['id'] ?? null;
    //     if ($id === null || !in_array($id, [0, 1], true) || $this->container->get('loader')->x()) {
    //         return new WP_Error('invalid_param', 'Invalid Params', ['status' => 400]);
    //     }

    //     $cookieName = UserService::G3_LANG_COOKIE;
    //     $locale     = ($id === 1) ? 'en_US' : 'zh_CN';
    //     // one week cookie
    //     setcookie($cookieName, $locale, time() + WEEK_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);

    //     return rest_ensure_response([
    //         'success' => true,
    //         'code'    => 200,
    //         'message' => __('Switched', 'G3'),
    //     ]);
    // }

    #[RestRouter(namespace: 'api/user', route: 'v1/post-actions', methods: 'POST')]
    #[Middleware(RestAuthMiddleware::class)]
    #[Middleware(RateLimitMiddleware::class, [60, 60])]
    #[Schema([
        'type'       => 'object',
        'required'   => ['action'],
        'properties' => [
            'action'         => ['type' => 'string', 'enum' => ['like', 'dislike', 'favorite', 'share']],
            'paged'          => ['type' => 'integer'],
            'posts_per_page' => ['type' => 'integer'],
        ],
    ])]
    public function postActions(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $params = $request->get_json_params() ?: [];
        $result = $this->postActionService->listPosts(
            (int) get_current_user_id(),
            (string) ($params['action'] ?? ''),
            max(1, (int) ($params['paged'] ?? 1)),
            max(1, (int) ($params['posts_per_page'] ?? 20))
        );

        return is_wp_error($result)
            ? $result
            : $this->ok($result);
    }

    private function ok(mixed $data): WP_REST_Response
    {
        return rest_ensure_response([
            'success' => true,
            'code'    => 200,
            'data'    => $data,
        ]);
    }
}
