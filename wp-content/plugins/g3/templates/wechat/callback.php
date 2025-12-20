<?php
if (!defined('ABSPATH')) exit;

use JEALER\G3\Services\WechatOAService;
use Psr\Http\Message\ResponseInterface;

try {
    $service = WechatOAService::run();

    if (!$service->isAvailable()) {
        http_response_code(503);
        exit('Service not available');
    }

    /** @var ResponseInterface $response */
    $response = $service->app->getServer()->serve();

    // === 手动发送 PSR-7 响应（EasyWeChat 6.x 标准做法）===

    // 1. 发送状态码
    http_response_code($response->getStatusCode());

    // 2. 发送所有 headers
    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header(sprintf('%s: %s', $name, $value), false);
        }
    }

    // 3. 清除所有输出缓冲（防止 WordPress 或其他代码污染）
    while (ob_get_level()) {
        ob_end_clean();
    }

    // 4. 输出 body 并结束
    echo $response->getBody();
    exit;


}
catch (Exception $e) {
    error_log('WeChat Callback Error: ' . $e->getMessage());
    http_response_code(500);
    exit('error');
}