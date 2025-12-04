<?php
namespace JEALER\G3\Middleware;

use WP_REST_Request;
use WP_Error;

interface MiddlewareInterface {
    /**
     * Handle the middleware logic.
     * 
     * 处理中间件逻辑
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function handle(WP_REST_Request $request);
}