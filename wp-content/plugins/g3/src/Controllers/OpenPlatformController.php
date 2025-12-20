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

class OpenPlatformController {

    /**
     * WeChat Official Account Platform callback
     *
     * @param WP_REST_Request $request
     * @return 
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: 'wechat_oa/callback',
        methods: ['GET', 'POST']
    )]
    public function wechatOACallback(WP_REST_Request $request)
    {
        try {
            $service = WechatOAService::run();

            if (!$service->isAvailable()) {
                return new WP_REST_Response([
                    'message' => 'Service not available'
                ], 503);
            }

            // Handle WeChat server verification (GET request)
            // if ($request->get_method() === 'GET') {
            //     // Manual verification - more reliable approach
            //     $signature = $request->get_param('signature');
            //     $timestamp = $request->get_param('timestamp');
            //     $nonce     = $request->get_param('nonce');
            //     $echostr   = $request->get_param('echostr');

            //     // Get token from service config - FIXED: Use the correct option key
            //     $config = get_option(SystemService::OPEN_WECHAT_OA_KEY);
            //     $token  = $config['token'] ?? '';

            //     if (empty($token)) {
            //         return new WP_REST_Response('Forbidden', 403);
            //     }

            //     // Sort parameters lexicographically
            //     $params = [$token, $timestamp, $nonce];
            //     sort($params, SORT_STRING);

            //     // Concatenate parameters
            //     $concatenated = implode('', $params);

            //     // Generate SHA1 hash
            //     $hash = sha1($concatenated);

            //     // Compare with signature
            //     if ($hash === $signature) {
            //         // return new WP_REST_Response($echostr, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
            //         while (ob_get_level()) {
            //             ob_end_clean();
            //         }
            //         header('Content-Type: text/plain; charset=utf-8');
            //         echo $echostr;
            //         exit;
            //     } else {
            //         // return new WP_REST_Response('Forbidden', 403);
            //         header('HTTP/1.1 403 Forbidden');
            //         echo 'Forbidden';
            //         exit;
            //     }
            // }

            // Let EasyWeChat handle both GET (verification) and POST (messages)
            // $response = $service->app->getServer()->serve();

            // Let EasyWeChat handle everything
            $psr7Response = $service->app->getServer()->serve();

            // --- Convert PSR-7 Response to WP_REST_Response ---
            $body   = $psr7Response->getBody()->__toString();
            $status = $psr7Response->getStatusCode();

            // Flatten headers for WordPress (array of strings)
            $headers = [];
            foreach ($psr7Response->getHeaders() as $name => $values) {
                $headers[$name] = implode(', ', $values);
            }

            error_log('WeChat OA Callback Response: ' . $body);

            return new WP_REST_Response($body, $status, $headers);


        }
        catch (\Exception $e) {
            error_log('WeChat OA Callback Error: ' . $e->getMessage());
            return new WP_REST_Response('Internal Server Error', 500);
        }
    }
}
