<?php

namespace JEALER\G3\Services;

use WP_Error;

/**
 * Sidebar Service
 * 
 * 侧边栏服务
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class SidebarService {

    /**
     * Register Widget
     * 
     * 注册侧边栏小部件
     *
     * @param string $widget widget class name
     * @param string $dir widget directory
     * @return void
     */
    public static function registerWidget(string $widget, string $dir): void
    {
        if (!is_string($widget)) {
            new WP_Error('Widget Error', 'Invalid Widget.');
        }

        if (!is_string($dir)) {
            new WP_Error('Widget Error', 'Invalid Path.');
        }

        require_once $dir . '/widgets/' . $widget . '.php';
        register_widget($widget);
    }
}
