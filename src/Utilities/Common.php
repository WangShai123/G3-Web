<?php
namespace JEALER\G3\Utilities;

final class Common {

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
}
