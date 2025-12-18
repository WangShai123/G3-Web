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

            $test = $this->decryptAndLogResponse($content);
            error_log('WeChat OA - Decrypted response content: ' . $test);

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
     * 解密微信响应内容并记录日志（仅用于调试）
     * 
     * @param string $encryptedResponse
     */
    private function decryptAndLogResponse(string $encryptedResponse): void
    {
        try {
            // 从响应中提取加密数据
            $xml = simplexml_load_string($encryptedResponse, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($xml && isset($xml->Encrypt)) {
                $encrypt = (string) $xml->Encrypt;

                // 获取EncodingAESKey
                $config         = get_option(SystemService::OPEN_WECHAT_OA_KEY);
                $encodingAESKey = $config['encodingAESKey'] ?? '';

                if (!empty($encodingAESKey)) {
                    // 解密过程
                    $decrypted = $this->decrypt($encrypt, $encodingAESKey);
                    error_log('WeChat OA - Decrypted response content: ' . $decrypted);
                } else {
                    error_log('WeChat OA - EncodingAESKey not found for decryption');
                }
            } else {
                error_log('WeChat OA - Invalid encrypted response format');
            }
        }
        catch (\Exception $e) {
            error_log('WeChat OA - Decryption error: ' . $e->getMessage());
        }
    }

    /**
     * 微信消息解密方法
     * 
     * @param string $encrypted
     * @param string $encodingAESKey
     * @return string
     */
    private function decrypt(string $encrypted, string $encodingAESKey): string
    {
        try {
            // 微信加密数据解密逻辑
            $key            = base64_decode($encodingAESKey . "=");
            $ciphertext_dec = base64_decode($encrypted);
            $iv             = substr($key, 0, 16);

            $decrypted = openssl_decrypt($ciphertext_dec, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);

            // 去除补位字符
            if ($decrypted) {
                $pad = ord(substr($decrypted, -1));
                if ($pad < 1 || $pad > 32) {
                    $pad = 0;
                }
                return substr($decrypted, 0, (strlen($decrypted) - $pad));
            }

            return '';
        }
        catch (\Exception $e) {
            error_log('WeChat OA - Decrypt exception: ' . $e->getMessage());
            return '';
        }
    }
}
