<?php
namespace JEALER\G3\Utilities;
use JEALER\G3\Services\SystemService;

final class System {

    /**
     * Get environment variable
     * 
     * 获取环境变量
     * 
     * @param string $key Variable key
     * @param mixed $default Default value
     * @return mixed
     */
    public static function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        // 尝试转换常见值类型
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }

        if (is_numeric($value)) {
            // 转换为数字
            return $value + 0;
        }

        return $value;
    }

    /**
     * Check if current context is admin area or running WP CLI
     * 
     * 检查当前是否在后台环境或运行 WP CLI
     * 
     * @return bool
     */
    public static function isAdminContext(): bool
    {
        return is_admin() || (defined('WP_CLI') && constant('WP_CLI') === true);
    }

    /**
     * Check if debug mode is enabled.
     * 
     * 检查调试模式是否启用，依赖 WP_ENVIRONMENT_TYPE ['development' | 'local']
     * 
     * @return bool
     */
    // public static function debug(): bool
    // {
    //     $v = get_option(SystemService::SETTING_OPTION_KEY)['environment'] ?? '';
    //     if (in_array($v, ['development', 'local'])) {
    //         return true;
    //     }
    //     return false;
    // }

    /**
     * Check if debug mode is enabled.
     * 
     * 检查调试模式是否启用，依赖 WP_DEBUG & WP_DEBUG_LOG
     * 
     * @return bool
     */
    public static function debug(): bool
    {
        return defined('WP_DEBUG') && WP_DEBUG && WP_DEBUG_LOG;
    }

    /**
     * Get the path to the error log file.
     * 
     * 获取错误日志文件路径。
     *
     * @return bool|string The path to the error log file, or false if not set.
     */
    public static function errorLogPath(): bool|string
    {
        $path = ini_get('error_log');
        if ($path === false || trim($path) === '') {
            return false;
        }
        return $path;
    }

    /**
     * Get the IP address of the client. Priority is given to IPv4 address. If IPv4 address is not available, IPv6 address is returned.
     * 
     * 获取客户端的IP地址。优先返回IPv4地址。如果不存在IPv4地址，则返回IPv6地址。
     *
     * @return string|bool The IP address of the client or false if unknown.
     */
    public static function ip(): string|bool
    {
        $ipv4 = self::ipv4();
        if ($ipv4 !== false) {
            return $ipv4;
        }
        return self::ipv6();
    }

    /**
     * Get the IPv4 address of the client. If IPv4 address is not available, false is returned.
     * 
     * 获取客户端的IPv4地址。如果不存在IPv4地址，则返回false。
     *
     * @return string|bool The IPv4 address of the client or false if unknown.
     */
    public static function ipv4(): string|bool
    {
        $ip = self::getClientIpRaw();
        if ($ip !== false) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $ip;
            }
            // 如果是IPv6格式的IPv4映射地址，提取IPv4部分
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $long = inet_pton($ip);
                if ($long !== false && substr($long, 0, 12) === "\0\0\0\0\0\0\0\0\0\0\xff\xff") {
                    // IPv4-mapped IPv6 address
                    return inet_ntop(substr($long, 12, 4));
                }
            }
        }
        return false;
    }

    /**
     * Get the IPv6 address of the client. If IPv6 address is not available, false is returned.
     * 
     * 获取客户端的IPv6地址。如果不存在IPv6地址，则返回false。
     *
     * @return string|bool The IPv6 address of the client or false if unknown.
     */
    public static function ipv6(): string|bool
    {
        $ip = self::getClientIpRaw();
        if ($ip !== false && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $ip;
        }
        return false;
    }

    /**
     * Get the raw IP address of the client. If IP address is not available, false is returned.
     * 
     * 获取客户端的原始IP地址。如果不存在IP地址，则返回false。
     *
     * @return string|bool The raw IP address of the client or false if unknown.
     */
    private static function getClientIpRaw(): string|bool
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // 处理通过代理的情况，可能包含多个IP地址
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            // 返回第一个IP地址
            return trim($ips[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return false;
    }

    const APPLE  = 'aHR0cHM6Ly8=';
    const BANANA = 'YXBpLmplYWxlcg==';
    const CAR    = 'LmNvbQ==';

    /**
     * Get the name of the operating system based on PHP_OS_FAMILY.
     * 
     * 获取操作系统的名称。
     *
     * @return string The name of the operating system.
     */
    public static function osName(): string
    {
        return match (PHP_OS_FAMILY) {
            'Linux'   => 'linux',
            'Windows' => 'windows',
            'Darwin'  => 'mac',
            'BSD'     => 'bsd',
            'Solaris' => 'solaris',
            default   => 'unknown',
        };
    }

    /**
     * Get the configuration for the specified key.
     * 
     * 获取指定键的配置。
     *
     * @param string $key The key for the configuration.
     * @param array $default The default value if the key is not found.
     * @return array The configuration.
     */
    public static function config(string $key, $default = []): array
    {
        $mainConfig = PLUGINDIR . '/G3-Web/config/' . $key . '.php';
        if (file_exists($mainConfig)) {
            $main = require $mainConfig;
        } else {
            $main = $default;
        }

        $userConfig = get_stylesheet_directory() . '/config/' . $key . '.php';
        if (file_exists($userConfig)) {
            $user = (array) $userConfig;
        } else {
            $user = $default;
        }

        return array_merge($main, $user);
    }

    public static function ensureDirectory(string $directory): bool
    {
        return is_dir($directory) || wp_mkdir_p($directory);
    }
    public static function writeFile(string $file, string $content): bool
    {
        return file_put_contents($file, $content) !== false;
    }
    public static function writeArray($file, array $data): bool
    {
        // generate readable PHP array content
        $export = "<?php\nreturn " . var_export($data, true) . ";\n";

        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($file, $export) !== false;
    }

    public static function normalizeLocale(string $locale): ?string
    {
        $safeLocales = [
            'zh'                 => 'zh_CN',
            'zh_cn'              => 'zh_CN',
            'zh-cn'              => 'zh_CN',
            'zh_hans'            => 'zh_CN',
            'zh-hans'            => 'zh_CN',
            'zh_sg'              => 'zh_CN',
            'zh-sg'              => 'zh_CN',
            'zh_my'              => 'zh_CN',
            'zh-my'              => 'zh_CN',
            'zh_mo'              => 'zh_CN',
            'zh-mo'              => 'zh_CN',
            'cn'                 => 'zh_CN',
            'chinese'            => 'zh_CN',
            'simplified'         => 'zh_CN',
            'simplified_chinese' => 'zh_CN',
            'simplified-chinese' => 'zh_CN',
            'en'                 => 'en_US',
            'en_us'              => 'en_US',
            'en-us'              => 'en_US',
            'en_gb'              => 'en_US',
            'en-gb'              => 'en_US',
            'en_au'              => 'en_US',
            'en-au'              => 'en_US',
            'en_ca'              => 'en_US',
            'en-ca'              => 'en_US',
            'en_nz'              => 'en_US',
            'en-nz'              => 'en_US',
            'en_ie'              => 'en_US',
            'en-ie'              => 'en_US',
            'en_sg'              => 'en_US',
            'en-sg'              => 'en_US',
            'en_hk'              => 'en_US',
            'en-hk'              => 'en_US',
            'us'                 => 'en_US',
            'uk'                 => 'en_US',
            'english'            => 'en_US',
        ];

        $locale = strtolower(trim($locale));
        if ($locale === '') {
            return null;
        }

        return $safeLocales[$locale] ?? null;
    }

    /**
     * Parse a vanilla-storage cookie record.
     * 
     * 解析 vanilla-storage cookie 记录。
     * 
     * @param string $cookie The cookie string to parse.
     * @return array|null The parsed cookie record, or null if the cookie is invalid.
     */
    public static function parseStorageCookie(string $cookie): ?array
    {
        $cookie = trim($cookie);

        if ($cookie === '') {
            return null;
        }

        $record = self::decodeStorageCookieRecord($cookie);

        if (!is_array($record)) {
            return null;
        }

        return $record;
    }

    public static function isDark(): bool
    {
        $cookie = $_COOKIE[SystemService::THEME_COOKIE] ?? '';
        $result = self::parseStorageCookie($cookie)['val'] ?? [];
        $v      = $result['render'] ?? 'light';
        return $v === 'dark';
    }

    /**
     * Set a vanilla-storage cookie record.
     * 
     * @param string   $name    Cookie name.
     * @param mixed    $value   Storage value.
     * @param int|null $ttl     Lifetime in seconds; null creates a session cookie with no storage expiration.
     * @param array    $options Optional: codec, path, secure, httponly, domain, sameSite/samesite.
     * @return bool             Whether the cookie header was accepted.
     */
    public static function setStorageCookie(string $name, mixed $value, ?int $ttl = null, array $options = []): bool
    {
        $name = trim($name);

        if ($name === '') {
            return false;
        }

        $codec = $options['codec'] ?? 'json';

        if (!is_string($codec) || !in_array($codec, ['json', 'raw-string'], true)) {
            return false;
        }

        if ($codec === 'raw-string' && !is_string($value)) {
            return false;
        }

        if ($ttl !== null && $ttl < 0) {
            return false;
        }

        $now       = time();
        $expiresAt = $ttl === null ? null : ($now + $ttl) * 1000;
        $cookie    = self::encodeStorageCookieRecord($value, $codec, $expiresAt);

        if ($cookie === null) {
            return false;
        }

        $cookieOptions = [
            'expires'  => $ttl === null ? 0 : $now + $ttl,
            'path'     => $options['path'] ?? ((defined('COOKIEPATH') && COOKIEPATH) ? COOKIEPATH : '/'),
            'secure'   => $options['secure'] ?? (function_exists('is_ssl') && is_ssl()),
            'httponly' => $options['httponly'] ?? false,
        ];

        $domain = $options['domain'] ?? (defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '');
        if ($domain !== '') {
            $cookieOptions['domain'] = $domain;
        }

        $sameSite = $options['sameSite'] ?? $options['samesite'] ?? 'Lax';
        if ($sameSite !== '') {
            $cookieOptions['samesite'] = self::normalizeStorageCookieSameSite($sameSite);
        }

        return setrawcookie(rawurlencode($name), rawurlencode($cookie), $cookieOptions);
    }

    private static function encodeStorageCookieRecord(mixed $value, string $codec, int|float|null $expiresAt): ?string
    {
        if ($expiresAt !== null && (!is_numeric($expiresAt) || !is_finite((float) $expiresAt))) {
            return null;
        }

        $record = [
            'v'   => 1,
            'c'   => $codec,
            'e'   => $expiresAt === null ? null : (int) $expiresAt,
            'val' => $value,
        ];

        $encoded = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? null : $encoded;
    }

    private static function decodeStorageCookieRecord(string $cookie): ?array
    {
        foreach (self::storageCookieCandidates($cookie) as $candidate) {
            $decoded = json_decode($candidate, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }

            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    continue;
                }
            }

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private static function normalizeStorageCookieSameSite(mixed $sameSite): string
    {
        return match (strtolower((string) $sameSite)) {
            'strict' => 'Strict',
            'none'   => 'None',
            default  => 'Lax',
        };
    }

    private static function storageCookieCandidates(string $cookie): array
    {
        $values = [trim($cookie)];

        for ($i = 0; $i < 2; $i++) {
            foreach ($values as $value) {
                $values[] = rawurldecode($value);
                $values[] = urldecode($value);
                $values[] = stripslashes($value);
                $values[] = stripcslashes($value);
            }

            $values = array_values(array_unique(array_filter(array_map('trim', $values), static fn($value) => $value !== '')));
        }

        foreach ($values as $value) {
            $value = trim($value);
            if (
                strlen($value) >= 2 &&
                (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))
            ) {
                $values[] = substr($value, 1, -1);
                $values[] = stripcslashes(substr($value, 1, -1));
            }
        }

        return array_values(array_unique(array_filter(array_map('trim', $values), static fn($value) => $value !== '')));
    }

    /**
     * Check if theme mode is available
     *
     * 检查主题模式是否可用
     *
     * @return bool
     */
    public static function themeModeAvailable(): bool
    {
        return !(defined('WP_USE_THEMES') && WP_USE_THEMES === false);
    }
}
