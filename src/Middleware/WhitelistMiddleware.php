<?php
namespace JEALER\G3\Middleware;
use JEALER\G3\Utilities\Message;
use JEALER\G3\Utilities\System;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Whitelist Middleware
 * 
 * IP白名单中间件，仅允许指定IP访问
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class WhitelistMiddleware implements MiddlewareInterface {
    /**
     * Allowed IPs
     * 
     * 允许的IP地址列表
     * 
     * @var array
     */
    private array $allowedIps;

    public function __construct(array $allowedIps = [])
    {
        $this->allowedIps = $allowedIps ?: $this->getDefaultAllowedIps();
    }

    public function handle(WP_REST_Request $request): bool|WP_Error
    {
        $clientIp = $this->getClientIp();
        // 检查IP是否在白名单中
        if (!$this->isIpAllowed($clientIp)) {
            return new WP_Error(
                'access_denied',
                Message::forbidden(),
                ['status' => 403]
            );
        }
        return true;
    }

    /**
     * Get default allowed IPs from config
     * 
     * 从配置获取默认允许的IP地址
     * 
     * @return array
     */
    private function getDefaultAllowedIps(): array
    {
        $whitelist = System::config('whitelist');
        return is_array($whitelist) ? $whitelist : [];
    }

    /**
     * Get client IP address
     * 
     * 获取客户端IP地址
     * 
     * @return string
     */
    private function getClientIp(): string
    {
        $ipKeys = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (!isset($_SERVER[$key])) {
                continue;
            }
            $ip = $_SERVER[$key];
            // 处理 X-Forwarded-For 可能包含多个IP的情况
            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $ips = explode(',', $ip);
                $ip  = trim($ips[0]); // 使用第一个IP
            } else {
                $ip = trim($ip);
            }
            // 验证IP地址格式
            if ($this->isValidIpAddress($ip)) {
                return $ip;
            }
        }
        // 默认返回 REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Check if IP is allowed
     * 
     * 检查IP是否被允许
     * 
     * @param string $ip IP address
     * @return bool
     */
    private function isIpAllowed(string $ip): bool
    {
        // 检查是否为本地IP（开发环境）
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }
        // 检查精确匹配
        if (in_array($ip, $this->allowedIps)) {
            return true;
        }
        // 检查CIDR范围
        foreach ($this->allowedIps as $allowedIp) {
            if ($this->ipInRange($ip, $allowedIp)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Validate IP address format
     * 
     * 验证IP地址格式
     * 
     * @param string $ip IP address
     * @return bool
     */
    private function isValidIpAddress(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Check if IP is in CIDR range
     * 
     * 检查IP是否在CIDR范围内
     * 
     * @param string $ip IP address to check
     * @param string $cidr CIDR notation (e.g., 192.168.1.0/24)
     * @return bool
     */
    private function ipInRange(string $ip, string $cidr): bool
    {
        if (strpos($cidr, '/') === false) {
            // 不是CIDR格式，直接比较
            return $ip === $cidr;
        }

        list($subnet, $mask) = explode('/', $cidr);

        // 检查IP和子网是否为同一类型（IPv4或IPv6）
        if (
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
            filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        ) {

            $ipLong     = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskLong   = ~((1 << (32 - $mask)) - 1);

            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        } elseif (
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) &&
            filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
        ) {
            // 对于IPv6的CIDR检查
            return $this->ipv6InRange($ip, $subnet, (int) $mask);
        }

        return false;
    }

    /**
     * Check IPv6 in range
     * 
     * 检查IPv6是否在范围内
     * 
     * @param string $ip IPv6 address
     * @param string $subnet IPv6 subnet
     * @param int $mask Subnet mask
     * @return bool
     */
    private function ipv6InRange(string $ip, string $subnet, int $mask): bool
    {
        $ipBytes     = inet_pton($ip);
        $subnetBytes = inet_pton($subnet);

        if ($ipBytes === false || $subnetBytes === false) {
            return false;
        }

        $bytesToCheck = intval($mask / 8);
        $bitsToCheck  = $mask % 8;

        // check full bytes
        for ($i = 0; $i < $bytesToCheck; $i++) {
            if ($ipBytes[$i] !== $subnetBytes[$i]) {
                return false;
            }
        }

        // check remaining bits
        if ($bitsToCheck > 0 && $bytesToCheck < strlen($ipBytes)) {
            $maskByte = chr((0xFF00 >> $bitsToCheck) & 0xFF);
            if (($ipBytes[$bytesToCheck] & $maskByte) !== ($subnetBytes[$bytesToCheck] & $maskByte)) {
                return false;
            }
        }

        return true;
    }
}
