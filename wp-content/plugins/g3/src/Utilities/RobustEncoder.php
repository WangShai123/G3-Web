<?php
namespace JEALER\G3\Utilities;

/**
 * Robust Encoder - Optimized Version
 * 
 * 鲁棒编码器，依赖 utf8mb4 字符集数据库
 * 优化版本，提升性能并减少内存使用
 * 
 * @package G3
 * @since 1.0.0
 */
class RobustEncoder {

    /**
     * Zero Width Characters
     * 
     * 零宽 Unicode 字符集（用于二进制映射）
     * 使用4个字符来避免映射冲突
     *
     * @var array<int, string>
     */
    private const ZERO_WIDTH_CHARS = [
        "\u{200B}", // ZWSP - Zero Width Space
        "\u{200C}", // ZWNJ - Zero Width Non-Joiner  
        "\u{200D}", // ZWJ - Zero Width Joiner
        "\u{FEFF}", // ZWNBSP - Zero Width No-Break Space
    ];

    /**
     * Marker characters - different from encoding characters
     * 标记字符 - 与编码字符不同
     * 
     * @var array<int, string>
     */
    private const MARKER_CHARS = [
        "\u{2060}", // Word Joiner
        "\u{2061}", // Function Application  
        "\u{2062}", // Invisible Times
    ];

    /**
     * Unique marker for ghost blocks
     * 
     * @var string
     */
    private static string $marker;

    /**
     * Binary to zero-width character mapping
     * 
     * @var array<string, string>
     */
    private static array $binaryToChar;

    /**
     * Zero-width character to binary mapping
     * 
     * @var array<string, string>
     */
    private static array $charToBinary;

    /**
     * Initialize static mappings
     * 
     * @return void
     */
    private static function initMappings(): void
    {
        if (!isset(self::$marker)) {
            // Use separate marker characters that don't conflict with encoding
            self::$marker = self::MARKER_CHARS[0] . self::MARKER_CHARS[1] . self::MARKER_CHARS[2];

            // Simple 1:1 mapping using 4 characters for 2-bit combinations
            self::$binaryToChar = [
                '00' => self::ZERO_WIDTH_CHARS[0], // ZWSP
                '01' => self::ZERO_WIDTH_CHARS[1], // ZWNJ  
                '10' => self::ZERO_WIDTH_CHARS[2], // ZWJ
                '11' => self::ZERO_WIDTH_CHARS[3], // ZWNBSP
            ];

            self::$charToBinary = [
                self::ZERO_WIDTH_CHARS[0] => '00', // ZWSP -> 00
                self::ZERO_WIDTH_CHARS[1] => '01', // ZWNJ -> 01
                self::ZERO_WIDTH_CHARS[2] => '10', // ZWJ -> 10
                self::ZERO_WIDTH_CHARS[3] => '11', // ZWNBSP -> 11
            ];
        }
    }

    /**
     * Get Unique Marker, Ghost Marker
     * 
     * 获取唯一标记，用于识别幽灵块
     * 
     * @return string
     * @since 1.0.0
     * @author Wang Shai
     */
    private static function getMarker(): string
    {
        self::initMappings();
        return self::$marker;
    }

    /**
     * Encode Ghost String - Optimized Version
     * 
     * 将数据编码为带标记的幽灵字符串（优化版本）
     * 
     * @param string $data
     * @return string
     */
    public static function encodePayload(string $data): string
    {
        self::initMappings();

        // 使用更高效的base64编码
        $base64 = base64_encode($data);
        $length = strlen($base64);

        // 预分配字符串缓冲区
        $ghost        = '';
        $ghost_length = 0;

        // 批量处理字符，减少函数调用
        for ($i = 0; $i < $length; $i++) {
            $char  = $base64[$i];
            $ascii = ord($char);

            // 直接转换为8位二进制，避免str_pad
            $binary = sprintf('%08b', $ascii);

            // 每2位转换为零宽字符
            for ($j = 0; $j < 8; $j += 2) {
                $pair   = $binary[$j] . $binary[$j + 1];
                $ghost .= self::$binaryToChar[$pair];
            }
        }

        return self::$marker . $ghost . self::$marker;
    }

