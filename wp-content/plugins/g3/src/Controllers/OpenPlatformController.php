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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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
            if ($request->get_method() === 'GET') {
                // Manual verification - more reliable approach
                $signature = $request->get_param('signature');
                $timestamp = $request->get_param('timestamp');
                $nonce     = $request->get_param('nonce');
                $echostr   = $request->get_param('echostr');

                // Get token from service config - FIXED: Use the correct option key
                $config = get_option(SystemService::OPEN_WECHAT_OA_KEY);
                $token  = $config['token'] ?? '';

                if (empty($token)) {
                    return new WP_REST_Response('Forbidden', 403);
                }

                // Sort parameters lexicographically
                $params = [$token, $timestamp, $nonce];
                sort($params, SORT_STRING);

                // Concatenate parameters
                $concatenated = implode('', $params);

                // Generate SHA1 hash
                $hash = sha1($concatenated);

                // Compare with signature
                if ($hash === $signature) {

                    while (ob_get_level()) {
                        ob_end_clean();
                    }

                    // 直接输出echostr并结束脚本执行
                    header('Content-Type: text/plain; charset=utf-8');
                    echo $echostr;
                    exit;
                } else {
                    header('HTTP/1.1 403 Forbidden');
                    echo 'Forbidden';
                    exit;
                }
            }

            // Handle WeChat server push messages (POST request)
            error_log('WeChat OA - Processing incoming message from ' . $_SERVER['REMOTE_ADDR']);
            // Serve the request and get response
            $response = $service->app->getServer()->serve();

            error_log('WeChat OA - Full response: ' . print_r($response, true));

            $response->send();

            return $response;

            // // 获取实际的响应内容（只获取一次）
            // $responseBody = $response->getBody();
            // $content      = '';

            // if (method_exists($responseBody, '__toString')) {
            //     $content = (string) $responseBody;
            //     // error_log('WeChat OA - Actual response body: ' . $content);
            // } else {
            //     // 保存当前位置以便可以重新读取
            //     $responseBody->rewind();
            //     $content = $responseBody->getContents();
            //     // error_log('WeChat OA - Actual response contents: ' . $content);
            // }

            // 重置指针位置以确保能读取内容
            // $responseBody->rewind();
            // $content = $responseBody->getContents();



            // error_log('WeChat OA - Actual response body: ' . $content);

            // return $content;

            // Get response status code
            // $statusCode = $response->getStatusCode();

            // error_log('WeChat OA - Response content length: ' . strlen($content));
            // error_log('WeChat OA - Response status code: ' . $statusCode);
            // error_log('WeChat OA - First 200 chars of response: ' . substr($content, 0, 200));

            // // Create WordPress REST response
            // $wpResponse = new WP_REST_Response($content, $statusCode);

            // // Set response headers
            // foreach ($response->getHeaders() as $header => $values) {
            //     $wpResponse->header($header, implode(', ', $values));
            // }

            // $wpResponse->header('Content-Type', 'application/xml; charset=utf-8');

            // // error_log('WeChat OA - Sending response with headers: ' . json_encode($wpResponse->get_headers()));

            // // error_log('WeChat OA - Sending response: ' . $content);

            // return $wpResponse;

            // 直接输出响应内容，不使用WP_REST_Response包装
            // while (ob_get_level()) {
            //     ob_end_clean();
            // }

            // 设置正确的Content-Type头
            // header('Content-Type: application/xml; charset=utf-8');

            // Set proper headers
            // foreach ($response->getHeaders() as $header => $values) {
            //     header($header . ': ' . implode(', ', $values));
            // }

            // http_response_code($response->getStatusCode());

            // echo $response->getBody();

            // 输出响应内容
            // echo $content;
            // exit;

        }
        catch (\Exception $e) {
            // Log the error
            error_log('WeChat callback error: ' . $e->getMessage());
            error_log('WeChat callback error trace: ' . $e->getTraceAsString());

            // // Return error response
            // return new WP_REST_Response([
            //     'message' => 'Internal Server Error'
            // ], 500);
            // Return error response
            header('HTTP/1.1 500 Internal Server Error');
            echo 'Internal Server Error';
            exit;
        }
    }
}
