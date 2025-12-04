<?php
namespace JEALER\G3\Services;
use JEALER\G3\Components;
class PageService {
    /**
     * Check if is user page.
     * 
     * 当前模板页是否是用户页面。
     * 
     * @todo
     * @return bool
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function isUser(): bool
    {
        return true;
    }

    /**
     * Check if is my page.
     * 
     * 当前模板页是否是我的页面。
     * 
     * @todo
     * @return bool
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function isMy(): bool
    {
        return true;
    }

    /**
     * Check if is login page.
     * 
     * 当前模板页是否是登录页面。
     * 
     * @todo
     * @return bool
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function isLogin(): bool
    {
        return true;
    }

    /**
     * Check if is register page.
     * 
     * 当前模板页是否是注册页面。
     * 
     * @todo
     * @return bool
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function isRegister(): bool
    {
        return true;
    }

    /**
     * Check if is lost password page.
     * 
     * 当前模板页是否是找回密码页面。
     * 
     * @todo
     * @return bool
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function isLostPassword(): bool
    {
        return true;
    }

    /**
     * Check if is reset password page.
     * 
     * 当前模板页是否是重置密码页面。
     * 
     * @todo
     * @return bool
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function isResetPassword(): bool
    {
        return true;
    }

    /**
     * Check if is custom admin login page.
     * 
     * 当前模板页是否是自定义后台登录页面。
     * 
     * @return bool
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function isAdminLogin(): bool
    {
        global $wp_query;
        $v = Components::getProperty('Security', 'option')['url'] ?? '';
        return isset($wp_query->query_vars['custom_admin_login']) && $wp_query->query_vars['custom_admin_login'] === $v;
    }
}