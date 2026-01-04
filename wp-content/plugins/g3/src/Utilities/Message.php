<?php
namespace JEALER\G3\Utilities;

final class Message {
    public const LANG = 'G3';

    public static function successLogin()
    {
        return __('Login Successful', self::LANG);
    }
}