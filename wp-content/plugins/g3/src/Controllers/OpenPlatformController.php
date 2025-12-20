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

            // === 关键：直接输出原始响应，不经过 WordPress REST 层 ===
            http_response_code($psr7Response->getStatusCode());

            // 输出所有 headers
            foreach ($psr7Response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    header(sprintf('%s: %s', $name, $value), false); // false = 不替换已有同名头
                }
            }

            // 清除所有输出缓冲（防止 WordPress 或插件污染输出）
            if (ob_get_level()) {
                ob_end_clean();
            }

            // 输出 body 并立即终止
            echo $psr7Response->getBody()->__toString();
            exit;

        }
        catch (\Exception $e) {
            error_log('WeChat OA Callback Error: ' . $e->getMessage());
            http_response_code(500);
            echo 'error';
            exit;
        }
    }
}
