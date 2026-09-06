<?php
namespace JEALER\G3\Controllers;
use JEALER\G3\Core\Attributes\Middleware;
use JEALER\G3\Core\Attributes\RestRouter;
use JEALER\G3\Core\Attributes\Schema;
use JEALER\G3\Core\Router\Controller;
use JEALER\G3\Middleware\RateLimitMiddleware;
use JEALER\G3\Middleware\RestAuthMiddleware;
use JEALER\G3\Services\PostService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class PostController extends Controller {

    public function __construct(private PostService $postService)
    {
        parent::__construct();
    }

    #[Schema([
        'type'       => 'object',
        'properties' => [
            'post_type'              => ['type' => ['string', 'array']],
            'posts_per_page'         => ['type' => 'integer'],
            'paged'                  => ['type' => 'integer'],
            'offset'                 => ['type' => 'integer'],
            'orderby'                => ['type' => 'string'],
            'order'                  => ['type' => 'string'],
            's'                      => ['type' => 'string'],
            'post_status'            => ['type' => ['string', 'array']],
            'post_parent'            => ['type' => 'integer'],
            'name'                   => ['type' => 'string'],
            'p'                      => ['type' => 'integer'],
            'page_id'                => ['type' => 'integer'],
            'category_name'          => ['type' => 'string'],
            'cat'                    => ['type' => ['integer', 'string']],
            'category__in'           => ['type' => 'array'],
            'category__not_in'       => ['type' => 'array'],
            'tag'                    => ['type' => 'string'],
            'tag_id'                 => ['type' => 'integer'],
            'tag__in'                => ['type' => 'array'],
            'tag__not_in'            => ['type' => 'array'],
            'tax_query'              => ['type' => 'array'],
            'meta_key'               => ['type' => 'string'],
            'meta_value'             => ['type' => 'mixed'],
            'meta_query'             => ['type' => 'array'],
            'author'                 => ['type' => ['integer', 'string']],
            'author_name'            => ['type' => 'string'],
            'author__in'             => ['type' => 'array'],
            'author__not_in'         => ['type' => 'array'],
            'year'                   => ['type' => 'integer'],
            'monthnum'               => ['type' => 'integer'],
            'day'                    => ['type' => 'integer'],
            'date_query'             => ['type' => 'array'],
            'no_found_rows'          => ['type' => 'boolean'],
            'update_post_meta_cache' => ['type' => 'boolean'],
            'update_post_term_cache' => ['type' => 'boolean'],
            'respect'                => ['type' => 'array'],
            'filter'                 => ['type' => 'array'],
        ],
        'required'   => ['post_type', 'posts_per_page']
    ])]
    #[RestRouter(
        namespace: 'api/posts',
        route: 'v1/query',
        methods: 'POST',
    )]
    #[Middleware(RateLimitMiddleware::class, [60, 60])]
    public function query(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $params  = $request->get_json_params() ?: [];
        $respect = $params['respect'] ?? null;
        unset($params['respect']);

        $result = $this->postService->query($params, $respect);

        if (isset($params['paged']) && $params['paged'] > 1) {
            sleep(1);
        }

        return is_wp_error($result)
            ? $result
            : rest_ensure_response([
                'success' => true,
                'code'    => 200,
                'count'   => [
                    'found_posts'   => $result['found_posts'],
                    'max_num_pages' => $result['max_num_pages'],
                ],
                'data'    => $result['data'],
            ]);
    }

    #[RestRouter(
        namespace: 'api/posts',
        route: 'v1/react',
        methods: 'POST',
    )]
    #[Middleware(RestAuthMiddleware::class)]
    #[Middleware(RateLimitMiddleware::class, [30, 60])]
    #[Schema([
        'type'       => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'action'  => ['type' => 'string', 'enum' => ['like', 'dislike', 'favorite', 'share']],
            'value'   => ['type' => 'integer', 'enum' => [-1, 1]],
        ],
        'required'   => ['post_id', 'action', 'value']
    ])]
    public function react(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $params = $request->get_json_params() ?: [];

        $result = $this->postService->react(
            (int) ($params['post_id'] ?? 0),
            (string) ($params['action'] ?? ''),
            (int) ($params['value'] ?? 0)
        );

        return is_wp_error($result)
            ? $result
            : $this->ok($result);
    }

    #[RestRouter(
        namespace: 'api/posts',
        route: 'v1/actions/status',
        methods: 'POST',
    )]
    #[Middleware(RestAuthMiddleware::class)]
    #[Middleware(RateLimitMiddleware::class, [60, 60])]
    #[Schema([
        'type'       => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
        ],
        'required'   => ['post_id']
    ])]
    public function actionStatus(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $params = $request->get_json_params() ?: [];
        return $this->ok($this->postService->currentUserActionStatus((int) ($params['post_id'] ?? 0)));
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
