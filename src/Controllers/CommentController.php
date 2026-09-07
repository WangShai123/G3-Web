<?php
namespace JEALER\G3\Controllers;
use JEALER\G3\Core\Attributes\Middleware;
use JEALER\G3\Core\Attributes\RestRouter;
use JEALER\G3\Core\Attributes\Schema;
use JEALER\G3\Core\Router\Controller;
use JEALER\G3\Middleware\RateLimitMiddleware;
use JEALER\G3\Middleware\RestAuthMiddleware;
use JEALER\G3\Services\CommentService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class CommentController extends Controller {

    public function __construct(private CommentService $service)
    {
        parent::__construct();
    }

    #[RestRouter(namespace: 'api/comment', route: 'v1/config', methods: 'GET')]
    #[Middleware(RateLimitMiddleware::class, [60, 60])]
    public function config(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $options = $this->service->option();
        $result  = [
            'perPage'   => $options['perPage'] ?? 10,
            'maxLength' => $options['maxLength'] ?? 300,
            'throttle'  => $options['throttle'] ?? 5,
            'cacheTtl'  => CommentService::COOKIE_CONFIG_TTL,
        ];
        return is_wp_error($result) ? $result : $this->success($result);
    }

    #[RestRouter(namespace: 'api/comment', route: 'v1/comments', methods: 'GET')]
    #[Middleware(RateLimitMiddleware::class, [120, 60])]
    public function comments(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        // sleep(10000); // test
        $result = $this->service->list(
            (int) $request->get_param('post_id'),
            max(1, (int) ($request->get_param('page') ?: 1)),
            (string) ($request->get_param('sort') ?: 'latest'),
            max(0, (int) ($request->get_param('user_id') ?: 0))
        );

        sleep(1);

        return is_wp_error($result) ? $result : $this->success($result);
    }

    #[RestRouter(namespace: 'api/comment', route: 'v1/replies', methods: 'GET')]
    #[Middleware(RateLimitMiddleware::class, [120, 60])]
    public function replies(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $result = $this->service->replies(
            (int) $request->get_param('comment_id'),
            max(1, (int) ($request->get_param('page') ?: 1)),
            max(0, (int) ($request->get_param('user_id') ?: 0))
        );

        sleep(1);

        return is_wp_error($result) ? $result : $this->success($result);
    }

    #[RestRouter(namespace: 'api/comment', route: 'v1/comments', methods: 'POST')]
    #[Middleware(RestAuthMiddleware::class)]
    #[Middleware(RateLimitMiddleware::class, [60, 60])]
    #[Schema([
        'type'       => 'object',
        'required'   => ['post_id', 'content'],
        'properties' => [
            'post_id'   => ['type' => 'integer'],
            'parent_id' => ['type' => 'integer'],
            'content'   => ['type' => 'string', 'minLength' => 1],
        ],
    ])]
    public function create(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $result = $this->service->create($request->get_json_params() ?: []);

        return is_wp_error($result) ? $result : $this->success($result);
    }

    #[RestRouter(namespace: 'api/comment', route: 'v1/reaction', methods: 'POST')]
    #[Middleware(RestAuthMiddleware::class)]
    #[Middleware(RateLimitMiddleware::class, [60, 60])]
    #[Schema([
        'type'       => 'object',
        'required'   => ['comment_id', 'reaction'],
        'properties' => [
            'comment_id' => ['type' => 'integer'],
            'reaction'   => ['type' => 'string', 'enum' => ['like', 'dislike', 'none']],
        ],
    ])]
    public function reaction(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data   = $request->get_json_params() ?: [];
        $result = $this->service->react((int) ($data['comment_id'] ?? 0), (string) ($data['reaction'] ?? 'none'));

        return is_wp_error($result) ? $result : $this->success($result);
    }
}
