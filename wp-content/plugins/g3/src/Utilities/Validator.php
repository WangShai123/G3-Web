<?php
namespace JEALER\G3\Utilities;

final class Validator {

    /**
     * Detect the language based on the environment variable LANG.
     * 
     * 检测语言基于环境变量 LANG。
     *
     * @return string 'zh' or 'en'
     * @since 1.0.0
     * @author Wang Shai
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
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function isImage(string $pathOrUrl): bool
    {
        // if URL, getimagesizefromstring
        if (filter_var($pathOrUrl, FILTER_VALIDATE_URL)) {
            $headers = @get_headers($pathOrUrl, 1);

            // check if URL is valid
            if (!$headers || strpos($headers[0], '200') === false) {
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
     * Check if the string is a valid URL.
     * 
     * 检查字符串是否为有效的 URL。
     * 
     *
     * @param string $url The URL to check.
     * @return bool True if the string is a valid URL, false otherwise.
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function isURL(string $url): bool
    {
        $url = trim($url);

        if (!wp_http_validate_url($url)) {
            return false;
        }

        return true;
    }
}