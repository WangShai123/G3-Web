<?php
if (!defined('ABSPATH')) exit;

use JEALER\G3\Services\WechatOAService;
use Psr\Http\Message\ResponseInterface;

try {
    $service = WechatOAService::run();

    error_log('Wechat callback file.');

    if (!$service->isAvailable()) {
        http_response_code(503);
        exit('Service not available');
    }

    // ======================
    // 1. 处理 GET：仅做 Token 验证
    // ======================
    if ($method === 'GET') {
        $signature = $_GET['signature'] ?? '';
        $timestamp = $_GET['timestamp'] ?? '';
        $nonce     = $_GET['nonce'] ?? '';
        $echostr   = $_GET['echostr'] ?? '';

        if (empty($echostr)) {
            http_response_code(400);
            exit('Missing echostr');
        }

        // 从 WordPress 选项中读取 token（不初始化完整服务）
        $config = get_option('g3_wechat_oa_settings', []);
        $token  = $config['token'] ?? '';

        if (empty($token)) {
            error_log('WeChat callback: Token not configured');
            http_response_code(500);
            exit('Token missing');
        }

        // 验证签名
        $tmpArr = [$token, $timestamp, $nonce];
        sort($tmpArr, SORT_STRING);
        $signatureCalculated = sha1(implode('', $tmpArr));

        if ($signatureCalculated === $signature) {
            error_log('WeChat callback: Token verification SUCCESS');
            // 清除缓冲，直接输出 echostr
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: text/plain; charset=utf-8');
            echo $echostr;
            exit;
        } else {
            error_log('WeChat callback: Token verification FAILED');
            http_response_code(403);
            exit('Forbidden');
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