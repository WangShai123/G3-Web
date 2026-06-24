<?php
namespace JEALER\G3\Utilities;

final class Message {

    public static function loginSuccess(): string
    {
        return __('Login Successful', 'G3');
    }

    public static function bindSuccess(): string
    {
        return __('Bind Successful', 'G3');
    }

    public static function logoutSuccess(): string
    {
        return __('Logout Successful', 'G3');
    }

    public static function forbidden(): string
    {
        return __('Forbidden', 'G3');
    }

    public static function unauthorized(): string
    {
        return __('Unauthorized', 'G3');
    }

    public static function updated(): string
    {
        return __('Updated', 'G3');
    }

    public static function deleteConfirm(): void
    {
        echo __('Are you sure you want to delete it?', 'G3');
    }
}
