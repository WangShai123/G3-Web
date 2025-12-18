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
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class AdminController {

    /**
     * 添加自定义回复
     * 
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: 'admin/wechat_oa/reply/add',
        methods: 'POST'
    )]
    #[Middleware(RoleMiddleware::class, ['administrator'])]
    // #[Middleware(RateLimitMiddleware::class, [10, 60])]
    #[Schema([
        'type'       => 'object',
        'properties' => [
            'keywords' => ['type' => 'string'],
            'content'  => ['type' => 'string'],
            'status'   => ['type' => 'string'],
            'type'     => ['type' => 'string'],
        ],
        'required'   => ['keywords', 'content', 'status', 'type']
    ])]
    public function addReply(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data = $request->get_json_params();
        error_log(print_r($data, true));
        $result = WechatOAService::addReply($data);

        if (is_wp_error($result)) {
            return new WP_Error($result->get_error_code(), $result->get_error_message());
        }

        return new WP_REST_Response([
            'id'      => $result,
            'message' => __('Added', 'G3')
        ], 200);
    }
}