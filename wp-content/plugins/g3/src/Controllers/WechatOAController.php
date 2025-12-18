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

            // Handle WeChat server push messages
            error_log('WeChat OA - Processing incoming message from ' . $_SERVER['REMOTE_ADDR']);
            $response = $service->app->getServer()->serve();

            // Get response content and status code
            $content    = $response->getBody()->getContents();
            $statusCode = $response->getStatusCode();

            error_log('WeChat OA - Response content length: ' . strlen($content));
            error_log('WeChat OA - Response status code: ' . $statusCode);
            error_log('WeChat OA - First 200 chars of response: ' . substr($content, 0, 200));

            // Create WordPress REST response
            $wpResponse = new WP_REST_Response($content, $statusCode);

            // Set response headers
            foreach ($response->getHeaders() as $header => $values) {
                $wpResponse->header($header, implode(', ', $values));
            }

            $wpResponse->header('Content-Type', 'application/xml; charset=utf-8');

            error_log('WeChat OA - Sending response with headers: ' . json_encode($wpResponse->get_headers()));

            error_log('WeChat OA - Sending response: ' . $content);

            // 尝试使用EasyWeChat解密响应内容并打印（仅用于调试）
            $this->decryptAndLogResponseWithEasyWeChat($content);

            return $wpResponse;

        }
        catch (\Exception $e) {
            // Log the error
            error_log('WeChat callback error: ' . $e->getMessage());
            error_log('WeChat callback error trace: ' . $e->getTraceAsString());

            // Return error response
            return new WP_REST_Response([
                'message' => 'Internal Server Error'
            ], 500);
        }
    }
    /**
     * 使用EasyWeChat解密微信响应内容并记录日志（仅用于调试）
     * 
     * @param string $encryptedResponse
     */
    private function decryptAndLogResponseWithEasyWeChat(string $encryptedResponse): void
    {
        try {
            error_log('WeChat OA - Starting decryption process with EasyWeChat');
            $service = WechatOAService::run();
            $app     = $service->app;

            // 从响应中提取加密数据
            $xml = simplexml_load_string($encryptedResponse, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($xml && isset($xml->Encrypt)) {
                $encrypt = (string) $xml->Encrypt;
                error_log('WeChat OA - Encrypted data length: ' . strlen($encrypt));

                // 使用EasyWeChat解密
                if (isset($app)) {
                    // 获取消息加密器
                    $encryptor = $app->getRequest();

                    // 获取配置信息
                    $config = get_option(SystemService::OPEN_WECHAT_OA_KEY);
                    $token  = $config['token'] ?? '';
                    $aesKey = $config['encodingAESKey'] ?? '';
                    $appId  = $config['appId'] ?? '';

                    if (!empty($token) && !empty($aesKey) && !empty($appId)) {
                        error_log('WeChat OA - Decrypting with appId: ' . $appId);

                        // 使用EasyWeChat的加密模块解密
                        $decrypted = $app->getEncryptionClient()->decrypt(
                            $encrypt,
                            $aesKey,
                            $appId
                        );

                        if (!empty($decrypted)) {
                            error_log('WeChat OA - Decrypted response content with EasyWeChat: ' . $decrypted);
                        } else {
                            error_log('WeChat OA - EasyWeChat decryption returned empty result');
                        }
                    } else {
                        error_log('WeChat OA - Missing configuration for decryption');
                    }
                } else {
                    error_log('WeChat OA - App not available for decryption');
                }
            } else {
                error_log('WeChat OA - Invalid encrypted response format');
                error_log('WeChat OA - Response content: ' . $encryptedResponse);
            }
        }
        catch (\Exception $e) {
            error_log('WeChat OA - EasyWeChat decryption error: ' . $e->getMessage());
            error_log('WeChat OA - EasyWeChat decryption error trace: ' . $e->getTraceAsString());
        }
    }
}
