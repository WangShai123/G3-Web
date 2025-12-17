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
use JEALER\G3\Services\SystemService;
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

            // Handle WeChat server verification (GET request)
            // if ($request->get_method() === 'GET') {
            //     // Let EasyWeChat handle the verification
            //     $server   = $service->app->getServer();
            //     $response = $server->serve();

            //     // For GET requests, EasyWeChat should return the echostr directly
            //     $content    = $response->getBody()->getContents();
            //     $statusCode = $response->getStatusCode();

            //     $wpResponse = new WP_REST_Response($content, $statusCode);

            //     // Set response headers
            //     foreach ($response->getHeaders() as $header => $values) {
            //         $wpResponse->header($header, implode(', ', $values));
            //     }

            //     return $wpResponse;
            // }

            // Handle WeChat server verification (GET request)
            if ($request->get_method() === 'GET') {
                // Manual verification - more reliable approach
                $signature = $request->get_param('signature');
                $timestamp = $request->get_param('timestamp');
                $nonce     = $request->get_param('nonce');
                $echostr   = $request->get_param('echostr');

                error_log("WeChat Verification - Signature: $signature, Timestamp: $timestamp, Nonce: $nonce, Echostr: $echostr");

                // Get token from service config
                $config = get_option(SystemService::OPEN_WECHAT_OA_KEY);
                $token  = $config['token'] ?? '';

                error_log("WeChat Token from config: $token");

                if (empty($token)) {
                    error_log('WeChat token is not configured');
                    return new WP_REST_Response('Forbidden', 403);
                }

                // Sort parameters lexicographically
                $params = [$token, $timestamp, $nonce];
                sort($params, SORT_STRING);

                // Concatenate parameters
                $concatenated = implode('', $params);

                // Generate SHA1 hash
                $hash = sha1($concatenated);

                error_log("Calculated hash: $hash, Received signature: $signature");

                // Compare with signature
                if ($hash === $signature) {
                    // Return echostr to confirm successful verification
                    error_log("WeChat verification successful, returning echostr: $echostr");
                    return new WP_REST_Response($echostr, 200);
                } else {
                    error_log("WeChat verification failed");
                    return new WP_REST_Response('Forbidden', 403);
                }
            }

            // Handle WeChat server push messages
            error_log('Handling WeChat server push messages');
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
