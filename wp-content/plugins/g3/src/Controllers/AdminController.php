<?php
namespace JEALER\G3\Controllers;

use JEALER\G3\Attributes\RestRouter;
use JEALER\G3\Attributes\Middleware;
use JEALER\G3\Attributes\Schema;
use JEALER\G3\Middleware\RoleMiddleware;
use JEALER\G3\Middleware\RateLimitMiddleware;
use JEALER\G3\Services\WechatOAService;
use JEALER\G3\Utilities\Request;
use JEALER\G3\Utilities\Validator;
use JEALER\G3\Queue\Queue;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class AdminController {
    /**
     * Process queue jobs via REST API
     * 
     * 通过REST API处理队列任务
     * 
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: 'admin/queue/process',
        methods: 'GET'
    )]
    #[Middleware(RoleMiddleware::class, ['administrator'])]
    #[Middleware(RateLimitMiddleware::class, [60, 60])]
    public function processQueue(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data  = $request->get_json_params();
        $limit = $data['limit'] ?? 10;
        $queue = $data['queue'] ?? 'default';

        $processed = Queue::processJobs((int) $limit, $queue);

        return rest_ensure_response([
            'success'   => true,
            'processed' => $processed,
            'message'   => sprintf(__('Processed %d jobs from queue.', 'G3'), $processed)
        ]);
    }

    /**
     * Admin Controller: Update Wechat OA Reply Rule
     * 
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: 'admin/wechat_oa/reply/update',
        methods: 'POST'
    )]
    #[Middleware(RoleMiddleware::class, ['administrator'])]
    #[Middleware(RateLimitMiddleware::class, [10, 60])]
    #[Schema([
        'type'       => 'object',
        'properties' => [
            'id'       => ['type' => 'integer'],
            'keywords' => ['type' => 'string'],
            'content'  => ['type' => 'string'],
            'status'   => ['type' => 'string'],
            'type'     => ['type' => 'string'],
        ],
        'required'   => ['keywords', 'content', 'status', 'type']
    ])]
    public function updateReply(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data = $request->get_json_params();

        $result = WechatOAService::updateReply($data);
        if (is_wp_error($result)) {
            return new WP_Error($result->get_error_code(), $result->get_error_message());
        }

        return rest_ensure_response([
            'id'      => $result,
            'message' => __('Updated', 'G3')
        ]);
    }

    /**
     * Admin Controller: Delete Wechat OA Reply Rule
     * 
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: 'admin/wechat_oa/reply/delete',
        methods: 'POST'
    )]
    #[Middleware(RoleMiddleware::class, ['administrator'])]
    #[Middleware(RateLimitMiddleware::class, [10, 60])]
    #[Schema([
        'type'       => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
        ],
        'required'   => ['id']
    ])]
    public function deleteReply(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data = $request->get_json_params();

        $result = WechatOAService::deleteReply($data);
        if (is_wp_error($result)) {
            return new WP_Error($result->get_error_code(), $result->get_error_message());
        }

        return rest_ensure_response([
            'id'      => $data['id'],
            'message' => __('Deleted', 'G3')
        ]);
    }

}