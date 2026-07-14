<?php
namespace JEALER\G3\Utilities;

final class Validator {

    /**
     * Sanitize output to prevent XSS.
     * 
     * 防止 XSS 攻击。
     *
     * @param string $data The data to sanitize.
     * @param string $context The context of the data. Options: html, attr, post.
     * @return string The sanitized data.
     */
    public static function output($data, $context = 'html'): string
    {
        $result = match ($context) {
            'attr'  => esc_attr($data),
            'post'  => wp_kses_post($data),
            default => esc_html($data),
        };
        return $result;
    }

    /**
     * Sanitize input to prevent XSS.
     * 
     * 防止 XSS 攻击。
     *
     * @param string $input The input to sanitize.
     * @return string The sanitized input.
     */
    public static function input($input): string
    {
        return sanitize_text_field($input);
    }

    /**
     * Sanitize textarea input to prevent XSS.
     * 
     * 防止 XSS 攻击。
     *
     * @param string $input The input to sanitize.
     * @return string The sanitized input.
     */
    public static function textarea($input): string
    {
        return sanitize_textarea_field($input);
    }

    /**
     * Detect the language based on the environment variable LANG.
     * 
     * 检测语言基于环境变量 LANG。
     *
     * @return string 'zh' or 'en'
     */
    public static function detectLang(): string
    {
        $locale = getenv('LANG');
        if ($locale && stripos($locale, 'zh') !== false) {
            return 'zh';
        } else {
            return 'en';
        }
    }

    /**
     * Check if the file is an image.
     * 
     * 检查文件是否为图片。
     *
     * @param string $pathOrUrl The path or URL to the file.
     * @return bool True if the file is an image, false otherwise.
     */
    public static function isImage(string $pathOrUrl): bool
    {
        // if URL, getimagesizefromstring
        if (filter_var($pathOrUrl, FILTER_VALIDATE_URL)) {
            $headers = @get_headers($pathOrUrl, 1);

            // check if URL is valid
            // if (!$headers || strpos($headers[0], '200') === false) {
            //     return false;
            // }
            if (!self::isURL($pathOrUrl)) {
                return false;
            }

            // check if Content-Type is image
            if (!isset($headers['Content-Type']) || strpos($headers['Content-Type'], 'image') !== 0) {
                return false;
            }

            // download the file content, check if it is an image
            $imgData = @file_get_contents($pathOrUrl, false, stream_context_create([
                "http" => ["method" => "GET", "timeout" => 3]
            ]));

            return $imgData !== false && @getimagesizefromstring($imgData) !== false;
        }

        // if file path, getimagesize
        if (!file_exists($pathOrUrl) || !is_file($pathOrUrl)) {
            return false;
        }

        return @getimagesize($pathOrUrl) !== false;
    }

    /**
     * Check if the given data is a WebP image by examining its file signature.
     * 
     * 通过检查文件签名判断是否为 WebP 图像。
     *
     * @param string $data The binary data of the file.
     * @return bool True if the data is a WebP image, false otherwise.
     */
    public static function isWebP(string $data): bool
    {
        // WebP 文件头前 12 字节：
        // RIFF (4 bytes) + 文件大小 (4 bytes) + WEBP (4 bytes)
        return substr($data, 0, 4) === 'RIFF' && substr($data, 8, 4) === 'WEBP';
    }

    /**
     * Check if the string is a valid URL.
     * 
     * 检查字符串是否为有效的 URL。
     *
     * @param string $url The URL to check.
     * @return bool True if the string is a valid URL, false otherwise.
     */
    public static function isURL(string $url): bool
    {
        $url = trim($url);
        return (bool) wp_http_validate_url($url);
    }

    /**
     * Check if the string is a valid UUIDv4.
     * 
     * 检查字符串是否为有效的 UUIDv4。
     *
     * @param string $uuid The UUID to check.
     * @return bool True if the string is a valid UUIDv4, false otherwise.
     */
    public static function isUUIDv4(string $uuid)
    {
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $uuid);
    }

    /**
     * Check if the string is a valid redirect URL.
     * 
     * 检查字符串是否为有效的跳转 URL。
     *
     * @param string $url The URL to check.
     * @return bool True if the string is a valid redirect URL, false otherwise.
     */
    public static function safeRedirectUrl(string $url): bool
    {
        $parsed = wp_parse_url($url);
        if (!$parsed || !isset($parsed['host'], $parsed['scheme'])) {
            return false;
        }

        // only allow http or https
        $allowedSchemes = [
            'http',
            'https'
        ];
        if (!in_array(strtolower($parsed['scheme']), $allowedSchemes, true)) {
            return false;
        }

        // DO NOT ALLOW INTERNAL REDIRECTS
        $siteHost = parse_url(home_url(), PHP_URL_HOST);
        if (isset($parsed['host']) && strtolower($parsed['host']) === strtolower($siteHost)) {
            return false;
        }

        // Manual SSRF defense, more flexible than wp_http_validate_url
        $host = $parsed['host'];
        // if IP, deny
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        // Blacklist
        $blackList = [
            'localhost',
            'localhost.localdomain',
            'ip6-localhost'
        ];
        if (in_array(strtolower($host), $blackList, true)) {
            return false;
        }

        return true;
    }

    /**
     * Check if the current screen matches the given screen ID.
     * 
     * 检查当前屏幕是否匹配给定的屏幕 ID。
     *
     * @param string $id The screen ID to check.
     * @return bool True if the current screen matches the given ID, false otherwise.
     */
    public static function screen(string $id): bool
    {
        $screen = get_current_screen();
        return $screen->id === $id;
    }
}
