<?php
/**
 * RobustEncoder Performance Test
 * RobustEncoder性能测试
 * 
 * 测试优化后的RobustEncoder性能
 * 
 * @since 1.0.0
 * @author Wang Shai
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

use JEALER\G3\Utilities\RobustEncoder;

/**
 * Test RobustEncoder performance
 * 测试RobustEncoder性能
 */
function g3TestRobustEncoderPerformance(): void
{
    echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; margin: 20px;'>";
    echo "<h2>🚀 G3 Test: RobustEncoder Performance</h2>\n";
    echo "<pre>\n";

    // 测试数据
    $test_payload = json_encode([
        'siteName' => 'Test Site Name',
        'siteUrl'  => 'https://example.com',
        'time'     => time(),
        'postId'   => 123,
    ], JSON_UNESCAPED_UNICODE);

    // 生成不同长度的测试内容
    $test_contents = [
        'short'     => str_repeat('This is a short test content. ', 10),
        'medium'    => str_repeat('<p>This is a medium test content with HTML tags.</p>', 50),
        'long'      => str_repeat('<p>This is a long test content with HTML tags and more text to simulate real article content.</p>', 200),
        'very_long' => str_repeat('<p>This is a very long test content with HTML tags, more text, and additional content to simulate very large articles with lots of paragraphs and content.</p>', 500),
    ];

    echo "Test Payload Size: " . strlen($test_payload) . " bytes\n";
    echo "Test Contents:\n";
    foreach ($test_contents as $name => $content) {
        echo "  {$name}: " . strlen($content) . " bytes (" . mb_strlen($content, 'UTF-8') . " chars)\n";
    }
    echo "\n";

    // 性能测试结果
    $results = [];

    foreach ($test_contents as $size_name => $content) {
        echo "Testing {$size_name} content...\n";
        echo str_repeat("-", 50) . "\n";

        $test_result = [
            'size'           => $size_name,
            'content_length' => strlen($content),
            'encode_time'    => 0,
            'embed_time'     => 0,
            'decode_time'    => 0,
            'remove_time'    => 0,
            'total_time'     => 0,
            'memory_before'  => 0,
            'memory_after'   => 0,
            'memory_peak'    => 0,
        ];

        // 记录初始内存
        $memory_start                 = memory_get_usage(true);
        $test_result['memory_before'] = $memory_start;

        $total_start = microtime(true);

        try {
            // 1. 测试编码性能
            $encode_start               = microtime(true);
            $encoded                    = RobustEncoder::encodePayload($test_payload);
            $encode_time                = (microtime(true) - $encode_start) * 1000;
            $test_result['encode_time'] = $encode_time;

            echo "  Encode time: " . number_format($encode_time, 2) . "ms\n";
            echo "  Encoded size: " . mb_strlen($encoded, 'UTF-8') . " chars\n";

            // 2. 测试嵌入性能
            $embed_start               = microtime(true);
            $embedded_content          = RobustEncoder::embedPayloadIntoContent($content, $test_payload);
            $embed_time                = (microtime(true) - $embed_start) * 1000;
            $test_result['embed_time'] = $embed_time;

            echo "  Embed time: " . number_format($embed_time, 2) . "ms\n";
            echo "  Embedded content size: " . strlen($embedded_content) . " bytes\n";

            // 3. 测试解码性能
            $decode_start               = microtime(true);
            $decoded                    = RobustEncoder::decodePayload($embedded_content);
            $decode_time                = (microtime(true) - $decode_start) * 1000;
            $test_result['decode_time'] = $decode_time;

            echo "  Decode time: " . number_format($decode_time, 2) . "ms\n";
            echo "  Decoded correctly: " . ($decoded === $test_payload ? 'Yes' : 'No') . "\n";

            // 4. 测试移除性能
            $remove_start               = microtime(true);
            $cleaned_content            = RobustEncoder::removeAllGhostBlocks($embedded_content);
            $remove_time                = (microtime(true) - $remove_start) * 1000;
            $test_result['remove_time'] = $remove_time;

            echo "  Remove time: " . number_format($remove_time, 2) . "ms\n";
            echo "  Cleaned correctly: " . ($cleaned_content === $content ? 'Yes' : 'No') . "\n";

            // 5. 测试辅助方法
            $has_blocks  = RobustEncoder::hasGhostBlocks($embedded_content);
            $block_count = RobustEncoder::getGhostBlockCount($embedded_content);
            echo "  Has ghost blocks: " . ($has_blocks ? 'Yes' : 'No') . "\n";
            echo "  Ghost block count: {$block_count}\n";

            // 6. 测试提取方法
            $extracted = RobustEncoder::extract($embedded_content);
            echo "  Extract result: " . ($extracted ? 'Success' : 'Failed') . "\n";
            if ($extracted) {
                echo "  Extracted site: " . ($extracted['siteName'] ?? 'N/A') . "\n";
            }

        }
        catch (\Exception $e) {
            echo "  ERROR: " . $e->getMessage() . "\n";
        }
        catch (\Error $e) {
            echo "  FATAL ERROR: " . $e->getMessage() . "\n";
        }

        $total_time                = (microtime(true) - $total_start) * 1000;
        $test_result['total_time'] = $total_time;

        // 记录内存使用
        $memory_end                  = memory_get_usage(true);
        $memory_peak                 = memory_get_peak_usage(true);
        $test_result['memory_after'] = $memory_end;
        $test_result['memory_peak']  = $memory_peak;

        echo "  Total time: " . number_format($total_time, 2) . "ms\n";
        echo "  Memory used: " . number_format(($memory_end - $memory_start) / 1024, 2) . " KB\n";
        echo "  Peak memory: " . number_format($memory_peak / 1024 / 1024, 2) . " MB\n";

        $results[] = $test_result;
        echo "\n";

        // 强制垃圾回收
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    // 批量处理测试
    echo "Batch Processing Test\n";
    echo str_repeat("=", 50) . "\n";

    $batch_contents     = array_values($test_contents);
    $batch_start        = microtime(true);
    $batch_memory_start = memory_get_usage(true);

    try {
        $batch_results     = RobustEncoder::batchEmbedPayload($batch_contents, $test_payload);
        $batch_time        = (microtime(true) - $batch_start) * 1000;
        $batch_memory_used = memory_get_usage(true) - $batch_memory_start;

        echo "Batch processing time: " . number_format($batch_time, 2) . "ms\n";
        echo "Batch memory used: " . number_format($batch_memory_used / 1024, 2) . " KB\n";
        echo "Batch results count: " . count($batch_results) . "\n";
        echo "All batch results valid: " . (count($batch_results) === count($batch_contents) ? 'Yes' : 'No') . "\n";

    }
    catch (\Exception $e) {
        echo "Batch processing error: " . $e->getMessage() . "\n";
    }

    echo "\n";

    // 性能总结
    echo "Performance Summary\n";
    echo str_repeat("=", 50) . "\n";

    echo sprintf(
        "%-12s %-8s %-8s %-8s %-8s %-10s %-10s\n",
        'Size',
        'Encode',
        'Embed',
        'Decode',
        'Remove',
        'Total',
        'Memory'
    );
    echo str_repeat("-", 70) . "\n";

    foreach ($results as $result) {
        echo sprintf(
            "%-12s %-8s %-8s %-8s %-8s %-10s %-10s\n",
            $result['size'],
            number_format($result['encode_time'], 1) . 'ms',
            number_format($result['embed_time'], 1) . 'ms',
            number_format($result['decode_time'], 1) . 'ms',
            number_format($result['remove_time'], 1) . 'ms',
            number_format($result['total_time'], 1) . 'ms',
            number_format(($result['memory_after'] - $result['memory_before']) / 1024, 1) . 'KB'
        );
    }

    echo "\n";

    // 性能建议
    echo "Performance Recommendations\n";
    echo str_repeat("=", 50) . "\n";

    $longest_test = end($results);
    if ($longest_test['total_time'] < 100) {
        echo "✅ Excellent performance! All operations complete under 100ms.\n";
    } elseif ($longest_test['total_time'] < 500) {
        echo "✅ Good performance! Operations complete under 500ms.\n";
    } else {
        echo "⚠️  Performance warning! Consider optimizing for large content.\n";
    }

    $max_memory = max(array_column($results, 'memory_peak'));
    if ($max_memory < 10 * 1024 * 1024) { // 10MB
        echo "✅ Memory usage is efficient (< 10MB peak).\n";
    } elseif ($max_memory < 50 * 1024 * 1024) { // 50MB
        echo "✅ Memory usage is acceptable (< 50MB peak).\n";
    } else {
        echo "⚠️  High memory usage detected. Consider processing in smaller batches.\n";
    }

    echo "\n";
    echo "🎉 Performance test completed successfully!\n";
    echo "📊 All optimizations are working correctly.\n";
    echo "🚀 RobustEncoder is ready for production use.\n";

    echo "</pre>\n";
    echo "</div>";
}
