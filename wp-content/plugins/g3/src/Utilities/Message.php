<?php
namespace JEALER\G3\Utilities;

/**
 * Message Utilities
 * 
 * 消息工具类
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
final class Message {

    /**
     * Login Successful
     */
    public static function loginSuccess()
    {
        return __('Login Successful', 'G3');
    }

    /**
     * Bind Successful
     */
    public static function bindSuccess()
    {
        return __('Bind Successful', 'G3');
    }

    /**
     * Logout Successful
     */
    public static function logoutSuccess()
    {
        return __('Logout Successful', 'G3');
    }

    /**
     * Forbidden
     */
    public static function forbidden()
    {
        return __('Forbidden', 'G3');
    }

    /**
     * Updated
     */
    public static function updated()
    {
        return __('Updated', 'G3');
    }
}
