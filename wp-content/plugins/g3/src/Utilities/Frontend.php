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
    public static function loadStyle(string $handle, bool $cdn = false, string $media = 'all')
    {
        $styles = [
            'jui'         => [G3_CSS_URL . '/jui.css', [], '1.0.0', 'https://unpkg.com/vanilla-jui@latest/dist/style.css'],

            /**
             * highlight: JavaScript syntax highlighter with language auto-detection and zero dependencies.
             * @link https://github.com/highlightjs/highlight.js
             */
            'highlight'   => [G3_CSS_URL . '/highlight.atom-one-dark.min.css', [], '11.11.1', 'https://cdn.jsdelivr.net/npm/highlight.js@11.11.1/styles/atom-one-dark.min.css'],
            /**
             * Swiper: The most modern mobile touch slider with hardware accelerated transitions
             * @link https://github.com/nolimits4web/Swiper
             */
            'swiper'      => [G3_CSS_URL . '/swiper-bundle.min.css', [], '12.1.1', 'https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.css'],
            'tiny.swiper' => [G3_CSS_URL . '/tiny.swiper.min.css', [], '1.0.0', 'https://unpkg.com/tiny-swiper@latest/lib/index.css'],
        ];
        /**
         * Custom Filter: g3_filter_style
         * @param array $styles The array of style handles.
         * @return array The filtered array of style handles.
         * @since 1.0.0
         * @author Wang Shai
         */
        $styles = apply_filters('g3_filter_style', $styles);

        if (isset($styles[$handle])) {
            $style = $styles[$handle];

            if ($cdn && isset($style[3])) {
                $style[0] = $style[3];
            }

            wp_enqueue_style($handle, $style[0], $style[1], $style[2], $media);

            // return true;
        }
        // return false;
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
    public static function loadScript(string $handle, bool $cdn = false, bool $in_footer = true): bool
    {
        // static $scripts;
        $scripts = [
            // jQuery
            'jquery'             => [includes_url('js/jquery/jquery.min.js'), [], '3.7.1', 'https://unpkg.com/jquery@latest/dist/jquery.min.js'],
            // vanilla signal & library
            'vanilla-signal'     => [G3_JS_URL . '/vanilla-signal.umd.js', [], '0.2.9', 'https://unpkg.com/vanilla-signal@latest/dist/index.umd.js'],
            'vanilla-simple-lru' => [G3_JS_URL . '/vanilla-simple-lru.umd.js', [], '0.2.5', 'https://unpkg.com/vanilla-simple-lru@latest/dist/index.umd.js'],
            'vanilla-query'      => [G3_JS_URL . '/vanilla-query.umd.js', ['vanilla-signal', 'vanilla-simple-lru'], '0.2.5', 'https://unpkg.com/vanilla-query@latest/dist/index.umd.js'],
            'vanilla-storage'    => [G3_JS_URL . '/vanilla-storage.umd.js', [], '0.2.3', 'https://unpkg.com/vanilla-storage@latest/dist/index.umd.js'],
            'vanilla-i18n'       => [G3_JS_URL . '/vanilla-i18n.umd.js', ['vanilla-signal'], '0.2.0', 'https://unpkg.com/vanilla-i18n@latest/dist/index.umd.js'],
            // JUI
            'jui'                => [G3_JS_URL . '/jui.umd.js', [], '1.0.0', 'https://unpkg.com/jealer-jui@latest/dist/jui.umd.js'],
            'jui.pca'            => [G3_JS_URL . '/jui.pca.min.js', [], '1.0.0'],
            // G3
            'g3.redirect.link'   => [WP_PLUGIN_URL . '/g3/assets/javascript/g3.redirect.link.min.js', [], '1.0.0'],
            'g3.admin'           => [WP_PLUGIN_URL . '/g3/assets/javascript/g3.admin.min.js', ['jquery'], '1.0.0'],
            // Template Scripts
            'g3.media.upload'    => [WP_PLUGIN_URL . '/g3/assets/javascript/g3.template.media.upload.min.js', ['jquery'], '1.0.0'],
            'g3.media.image'     => [WP_PLUGIN_URL . '/g3/assets/javascript/g3.template.media.image.upload.min.js', ['jquery'], '1.0.0'],
            /**
             * Htm:
             * @link https://github.com/developit/htm
             */
            'htm'                => [G3_JS_URL . '/htm.umd.js', [], '3.1.1', 'https://unpkg.com/htm@3.1.1/dist/htm.umd.js'],
            /**
             * Decimal: An arbitrary-precision Decimal type for JavaScript
             * @link https://github.com/MikeMcl/decimal.js
             */
            'decimal'            => [G3_JS_URL . '/decimal.min.js', [], '10.6.0', 'https://cdn.jsdelivr.net/npm/decimal.js@10.6.0/decimal.min.js'],
            /**
             * pace: Automatically add a progress bar to your site
             * @link https://github.com/CodeByZach/pace/
             */
            'pace'               => [G3_JS_URL . '/pace.min.js', [], '1.2.4', 'https://cdn.jsdelivr.net/npm/pace-js@1.2.4/pace.min.js'],
            /**
             * Swiper: The most modern mobile touch slider with hardware accelerated transitions
             * @link https://github.com/nolimits4web/Swiper
             */
            'swiper'             => [G3_JS_URL . '/swiper-bundle.min.js', [], '12.1.1', 'https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.js'],
            /**
             * vanilla-lazyload: it leverages IntersectionObserver to lazy load images, backgrounds, videos, iframes and scripts.
             * @link https://github.com/verlok/vanilla-lazyload
             */
            'lazyload'           => [G3_JS_URL . '/lazyload.min.js', [], '19.1.3', 'https://cdn.jsdelivr.net/npm/vanilla-lazyload@19.1.3/dist/lazyload.min.js'],
            /**
             * highlight: JavaScript syntax highlighter with language auto-detection and zero dependencies.
             * @link https://github.com/highlightjs/highlight.js
             */
            'highlight'          => [G3_JS_URL . '/highlight.min.js', [], '11.11.1', 'https://cdn.jsdelivr.net/npm/highlight.js@11.11.1/lib/common.min.js'],
            /**
             * qrcodeJS: Cross-browser QRCode generator for javascript.
             * @link https://github.com/davidshimjs/qrcodejs
             */
            'qrcode'             => [G3_JS_URL . '/qrcode.min.js', [], '1.0.0', 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js'],
        ];

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
    public static function loadModule(string $handle, bool $cdn = false): bool
    {
        $modules = [
            // vanilla signal
            'vanilla-signal'     => [G3_JS_URL . '/es/vanilla-signal.js', [], '0.2.9', 'https://unpkg.com/vanilla-signal@latest/dist/index.js'],
            'vanilla-simple-lru' => [G3_JS_URL . '/es/vanilla-simple-lru.js', [], '0.2.5', 'https://unpkg.com/vanilla-simple-lru@latest/dist/index.js'],
            'vanilla-storage'    => [G3_JS_URL . '/es/vanilla-storage.js', [], '0.2.3', 'https://unpkg.com/vanilla-storage@latest/dist/index.js'],
            'vanilla-query'      => [G3_JS_URL . '/es/vanilla-query.js', ['vanilla-signal', 'vanilla-simple-lru'], '0.2.5', 'https://unpkg.com/vanilla-query@latest/dist/index.js'],
            'vanilla-i18n'       => [G3_JS_URL . '/es/vanilla-i18n.js', ['vanilla-signal'], '0.2.0', 'https://unpkg.com/vanilla-i18n@latest/dist/index.js'],
            // jui
            'jui'                => [G3_JS_URL . '/es/jui.js', ['vanilla-signal'], '1.0.0', 'https://unpkg.com/vanilla-jui@latest/dist/index.js'],

            // build-in g3 modules
            'g3.login.modal'     => [G3_ASSETS_URL . '/javascript/es/g3.login.modal.min.js', ['jui'], '1.0.0'],
            'g3.subscribe.modal' => [G3_ASSETS_URL . '/javascript/es/g3.subscribe.modal.min.js', ['jui'], '1.0.0'],
            'g3.login.oa'        => [G3_ASSETS_URL . '/javascript/es/g3.login.oa.min.js', ['jui'], '1.0.0'],

            // htm
            'htm'                => [G3_JS_URL . '/es/htm.module.js', [], '3.1.1', 'https://unpkg.com/htm@3.1.1/dist/htm.module.js'],

            /**
             * qrcodeJS: Cross-browser QRCode generator for javascript
             * @link: https://github.com/davidshimjs/qrcodejs
             * @todo: add file
             */
            'qrcode'             => ['', [], '1.0.0', 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/+esm'],
        ];

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
