<?php
namespace JEALER\G3\Utilities;

final class Frontend {

    /**
     * Generate HTML class attribute
     *
     * 生成 HTML class 属性
     *
     * Custom Filter: g3_filter_html_class
     *
     * @param bool $echo whether to echo the class attribute
     * @return mixed whether to return the class attribute or echo it
     */
    public static function htmlClass($echo = true)
    {
        $classes = ['g3-web'];

        /**
         * @var array $classes
         * Custom Filter: g3_filter_html_class
         */
        $classes = apply_filters('g3_filter_html_class', $classes);

        $htmlClass = 'class="' . esc_attr(implode(' ', array_unique($classes))) . '"';

        if ($echo) {
            echo $htmlClass;
        } else {
            return $htmlClass;
        }
    }

    /**
     * Implement on-demand loading styles, support users to extend and manage custom style resources
     *
     * 按需加载样式，支持用户扩展和管理自定义样式资源
     *
     * Custom Filter: g3_filter_style
     *
     * @param string $handle The style handle.
     * @param bool $cdn Whether to load the style from a CDN (default: false).
     * @param string $media The media type for the style (default: 'all').
     * @return
     */
    public static function css(string $handle, bool $cdn = false, string $media = 'all')
    {
        $styles = require_once G3_CONFIG_DIR . '/css.php';

        /**
         * Custom Filter: g3_filter_css
         * @param array $styles The array of style handles.
         * @return array The filtered array of style handles.
         * @since 1.0.0
         * @author Wang Shai
         */
        $styles = apply_filters('g3_filter_css', $styles);

        if (isset($styles[$handle])) {
            $style = $styles[$handle];

            if ($cdn && isset($style[3])) {
                $style[0] = $style[3];
            }

            wp_enqueue_style($handle, $style[0], $style[1], $style[2], $media);
            return true;
        }
        return false;
    }

    /**
     * Implement on-demand loading scripts, support users to extend and manage custom script resources
     *
     * 按需加载脚本，支持用户扩展和管理自定义脚本资源
     *
     * Custom Filter: g3_filter_scripts
     *
     * @param string $handle The script handle.
     * @param bool $cdn Whether to load the script from a CDN (default: false).
     * @param bool $in_footer Whether to load the script in the footer (default: true).
     * @return bool Whether the script is successfully loaded.
     */
    public static function umd(string $handle, bool $cdn = false, bool $in_footer = true): bool
    {
        $scripts = require_once G3_CONFIG_DIR . '/umd.php';

        /**
         * Custom Filter: g3_filter_scripts
         * @param  array $scripts The array of script handles.
         * @return array The filtered array of script handles.
         */
        $scripts = apply_filters('g3_filter_scripts', $scripts);

        if (!function_exists('wp_enqueue_script')) {
            return false;
        }

        if (!isset($scripts[$handle])) {
            return false;
        }

        $script = $scripts[$handle];
        if ($cdn && isset($script[3])) {
            $script[0] = $script[3];
        }

        wp_enqueue_script($handle, $script[0], $script[1], $script[2], $in_footer);
        return true;
    }

    /**
     * Load a specific module script
     *
     * 加载插件内置的指定模块脚本
     *
     * Custom Filter: g3_filter_modules
     *
     * @param  string $handle The module handle.
     * @param  bool   $cdn    Whether to load the module from a CDN (default: false).
     * @return bool True if the module is loaded successfully, false otherwise.
     */
    public static function esm(string $handle, bool $cdn = false): bool
    {
        $modules = require_once G3_CONFIG_DIR . '/esm.php';

        /**
         * Custom Filter: g3_filter_modules
         * @param  array $modules The array of module handles.
         * @return array The filtered array of module handles.
         * @since 1.0.0
         * @author Wang Shai
         */
        $modules = apply_filters('g3_filter_modules', $modules);

        // Check if the function exists
        if (!function_exists('wp_enqueue_script_module')) {
            return false;
        }

        if (!isset($modules[$handle])) {
            return false;
        }

        $module = $modules[$handle];
        if ($cdn && isset($module[3])) {
            $module[0] = $module[3];
        }

        wp_enqueue_script_module($handle, $module[0], $module[1], $module[2]);
        return true;
    }

    /**
     * Load a custom JavaScript module from the theme's public/javascript/ directory.
     *
     * 加载自定义的JavaScript模块，模块文件位于主题目录的public/javascript/目录下。
     *
     * @param string $fileName module filename (without extension)
     * @param array $dependencies module dependencies (optional)
     * @param string|false $version module version (optional)
     * @return void
     */
    public static function loadMyModule(string $fileName, array $dependencies = [], string|false $version = false): void
    {
        $src = get_stylesheet_directory_uri() . '/public/javascript/' . $fileName . '.mjs';
        wp_enqueue_script_module($fileName, $src, $dependencies, $version);
    }

    /**
     * Get the count of submenu items for a specific admin page.
     *
     * 获取指定页面的后台submenu菜单数量
     *
     * @param string $page The admin page slug (default: 'options-general.php').
     * @return int The count of submenu items.
     */
    public static function getAdminSubmenuCount(string $page = 'options-general.php'): int
    {
        global $submenu;
        if (!isset($submenu[$page]) || !\is_array($submenu[$page])) {
            return 0;
        }
        return \count($submenu[$page]);
    }

    /**
     * Check if the current browser is a low-end browser.
     *
     * 检查当前浏览器是否为低端浏览器
     *
     * @return bool True if the browser is low-end, false otherwise.
     */
    public static function isLowEndBrowser(): bool
    {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        // Only check desktop browsers
        return !wp_is_mobile() && (
            // IE Browser (including IE11 and below)
            preg_match('/MSIE|Trident/i', $user_agent) ||
                // Old version UC Browser (version number less than 9)
            (preg_match('/UCBrowser\/(\d+)/i', $user_agent, $matches) && \intval($matches[1]) < 9)
        );
    }
}
