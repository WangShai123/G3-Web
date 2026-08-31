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
     */
    public static function ajaxSuccess(string $message): void
    {
        wp_send_json_success(
            ['message' => $message],
            200
        );
    }

    /**
     * Sends a standard WordPress AJAX error response format to the client 
     * 
     * 向客户端发送标准的 WordPress AJAX 失败响应格式
     * 
     * @param string $message
     * @return void
     */
    public static function ajaxError(string $message): void
    {
        wp_send_json_error(
            ['message' => $message],
            400
        );
    }

    /**
     * Updated
     */
    public static function ajaxUpdated(): void
    {
        wp_send_json_success(
            ['message' => Message::updated()],
            200
        );
    }

    /**
     * Deleted
     */
    public static function ajaxDeleted(): void
    {
        wp_send_json_success([
            'message' => Message::deleted(),
        ]);
    }

    /**
     * Forbidden
     */
    public static function ajaxForbidden(): void
    {
        wp_send_json_error(
            ['message' => Message::forbidden()],
            403
        );
    }

    /**
     * Illegal request
     */
    public static function ajaxIllegal(): void
    {
        wp_send_json_error(
            ['message' => Message::illegalRequest()],
            402
        );
    }

    /**
     * Failed
     */
    public static function ajaxFailed(): void
    {
        wp_send_json_error(
            ['message' => Message::failed()],
            400
        );
    }

    /**
     * Param Missing
     */
    public static function ajaxParamMissing(): void
    {
        wp_send_json_error(
            ['message' => Message::paramMissing()],
            400
        );
    }
}
