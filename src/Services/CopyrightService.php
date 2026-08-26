<?php
namespace JEALER\G3\Services;
use JEALER\G3\Core\Service\Service;
use WP_Post;

class CopyrightService extends Service {
    const CACHE_GROUP = 'g3_copyright';
    private const MAPS        = [
        "\u{200B}",
        "\u{200C}",
        "\u{200D}",
        "\u{FEFF}",
    ];

    private const CLEAN_PATTERN = '/[\x{200B}-\x{200D}\x{FEFF}\x{2060}\x{180E}\x{00AD}\x{2061}-\x{2069}\x{202A}-\x{202E}\x{206A}-\x{206F}\x{061C}\x{00A0}\x{2000}-\x{2003}]/u';

    private array $chars;
    public function __construct()
    {
        parent::__construct();
        $this->init();
    }

    public function init(): void
    {
        $this->chars = self::MAPS;
    }

    public function clean(string $text): string
    {
        return preg_replace(self::CLEAN_PATTERN, '', $text) ?? $text;
    }

    public function embed(string $text, string $payload, int $position): string
    {
        $cleanText = $this->clean($text);
        if (empty($payload)) return $cleanText;

        // 直接获取 UTF-8 字节流，并转为二进制字符串
        $bytes     = unpack('C*', $payload); // 获取每个字节的十进制值
        $binaryStr = '';
        foreach ($bytes as $byte) {
            $binaryStr .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        // 按 2 位一组切割，并映射为真正零宽字符
        $invisibleStr = '';
        $chunks       = str_split($binaryStr, 2);
        foreach ($chunks as $chunk) {
            $chunk         = str_pad($chunk, 2, '0', STR_PAD_RIGHT);
            $index         = bindec($chunk); // 0 ~ 3
            $invisibleStr .= $this->chars[$index];
        }

        $length   = mb_strlen($cleanText, 'UTF-8');
        $position = max(0, min($position, $length));

        return mb_substr($cleanText, 0, $position, 'UTF-8') .
            $invisibleStr .
            mb_substr($cleanText, $position, null, 'UTF-8');
    }

    public function extract(string $text): string
    {
        // 1. 仅提取当前水印编码使用的 4 种零宽字符
        preg_match_all('/[\x{200B}-\x{200D}\x{FEFF}]/u', $text, $matches);
        if (empty($matches[0])) return '';

        // 2. 反向映射为 2-bit 二进制流
        $reverseMap = array_flip($this->chars);
        $binaryStr  = '';
        foreach ($matches[0] as $char) {
            $binaryStr .= str_pad(decbin($reverseMap[$char]), 2, '0', STR_PAD_LEFT);
        }

        // 3. 按 8 位一组还原为字节，再拼接为 UTF-8 字符串
        $bytes     = [];
        $binaryStr = substr($binaryStr, 0, intval(strlen($binaryStr) / 8) * 8);
        foreach (str_split($binaryStr, 8) as $byte) {
            $bytes[] = bindec($byte);
        }

        // 使用 pack 将字节数组还原为原始字符串（完美兼容中文）
        return pack('C*', ...$bytes);
    }

    public function decrypt(int $id): string
    {
        $result = wp_cache_get($id, self::CACHE_GROUP);
        if (false === $result) {
            $post = get_post($id);
            if ($post instanceof WP_Post) {
                $result = $this->extract($post->post_content);
                wp_cache_set($id, $result, self::CACHE_GROUP, DAY_IN_SECONDS);
            } else {
                $result = 'Copyright info not found.';
            }
        }
        return $result;
    }
}
