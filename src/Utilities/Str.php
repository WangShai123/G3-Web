<?php
namespace JEALER\G3\Utilities;

final class Str {
    /**
     * Easy inside translate
     *
     * 简单翻译
     *
     * @param string $key translation key
     * @param string $lang language code 'zh' or 'en'
     * @param array $messages translation messages array
     *  Example:
     *   $messages = [
     *     'en' => [
     *       'hello' => 'hello',
     *     ],
     *     'zh' => [
     *       'hello' => '你好',
     *     ]
     *   ];
     *
     * @return string translated string
     */
    public static function t(string $key, string $lang, array $messages): string
    {
        return $messages[$lang][$key] ?? $key;
    }

    /**
     * Generate a random string, support max length 32 bits
     *
     * 生成随机字符串，支持最大长度 32 位
     *
     * @param int $length string length
     * @return string random string
     */
    public static function hash(int $length = 8): string
    {
        $hash = hash('sha256', uniqid('G3', true));

        return $length > 32
            ? substr($hash, 0, 32)
            : substr($hash, 0, $length);
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