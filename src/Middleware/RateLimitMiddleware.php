<?php
namespace JEALER\G3\Middleware;
use JEALER\G3\Utilities\System;
use WP_REST_Request;
use WP_Error;

/**
 * Rate Limit Middleware
 * 
 * 限流中间件
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class RateLimitMiddleware implements MiddlewareInterface {

    /**
     * request limit
     * @var int
     */
    private int $limit;

    /**
     * time window in seconds
     * @var int
     */
    private int $window;

    private string $message;

    /**
     * cache group
     * @var string
     */
    const CACHE_GROUP = 'g3_rate-limit';

    public function __construct(int $limit = 60, int $window = 60, string $message = '')
    {
        $this->limit   = $limit;
        $this->window  = $window;
        $this->message = $message;
    }

    public function handle(WP_REST_Request $request): bool|WP_Error
    {
        $ip       = System::ip();
        $cacheKey = md5($ip . $request->get_route());

        $count = wp_cache_get($cacheKey, self::CACHE_GROUP);
        if ($count === false) {
            $count = 0;
        }
        $count++;
        wp_cache_set($cacheKey, $count, self::CACHE_GROUP, $this->window);

        if ($count > $this->limit) {

            $message = trim($this->message) === '' ? sprintf(__('Forbidden: Rate limit exceeded. Please try later after %s seconds.', 'G3'), $this->window) : $this->message;

            return new WP_Error(
                '429',
                $message,
                [
                    'status' => 429,
                    'expire' => $this->window
                ]
            );
        }

        return true;
    }
}
