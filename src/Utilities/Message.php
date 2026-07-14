<?php
namespace JEALER\G3\Utilities;

final class Message {

    public static function loginSuccess(): string
    {
        return __('Logged in', 'G3');
    }

    public static function bindSuccess(): string
    {
        return __('Bound', 'G3');
    }

    public static function logoutSuccess(): string
    {
        return __('Logged out', 'G3');
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

    public static function flushed(): string
    {
        return __('Flushed', 'G3');
    }

    public static function generated(): string
    {
        return __('Generated', 'G3');
    }
    public static function created(): string
    {
        return __('Created', 'G3');
    }

    public static function pushed(): string
    {
        return __('Pushed', 'G3');
    }

    public static function sent(): string
    {
        return __('Sent', 'G3');
    }

    public static function verified(): string
    {
        return __('Verified', 'G3');
    }

    public static function failed(): string
    {
        return __('Failed', 'G3');
    }

    public static function deleteConfirm(): void
    {
        echo __('Are you sure you want to delete it?', 'G3');
    }
    public static function changeConfirm(): void
    {
        echo __('Are you sure you want to change it?', 'G3');
    }
    public static function templateNotImplemented($name): string
    {
        return sprintf(
            "%s" . __('The template "%s" is not implemented yet. Please complete your own theme.', 'G3') . "%s",
            '<div style="display:flex;align-items:center;justify-content:center;height:100vh;padding:2rem;">',
            $name,
            '</div>'
        );
    }
}
