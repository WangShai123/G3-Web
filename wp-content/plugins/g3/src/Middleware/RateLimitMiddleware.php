<?php
namespace JEALER\G3\Middleware;
use JEALER\G3\Utilities\System;
use WP_REST_Request;
use WP_Error;

class RateLimitMiddleware implements MiddlewareInterface {
    private int $limit;
    private int $window;

    /**
     * @param int $limit 请求次数限制
     * @param int $window 时间窗口（秒）
     */
    public function __construct(int $limit = 60, int $window = 60)
    {
        $this->limit  = $limit;
        $this->window = $window;
    }

    /**
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function handle(WP_REST_Request $request)
    {
        // Get client IP address
        $ip = System::getClientIP();

        // Set cache key
        $cacheKey = 'rate_limit_' . md5($ip . $request->get_route());

        // Get current count
        $count = get_transient($cacheKey);
        if ($count === false) {
            $count = 0;
        }

        // Increment count
        $count++;

        // If it's the first request, set expiration time
        if ($count === 1) {
            set_transient($cacheKey, $count, $this->window);
        } else {
            // Update count
            set_transient($cacheKey, $count, $this->window);
        }

        // Check if count exceeds limit
        if ($count > $this->limit) {
            return new WP_Error(
                '429',
                // '请求过于频繁，请稍后再试',
                __('Rate limit exceeded, please try again later', 'G3'),
                [
                    'status' => 429,
                    'expire' => $this->window
                ]
            );
        }

        return true;
    }

}