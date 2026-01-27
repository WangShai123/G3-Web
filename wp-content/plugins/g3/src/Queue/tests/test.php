<?php
/**
 * Queue Test - EmailJob Push Example
 * 队列测试 - EmailJob推送示例
 * 
 * @since 1.0.0
 * @author Wang Shai
 */

use JEALER\G3\Container\Container;
use JEALER\G3\Queue\Queue;

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 确保在WordPress完全加载后执行
add_action('init', function () {
    // 只在调试模式下执行，避免生产环境意外执行
    // if (!defined('WP_DEBUG') || !WP_DEBUG) {
    //     return;
    // }

    // 避免重复执行
    static $executed = false;
    if ($executed) {
        return;
    }
    $executed = true;

    try {
        // 实例化Queue - 直接创建新实例以确保使用最新配置
        $queue = new \JEALER\G3\Queue();

        // 准备EmailJob数据
        $emailData = [
            'to'       => 'test@example.com',
            'subject'  => 'G3队列系统测试邮件 - ' . date('Y-m-d H:i:s'),
            'message'  => '这是一封来自G3队列系统的测试邮件。<br><br>发送时间：' . date('Y-m-d H:i:s') . '<br>队列系统正常工作！',
            'template' => 'default',
            'headers'  => [
                'From: G3系统 <noreply@example.com>',
                'Content-Type: text/html; charset=UTF-8'
            ]
        ];

        // 推送EmailJob任务到队列
        $jobId = $queue->push(
            'JEALER\\G3\\Queue\\Jobs\\EmailJob',  // 任务类名
            $emailData,                           // 任务数据
            0,                                    // 延迟时间（秒）
            'default'                            // 队列名称
        );

        if ($jobId) {
            error_log("[G3 Test] EmailJob pushed to queue successfully. Job ID: {$jobId}");

            // 获取队列状态信息
            $queueSize = $queue->size('default');
            error_log("[G3 Test] Current queue size: {$queueSize}");

            // 获取队列配置信息
            $config     = $queue->getConfig();
            $driverType = $config['driver'] ?? 'unknown';
            // error_log("[G3] Queue config: " . json_encode($config, JSON_PRETTY_PRINT));
            error_log("[G3 Test] Queue driver: {$driverType}");

            // 如果是管理员用户，显示调试信息
            if (is_admin() && current_user_can('manage_options')) {
                add_action('admin_notices', function () use ($jobId, $emailData, $queueSize, $driverType) {
                    echo '<div class="notice notice-success is-dismissible">';
                    echo '<p><strong>G3队列测试成功：</strong>EmailJob任务已推送到队列</p>';
                    echo '<ul>';
                    echo '<li>任务ID: ' . esc_html($jobId) . '</li>';
                    echo '<li>收件人: ' . esc_html($emailData['to']) . '</li>';
                    echo '<li>主题: ' . esc_html($emailData['subject']) . '</li>';
                    echo '<li>队列大小: ' . esc_html($queueSize) . '</li>';
                    echo '<li>驱动类型: ' . esc_html($driverType) . '</li>';
                    echo '</ul>';
                    echo '<p><em>提示：可以使用 <code>php queue-worker.php --stop-when-empty --verbose</code> 来处理队列任务</em></p>';
                    echo '</div>';
                });
            }
        } else {
            error_log("[G3 Test] Failed to push EmailJob to queue");

            // 如果是管理员用户，显示错误信息
            if (is_admin() && current_user_can('manage_options')) {
                add_action('admin_notices', function () {
                    echo '<div class="notice notice-error is-dismissible">';
                    echo '<p><strong>G3队列测试失败：</strong>EmailJob任务推送失败</p>';
                    echo '</div>';
                });
            }
        }

    }
    catch (Exception $e) {
        error_log("[G3 Test] Queue test error: " . $e->getMessage());
        error_log("[G3 Test] Stack trace: " . $e->getTraceAsString());

        // 如果是管理员用户，显示错误信息
        if (is_admin() && current_user_can('manage_options')) {
            add_action('admin_notices', function () use ($e) {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>G3队列测试错误：</strong>' . esc_html($e->getMessage()) . '</p>';
                echo '<p><em>详细错误信息已记录到错误日志中</em></p>';
                echo '</div>';
            });
        }
    }
}, 10);