<?php
namespace JEALER\G3\Utilities;

final class Message {

    public static function loginSuccess()
    {
        return __('Login Successful', 'G3');
    }
    public static function bindSuccess()
    {
        return __('Binding Successful', 'G3');
    }

    public static function forbidden()
    {
        return __('Forbidden', 'G3');
    }
}