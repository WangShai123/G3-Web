<?php
namespace JEALER\G3\Controllers;
use JEALER\G3\Core\Attributes\Middleware;
use JEALER\G3\Core\Attributes\RestRouter;
use JEALER\G3\Core\Attributes\Schema;
use JEALER\G3\Core\Router\Controller;
use JEALER\G3\Middleware\RoleMiddleware;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class EditorController extends Controller {

    #[RestRouter(
        namespace: 'api/admin/editor',
        route: 'v1/content/(?P<post_id>\d+)',
        methods: 'GET',
    )]
    #[Middleware(RoleMiddleware::class, ['administrator'])]
    public function content(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $postId = (int) $request->get_param('post_id');
        $post   = get_post($postId);

        if (!$post) {
            return new WP_Error('invalid_post_id', __('Invalid Post ID.'), ['status' => 404]);
        }

        if (!current_user_can('edit_post', $postId)) {
            return new WP_Error('forbidden', __('Forbidden', 'G3'), ['status' => 403]);
        }

        return $this->success([
            'post_id'  => $postId,
            'content'  => $post->post_content,
            'modified' => get_post_modified_time('c', true, $post),
        ]);
    }

    #[RestRouter(
        namespace: 'api/admin/editor',
        route: 'v1/content',
        methods: 'POST',
    )]
    #[Middleware(RoleMiddleware::class, ['administrator'])]
    #[Schema([
        'type'       => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'content' => ['type' => 'string'],
        ],
        'required'   => ['post_id', 'content'],
    ])]
    public function updateContent(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $params  = $request->get_json_params() ?: [];
        $postId  = (int) ($params['post_id'] ?? 0);
        $content = (string) ($params['content'] ?? '');

        if (!get_post($postId)) {
            return new WP_Error('invalid_post_id', __('Invalid Post ID.'), ['status' => 404]);
        }

        if (!current_user_can('edit_post', $postId)) {
            return new WP_Error('forbidden', __('Forbidden', 'G3'), ['status' => 403]);
        }

        $result = wp_update_post([
            'ID'           => $postId,
            'post_content' => $content,
        ], true);

        if (is_wp_error($result)) {
            return $result;
        }

        return $this->success([
            'post_id'  => $postId,
            'modified' => get_post_modified_time('c', true, $postId),
        ], __('Updated', 'G3'));
    }
}
