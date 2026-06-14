<?php
namespace JEALER\G3\Middleware;
use WP_REST_Request;
use WP_Error;
interface MiddlewareInterface {
    /**
     * Handle the middleware logic
     * 
     * 处理中间件逻辑
     */
    public function handle(WP_REST_Request $request): bool|WP_Error;
}
