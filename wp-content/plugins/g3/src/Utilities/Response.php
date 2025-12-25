<?php
namespace JEALER\G3\Utilities;
use JEALER\G3\Utilities\System;
use WP_REST_Request;
final class Response {

    /**
     * Sends a standard WordPress AJAX success response format to the client
     * 
     * 向客户端发送标准的 WordPress AJAX 成功响应格式
     *
     * @param string $message
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function ajaxSuccess(string $message): void
    {
        wp_send_json_success([
            'message' => $message
        ]);
    }

    /**
     * Sends a standard WordPress AJAX error response format to the client 
     * 
     * 向客户端发送标准的 WordPress AJAX 失败响应格式
     * 
     * @param string $message
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function ajaxError(string $message): void
    {
        wp_send_json_error([
            'message' => $message
        ]);
    }

    /**
     * Send AJAX success response: Updated
     * 
     * 发送AJAX成功响应: 更新成功
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function ajaxUpdated(): void
    {
        wp_send_json_success([
            'message' => __('Updated', 'G3')
        ]);
    }

    /**
     * Send AJAX success response: Deleted
     * 
     * 发送AJAX成功响应: 删除成功
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function ajaxDeleted(): void
    {
        wp_send_json_success([
            'message' => __('Deleted', 'G3')
        ]);
    }

    /**
     * Send AJAX error response: Forbidden
     * 
     * 发送AJAX错误响应: 禁止访问
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function ajaxForbidden(): void
    {
        wp_send_json_error([
            'message' => __('Forbidden', 'G3')
        ]);
    }

    /**
     * Send AJAX error response: Failed
     * 
     * 发送AJAX错误响应: 失败
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function ajaxFailed(): void
    {
        wp_send_json_error([
            'message' => __('Failed', 'G3')
        ]);
    }
}