<?php
namespace JEALER\G3\Utilities;

final class Type {

    /**
     * Convert array to JSON string
     *
     * 将数组转换为 JSON 字符串
     *
     * @param array $array
     * @return string
     */
    public static function arrayToJson(array $array): string
    {
        $result = json_encode($array, JSON_UNESCAPED_UNICODE);

        if ($result === false) {
            return '{}';
        }

        return $result;
    }

    /**
     * Convert JSON string to array
     *
     * 将 JSON 字符串转换为数组
     *
     * @param string $json
     * @return array
     */
    public static function jsonToArray(string $json): array
    {
        if ($json === '') {
            return [];
        }

        $result = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return $result;
    }

    /**
     * Exclude array keys from unset
     * 
     * 从数组中移除指定的键值对
     * 
     * @param  array $array
     * @param  array $unset
     * @return array
     */
    public static function arrayExcept(array $array, ?array $unset = null): array
    {
        return match (true) {
            $unset === null || $unset === [] => $array,
            default                          => array_diff_key($array, array_flip($unset))
        };
    }

    /**
     * Truncate string with ellipsis
     *
     * 截断字符串并添加省略号
     *
     * @param string $string
     * @param int $length
     * @param string $ellipsis
     * @return string
     */
    public static function truncate($string, $length = 50, $ellipsis = '...'): string
    {
        // Use multibyte functions for proper handling of UTF-8 characters
        return mb_strlen($string) > $length
            ? mb_substr($string, 0, $length) . $ellipsis
            : $string;
    }

    /**
     * Truncate HTML string with ellipsis
     *
     * 截断 HTML 字符串并添加省略号
     *
     * @param string $html
     * @param int $length
     * @param string $ellipsis
     * @return string
     */
    public static function truncateHtml(string $html, int $length = 80, string $ellipsis = '…'): string
    {
        if (mb_strlen(strip_tags($html)) <= $length) {
            return $html;
        }
        // Use wp_html_excerpt to safely truncate (will not cut off in the middle of a tag)
        $truncated = wp_html_excerpt($html, $length, '');
        // Auto close unclosed tags
        $truncated = force_balance_tags($truncated);

        return $truncated . $ellipsis;
    }

    /**
     * Truncate content with ellipsis, strips all tags
     *
     * 截断内容并添加省略号，同时移除所有 HTML 标签
     *
     * @param  string $content
     * @param  int $maxLength
     * @param  string $ellipsis
     * @return string
     */
    public static function stripContent(string $content, int $maxLength = 150, string $ellipsis = '…'): string
    {
        $content = wp_strip_all_tags($content);
        return mb_strimwidth($content, 0, $maxLength, $ellipsis);
    }
}
