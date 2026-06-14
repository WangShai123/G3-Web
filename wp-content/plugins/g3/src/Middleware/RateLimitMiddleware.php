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

    /**
     * cache group
     * @var string
     */
    const CACHE_GROUP = 'g3_rate-limit';

    public function __construct(int $limit = 60, int $window = 60)
    {
        $this->limit  = $limit;
        $this->window = $window;
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
            return new WP_Error(
                '429',
                __('Forbidden: Rate limit exceeded', 'G3'),
                [
                    'status' => 429,
                    'expire' => $this->window
                ]
            );
        }

        return true;
    }
}
