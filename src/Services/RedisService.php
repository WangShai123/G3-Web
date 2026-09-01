<?php
namespace JEALER\G3\Services;
use JEALER\G3\Core\Service\Service;
use Redis;
use Throwable;

class RedisService extends Service {
    private ?Redis $redis = null;

    /**
     * 初始化 Redis 连接
     *
     * @param int   $db default 0
     * @param array $config Optional Redis Config
     * - host: Redis 主机名, string, default '127.0.0.1'
     * - port: Redis 端口号, int, default 6379
     * - timeout: Redis 超时时间, float, default 2.0
     * - reserved: Redis 预留连接数, int, default null
     * - retry_interval: Redis 重试间隔, int, default 0
     * - read_timeout: Redis 读取超时时间, float, default 0.0
     * - password: Redis 密码, string, default null
     * 
     * @throws Throwable
     * @return Redis|null
     */
    public function init(int $db = 0, array $config = []): ?Redis
    {
        try {
            $this->redis = $this->connect($config);
            $this->redis->select($db);
            return $this->redis;
        }
        catch (Throwable) {
            return null;
        }
    }

    public function connect(array $config = []): Redis
    {
        $host          = $config['host'] ?? (defined('WP_REDIS_HOST') ? WP_REDIS_HOST : '127.0.0.1');
        $port          = (int) ($config['port'] ?? (defined('WP_REDIS_PORT') ? WP_REDIS_PORT : 6379));
        $timeout       = (float) ($config['timeout'] ?? (defined('WP_REDIS_TIMEOUT') ? WP_REDIS_TIMEOUT : 2));
        $reserved      = $config['reserved'] ?? (defined('WP_REDIS_RESERVED') ? WP_REDIS_RESERVED : null);
        $retryInterval = (int) ($config['retry_interval'] ?? (defined('WP_REDIS_RETRY_INTERVAL') ? WP_REDIS_RETRY_INTERVAL : 0));
        $readTimeout   = (float) ($config['read_timeout'] ?? (defined('WP_REDIS_READ_TIMEOUT') ? WP_REDIS_READ_TIMEOUT : 0));
        $password      = $config['password'] ?? (defined('WP_REDIS_PASSWORD') ? WP_REDIS_PASSWORD : null);

        $redis = $this->container->get(Redis::class);
        $redis->connect($host, $port, $timeout, $reserved, $retryInterval, $readTimeout);
        if ($password) {
            $redis->auth($password);
        }

        return $redis;
    }
}