    /**
     * Get the payload from a string - Optimized Version
     * 
     * 从字符串中提取第一个有效 payload（优化版本）
     * 
     * @param string $content
     * @return string|null
     */
    public static function decodePayload(string $content): ?string
    {
        self::initMappings();

        $marker     = self::$marker;
        $marker_len = mb_strlen($marker, 'UTF-8');

        // 使用mb_strpos进行更高效的搜索
        $start_pos = mb_strpos($content, $marker, 0, 'UTF-8');
        if ($start_pos === false) {
            return null;
        }

        $end_pos = mb_strpos($content, $marker, $start_pos + $marker_len, 'UTF-8');
        if ($end_pos === false) {
            return null;
        }

        // 提取幽灵字符串
        $ghost_start  = $start_pos + $marker_len;
        $ghost_length = $end_pos - $ghost_start;
        $ghost        = mb_substr($content, $ghost_start, $ghost_length, 'UTF-8');

        if (empty($ghost)) {
            return null;
        }

        // 转换幽灵字符为二进制 - Simple 1:1 mapping
        $binary      = '';
        $ghost_chars = preg_split('//u', $ghost, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($ghost_chars as $char) {
            if (isset(self::$charToBinary[$char])) {
                $binary .= self::$charToBinary[$char];
            }
        }

        // 确保二进制长度是8的倍数
        $binary_length = strlen($binary);
        $padded_length = intval($binary_length / 8) * 8;
        if ($padded_length === 0) {
            return null;
        }

        $binary = substr($binary, 0, $padded_length);

        // 批量转换二进制为字符
        $base64 = '';
        for ($i = 0; $i < $padded_length; $i += 8) {
            $byte    = substr($binary, $i, 8);
            $base64 .= chr(bindec($byte));
        }

        // 解码base64
        $decoded = base64_decode($base64, true);
        return $decoded !== false ? $decoded : null;
    }

    /**
     * Remove Ghost Blocks - Optimized Version
     * 
     * 清除所有幽灵块（优化版本）
     * 
     * @param string $content
     * @return string
     */
    public static function removeAllGhostBlocks(string $content): string
    {
        if (empty($content)) {
            return $content;
        }

        $marker     = self::getMarker();
        $marker_len = mb_strlen($marker, 'UTF-8');

        // 使用循环替代正则表达式，提升性能
        $result = $content;
        $offset = 0;

        while (($start_pos = mb_strpos($result, $marker, $offset, 'UTF-8')) !== false) {
            $end_pos = mb_strpos($result, $marker, $start_pos + $marker_len, 'UTF-8');

            if ($end_pos === false) {
                // 没有找到结束标记，跳过这个开始标记
                $offset = $start_pos + $marker_len;
                continue;
            }

            // 移除整个幽灵块（包括标记）
            $block_length = $end_pos + $marker_len - $start_pos;
            $result       = mb_substr($result, 0, $start_pos, 'UTF-8') .
                mb_substr($result, $start_pos + $block_length, null, 'UTF-8');

            // 重置偏移量，因为字符串已经改变
            $offset = $start_pos;
        }

        return $result;
    }

    /**
     * Find insertion points by splitting - Enhanced Version
     * 
     * 智能分割内容，返回分散的可插入位置（增强版本）
     * 确保不会破坏HTML标签，并提供更好的分散效果
     * 
     * @param  string $content
     * @param  int $max_points
     * @return array<int>
     */
    public static function findInsertionPoints(string $content, int $max_points = 5): array
    {
        if (empty($content) || $max_points <= 0) {
            return [0];
        }

        $content_length = mb_strlen($content, 'UTF-8');
        if ($content_length === 0) {
            return [0];
        }

        $positions = []; // 不再默认包含开头

        // 检查是否包含HTML内容
        if (self::containsHtml($content)) {
            // HTML模式：查找安全的HTML插入点
            $html_positions = self::findHtmlInsertionPoints($content, $max_points);
            $positions      = array_merge($positions, $html_positions);
        } else {
            // 纯文本模式：按段落和句子分割
            $text_positions = self::findTextInsertionPoints($content, $max_points);
            $positions      = array_merge($positions, $text_positions);
        }

        // 如果没有找到足够的插入点，添加基于长度的分散位置
        if (count($positions) < $max_points && $content_length > 100) {
            $additional_positions = self::findLengthBasedPositions($content, $max_points - count($positions));
            $positions            = array_merge($positions, $additional_positions);
        }

        // 确保包含开头和结尾位置
        if (!in_array(0, $positions)) {
            array_unshift($positions, 0);
        }
        if (!in_array($content_length, $positions) && count($positions) < $max_points) {
            $positions[] = $content_length;
        }

        // 去重、排序并限制数量
        $positions = array_unique($positions);
        sort($positions);

        return array_slice($positions, 0, $max_points);
    }

    /**
     * Check if content contains HTML tags
     * 检查内容是否包含HTML标签
     * 
     * @param string $content
     * @return bool
     */
    private static function containsHtml(string $content): bool
    {
        // 检查常见的HTML标签
        return preg_match('/<[a-zA-Z][^>]*>/', $content) === 1;
    }

    /**
     * Find safe insertion points in HTML content
     * 在HTML内容中查找安全的插入点
     * 
     * @param string $content
     * @param int $max_points
     * @return array<int>
     */
    private static function findHtmlInsertionPoints(string $content, int $max_points): array
    {
        $positions = [];

        // 查找段落结束标签后的安全位置
        $safe_tags       = ['</p>', '</div>', '</section>', '</article>', '</h1>', '</h2>', '</h3>', '</h4>', '</h5>', '</h6>'];
        $found_positions = [];

        foreach ($safe_tags as $tag) {
            $offset = 0;
            while (($pos = mb_strpos($content, $tag, $offset, 'UTF-8')) !== false) {
                $safe_pos = $pos + mb_strlen($tag, 'UTF-8');

                // 确保插入位置不在其他HTML标签内部
                if (self::isSafeInsertionPoint($content, $safe_pos)) {
                    $found_positions[] = $safe_pos;
                }

                $offset = $safe_pos;
            }
        }

        // 去重并排序
        $found_positions = array_unique($found_positions);
        sort($found_positions);

        // 选择合适的插入点
        $count = count($found_positions);
        if ($count > 0) {
            if ($max_points >= 3 && $count >= 2) {
                // 选择开头、中间和其他位置
                $positions[] = $found_positions[0]; // 第一个安全位置

                if ($count >= 3) {
                    $mid_index   = intval($count / 2);
                    $positions[] = $found_positions[$mid_index]; // 中间位置

                    if ($max_points > 3 && $count > 4) {
                        $quarter_index = intval($count / 4);
                        if ($quarter_index !== 0 && $quarter_index !== $mid_index) {
                            $positions[] = $found_positions[$quarter_index];
                        }
                    }
                }
            } else if ($count >= 1) {
                $positions[] = $found_positions[0];
            }
        }

        return $positions;
    }

    /**
     * Find insertion points in plain text content
     * 在纯文本内容中查找插入点
     * 
     * @param string $content
     * @param int $max_points
     * @return array<int>
     */
    private static function findTextInsertionPoints(string $content, int $max_points): array
    {
        $positions = [];

        // 按双换行分割段落
        $paragraphs = preg_split('/\n\s*\n/', $content);
        if (count($paragraphs) <= 1) {
            return $positions;
        }

        $current_pos = 0;
        $para_count  = count($paragraphs);

        // 选择段落之间的位置
        for ($i = 1; $i < $para_count && count($positions) < $max_points - 1; $i++) {
            // 使用mb_strlen确保UTF-8安全
            $current_pos += mb_strlen($paragraphs[$i - 1], 'UTF-8');

            // 添加段落分隔符的长度（通常是\n\n）
            $separator_match = [];
            if (preg_match('/\n\s*\n/', $content, $separator_match, PREG_OFFSET_CAPTURE, $current_pos)) {
                $separator_length  = mb_strlen($separator_match[0][0], 'UTF-8');
                $current_pos      += $separator_length;
            } else {
                $current_pos += 2; // 默认双换行长度
            }

            // 选择合适的段落边界
            if ($i === 1 || $i === intval($para_count / 2) || ($i % 3 === 0 && count($positions) < $max_points - 1)) {
                $positions[] = $current_pos;
            }
        }

        return $positions;
    }

    /**
     * Find length-based insertion positions
     * 基于内容长度查找分散的插入位置
     * 
     * @param string $content
     * @param int $needed_points
     * @return array<int>
     */
    private static function findLengthBasedPositions(string $content, int $needed_points): array
    {
        $positions      = [];
        $content_length = mb_strlen($content, 'UTF-8');

        if ($needed_points <= 0 || $content_length < 50) {
            return $positions;
        }

        // 计算分散的位置
        $segment_size = intval($content_length / ($needed_points + 1));

        for ($i = 1; $i <= $needed_points; $i++) {
            $base_pos = $i * $segment_size;

            // 在HTML内容中，寻找附近的安全位置
            if (self::containsHtml($content)) {
                $safe_pos = self::findNearestSafePosition($content, $base_pos);
                if ($safe_pos !== false) {
                    $positions[] = $safe_pos;
                }
            } else {
                // 在纯文本中，寻找句子或段落边界
                $safe_pos    = self::findNearestTextBoundary($content, $base_pos);
                $positions[] = $safe_pos;
            }
        }

        return $positions;
    }

    /**
     * Find nearest safe position for HTML content
     * 在HTML内容中查找最近的安全位置
     * 
     * @param string $content
     * @param int $target_pos
     * @return int|false
     */
    private static function findNearestSafePosition(string $content, int $target_pos)
    {
        $content_length = mb_strlen($content, 'UTF-8');
        $search_range   = 50; // 搜索范围

        // 向前和向后搜索安全位置
        for ($offset = 0; $offset <= $search_range; $offset++) {
            // 向后搜索
            $pos_forward = $target_pos + $offset;
            if ($pos_forward < $content_length && self::isSafeInsertionPoint($content, $pos_forward)) {
                return $pos_forward;
            }

            // 向前搜索
            $pos_backward = $target_pos - $offset;
            if ($pos_backward > 0 && self::isSafeInsertionPoint($content, $pos_backward)) {
                return $pos_backward;
            }
        }

        return false;
    }

    /**
     * Find nearest text boundary (sentence or paragraph end)
     * 在纯文本中查找最近的文本边界
     * 
     * @param string $content
     * @param int $target_pos
     * @return int
     */
    private static function findNearestTextBoundary(string $content, int $target_pos): int
    {
        $content_length = mb_strlen($content, 'UTF-8');
        $search_range   = 30;

        // 寻找句号、问号、感叹号后的位置
        $sentence_endings = ['. ', '。', '? ', '？', '! ', '！', "\n"];

        for ($offset = 0; $offset <= $search_range; $offset++) {
            // 向后搜索
            $pos_forward = $target_pos + $offset;
            if ($pos_forward < $content_length - 1) {
                $char      = mb_substr($content, $pos_forward, 1, 'UTF-8');
                $next_char = mb_substr($content, $pos_forward + 1, 1, 'UTF-8');

                if (in_array($char . $next_char, $sentence_endings) || in_array($char, $sentence_endings)) {
                    return $pos_forward + 1;
                }
            }

            // 向前搜索
            $pos_backward = $target_pos - $offset;
            if ($pos_backward > 0) {
                $char      = mb_substr($content, $pos_backward, 1, 'UTF-8');
                $next_char = mb_substr($content, $pos_backward + 1, 1, 'UTF-8');

                if (in_array($char . $next_char, $sentence_endings) || in_array($char, $sentence_endings)) {
                    return $pos_backward + 1;
                }
            }
        }

        // 如果找不到合适的边界，返回目标位置
        return min($target_pos, $content_length);
    }

    /**
     * Check if a position is safe for insertion (not inside HTML tags)
     * 检查位置是否安全插入（不在HTML标签内部）
     * 
     * @param string $content
     * @param int $position
     * @return bool
     */
    private static function isSafeInsertionPoint(string $content, int $position): bool
    {
        if ($position <= 0 || $position >= mb_strlen($content, 'UTF-8')) {
            return false;
        }

        // 检查前后几个字符，确保不在标签内部
        $check_range = 10; // 检查前后10个字符
        $start       = max(0, $position - $check_range);
        $length      = min($check_range * 2, mb_strlen($content, 'UTF-8') - $start);

        $surrounding  = mb_substr($content, $start, $length, 'UTF-8');
        $relative_pos = $position - $start;

        // 检查是否在标签内部
        $before = mb_substr($surrounding, 0, $relative_pos, 'UTF-8');
        $after  = mb_substr($surrounding, $relative_pos, null, 'UTF-8');

        // 如果前面有未闭合的<，或者后面紧跟>，说明在标签内部
        $last_open  = mb_strrpos($before, '<', 0, 'UTF-8');
        $last_close = mb_strrpos($before, '>', 0, 'UTF-8');

        if ($last_open !== false && ($last_close === false || $last_open > $last_close)) {
            // 前面有未闭合的<标签
            return false;
        }

        // 检查后面是否紧跟>
        if (mb_substr($after, 0, 1, 'UTF-8') === '>') {
            return false;
        }

        // 检查是否在特殊标签内部（如script, style等）
        if (self::isInsideSpecialTag($content, $position)) {
            return false;
        }

        return true;
    }

    /**
     * Check if position is inside special tags (script, style, etc.)
     * 检查位置是否在特殊标签内部
     * 
     * @param string $content
     * @param int $position
     * @return bool
     */
    private static function isInsideSpecialTag(string $content, int $position): bool
    {
        $special_tags = ['script', 'style', 'pre', 'code'];

        foreach ($special_tags as $tag) {
            $open_tag  = "<{$tag}";
            $close_tag = "</{$tag}>";

            // 查找最近的开始和结束标签
            $content_before = mb_substr($content, 0, $position, 'UTF-8');
            $last_open      = mb_strrpos($content_before, $open_tag, 0, 'UTF-8');

            if ($last_open !== false) {
                $content_after_open = mb_substr($content, $last_open, null, 'UTF-8');
                $close_pos          = mb_strpos($content_after_open, $close_tag, 0, 'UTF-8');

                if ($close_pos === false || $last_open + $close_pos > $position) {
                    // 在特殊标签内部
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Insert Payload - Enhanced Version
     * 
     * 将 payload 分散嵌入到内容的多个安全位置（增强版本）
     * 真正实现分散插入，提升版权保护效果
     * 
     * @param string $content
     * @param string $payload
     * @return string
     */
    public static function embedPayloadIntoContent(string $content, string $payload): string
    {
        if (empty($content) || empty($payload)) {
            return $content;
        }

        $ghost_block = self::encodePayload($payload);
        $positions   = self::findInsertionPoints($content, 5); // 增加到5个插入点

        if (empty($positions)) {
            return $content;
        }

        // 选择多个分散的插入点（而不是只选择第一个）
        $selected_positions = [];
        $total_positions    = count($positions);

        if ($total_positions >= 3) {
            // 选择开头、中间、结尾的位置
            $selected_positions[] = $positions[0]; // 开头
            $selected_positions[] = $positions[intval($total_positions / 2)]; // 中间
            $selected_positions[] = $positions[$total_positions - 1]; // 结尾
        } elseif ($total_positions >= 2) {
            // 选择开头和结尾
            $selected_positions[] = $positions[0];
            $selected_positions[] = $positions[$total_positions - 1];
        } else {
            // 只有一个位置
            $selected_positions[] = $positions[0];
        }

        // 从后往前插入，避免位置偏移
        rsort($selected_positions);

        // 使用mb_substr进行UTF-8安全的字符串操作
        $result = $content;
        foreach ($selected_positions as $pos) {
            if ($pos >= 0 && $pos <= mb_strlen($result, 'UTF-8')) {
                $before = mb_substr($result, 0, $pos, 'UTF-8');
                $after  = mb_substr($result, $pos, null, 'UTF-8');
                $result = $before . $ghost_block . $after;
            }
        }

        return $result;
    }

    /**
     * 提取信息 - Optimized Version
     * 
     * @param string $content
     * @return array|null
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function extract(string $content): ?array
    {
        if (empty($content)) {
            return null;
        }

        $data = self::decodePayload($content);
        if ($data === null) {
            return null;
        }

        // 使用更安全的JSON解码
        try {
            $json = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

            if (
                is_array($json) &&
                (isset($json['siteName']) || isset($json['sitename'])) &&
                (isset($json['siteUrl']) || isset($json['siteurl']))
            ) {
                return $json;
            }
        }
        catch (\JsonException $e) {
            // JSON解码失败，返回null
            return null;
        }

        return null;
    }

    /**
     * Batch process multiple contents - New Method
     * 
     * 批量处理多个内容，提升性能
     * 
     * @param array<string> $contents
     * @param string $payload
     * @return array<string>
     */
    public static function batchEmbedPayload(array $contents, string $payload): array
    {
        if (empty($contents) || empty($payload)) {
            return $contents;
        }

        // 预编码payload，避免重复编码
        $ghost_block = self::encodePayload($payload);

        $results = [];
        foreach ($contents as $key => $content) {
            if (empty($content)) {
                $results[$key] = $content;
                continue;
            }

            // 清除旧的幽灵块
            $clean_content = self::removeAllGhostBlocks($content);

            // 嵌入新的payload
            $positions = self::findInsertionPoints($clean_content, 3);

            if (empty($positions)) {
                $results[$key] = $clean_content;
                continue;
            }

            // 从后往前插入
            rsort($positions);
            $result = $clean_content;

            foreach ($positions as $pos) {
                if ($pos >= 0 && $pos <= mb_strlen($result, 'UTF-8')) {
                    $before = mb_substr($result, 0, $pos, 'UTF-8');
                    $after  = mb_substr($result, $pos, null, 'UTF-8');
                    $result = $before . $ghost_block . $after;
                }
            }

            $results[$key] = $result;
        }

        return $results;
    }

    /**
     * Check if content has ghost blocks - New Method
     * 
     * 检查内容是否包含幽灵块
     * 
     * @param string $content
     * @return bool
     */
    public static function hasGhostBlocks(string $content): bool
    {
        if (empty($content)) {
            return false;
        }

        $marker = self::getMarker();
        return mb_strpos($content, $marker, 0, 'UTF-8') !== false;
    }

    /**
     * Get ghost block count - New Method
     * 
     * 获取幽灵块数量
     * 
     * @param string $content
     * @return int
     */
    public static function getGhostBlockCount(string $content): int
    {
        if (empty($content)) {
            return 0;
        }

        $marker     = self::getMarker();
        $marker_len = mb_strlen($marker, 'UTF-8');
        $count      = 0;
        $offset     = 0;

        while (($start_pos = mb_strpos($content, $marker, $offset, 'UTF-8')) !== false) {
            $end_pos = mb_strpos($content, $marker, $start_pos + $marker_len, 'UTF-8');

            if ($end_pos !== false) {
                $count++;
                $offset = $end_pos + $marker_len;
            } else {
                break;
            }
        }

        return $count;
    }
}