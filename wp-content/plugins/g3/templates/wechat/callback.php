<?php
if (!defined('ABSPATH')) exit;

use JEALER\G3\Services\SystemService;
use JEALER\G3\Services\WechatOAService;
use Psr\Http\Message\ResponseInterface;

try {
    $service = WechatOAService::run();

    error_log('Wechat callback file.');

    if (!$service->isAvailable()) {
        http_response_code(503);
        exit('Service not available');
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

    // ======================
    // Handle WeChat server verification (GET request)
    // ======================
    if ($method === 'GET') {
        // Manual verification - more reliable approach
        $signature = $_GET['signature'] ?? '';
        $timestamp = $_GET['timestamp'] ?? '';
        $nonce     = $_GET['nonce'] ?? '';
        $echostr   = $_GET['echostr'] ?? '';

        // Get token from service config - FIXED: Use the correct option key
        $config = get_option(SystemService::OPEN_WECHAT_OA_KEY);
        $token  = $config['token'] ?? '';

        if (empty($token)) {
            error_log('WeChat callback: Token not configured');
            http_response_code(500);
            exit('Token missing');
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
            // return new WP_REST_Response($echostr, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
            while (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Type: text/plain; charset=utf-8');
            echo $echostr;
            exit;
        } else {
            // return new WP_REST_Response('Forbidden', 403);
            header('HTTP/1.1 403 Forbidden');
            echo 'Forbidden';
            exit;
        }
    }

    // ======================
    // 2. 处理 POST：消息交互
    // ======================
    if ($method === 'POST') {
        try {
            $service = WechatOAService::run();

            if (!$service->isAvailable()) {
                error_log('WeChat callback: Service not available');
                http_response_code(503);
                exit('Service not available');
            }

            /** @var ResponseInterface $response */
            $response = $service->app->getServer()->serve();

            // 发送 PSR-7 响应
            http_response_code($response->getStatusCode());

            foreach ($response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    header(sprintf('%s: %s', $name, $value), false);
                }
            }

            while (ob_get_level()) {
                ob_end_clean();
            }

            echo $response->getBody();
            exit;

        }
        catch (Exception $e) {
            error_log('WeChat Callback Error (POST): ' . $e->getMessage());
            http_response_code(500);
            exit('error');
        }
    }

    // ======================
    // 3. 其他方法：拒绝
    // ======================
    http_response_code(405);
    exit('Method Not Allowed');
}
catch (Exception $e) {
    error_log('WeChat Callback Error: ' . $e->getMessage());
    http_response_code(500);
    exit('error');
}