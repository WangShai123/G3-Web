<?php
namespace JEALER\G3\Services;
use WP_Error;

class SidebarService {
    /**
     * Register Widget
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
