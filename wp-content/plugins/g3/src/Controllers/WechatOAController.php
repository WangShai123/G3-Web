<?php
namespace JEALER\G3\Controllers;

use JEALER\G3\Attributes\RestRouter;
use JEALER\G3\Attributes\Middleware;
use JEALER\G3\Attributes\Schema;
use JEALER\G3\Middleware\RestAuthMiddleware;
use JEALER\G3\Middleware\RoleMiddleware;
use JEALER\G3\Middleware\RateLimitMiddleware;
use JEALER\G3\Utilities\Request;
use JEALER\G3\Services\WechatOAService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class WechatOAController {

    #[RestRouter(
        namespace: 'api/v1',
        route: 'wechat_oa/callback',
        methods: ['GET', 'POST']
    )]
    public function callback(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        try {
            $service = WechatOAService::run();

            if (!$service->isAvailable()) {
                error_log('Service app not uninitialized');
                return new WP_REST_Response([
                    'message' => 'Service not available'
                ], 503);
            }

            // Handle WeChat server push messages
            $response = $service->app->getServer()->serve();

            // Get response content and status code
            $content    = $response->getBody()->getContents();
            $statusCode = $response->getStatusCode();

            // Create WordPress REST response
            $wpResponse = new WP_REST_Response($content, $statusCode);

            // Set response headers
            foreach ($response->getHeaders() as $header => $values) {
                $wpResponse->header($header, implode(', ', $values));
            }

            return $wpResponse;

        }
        catch (\Exception $e) {
            // Log the error
            error_log('WeChat callback error: ' . $e->getMessage());

            // Return error response
            return new WP_REST_Response([
                'message' => 'Internal Server Error'
            ], 500);
        }
    }
}
