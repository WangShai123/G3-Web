<?php
if (!defined('ABSPATH')) exit;

use JEALER\G3\Service;
use JEALER\G3\Services\SystemService;
use JEALER\G3\Services\WechatOAService;
use Psr\Http\Message\ResponseInterface;

try {
    $service = Container::run()->get(WechatOAService::class);

    if (!$service->available()) {
        http_response_code(503);
        exit('Service not available');
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

    // ======================
    // Handle WeChat server verification (GET request)
    // ======================
    if ($method === 'GET') {
        // Get parameters from GET request
        $signature = $_GET['signature'] ?? '';
        $timestamp = $_GET['timestamp'] ?? '';
        $nonce     = $_GET['nonce'] ?? '';
        $echostr   = $_GET['echostr'] ?? '';

        // Get token from service config
        $config = get_option(SystemService::OPEN_WECHAT_OA_KEY);
        $token  = $config['token'] ?? '';

        if (empty($token)) {
            http_response_code(403);
            exit('Forbidden');
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
            // Clean any output buffer
            while (ob_get_level()) {
                ob_end_clean();
            }

            // Set proper content type and return echostr with 200 status
            http_response_code(200);
            header('Content-Type: text/plain; charset=utf-8');
            echo $echostr;
            exit;
        } else {
            http_response_code(403);
            exit('Forbidden');
        }
    }

    // ======================
    // 2. 处理 POST：消息交互
    // ======================
    if ($method === 'POST') {
        try {
            // Serve the request and get response
            $response = $service->app->getServer()->serve();

            // Clean any output buffer
            while (ob_get_level()) {
                ob_end_clean();
            }

            // Set proper headers from the response
            foreach ($response->getHeaders() as $header => $values) {
                header($header . ': ' . implode(', ', $values));
            }

            // Set response code
            http_response_code($response->getStatusCode());

            // Output response body directly
            echo $response->getBody();
            exit;
        }
        catch (Exception $e) {
            error_log('WeChat Callback POST Error: ' . $e->getMessage());
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