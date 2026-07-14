<?php
namespace JEALER\G3\Utilities;
use ReflectionFunction;
use ReflectionException;

final class Frontend {

    private static bool  $stylesRegistered          = false;
    private static bool  $scriptsRegistered         = false;
    private static bool  $modulesRegistered         = false;
    private static array $styles                    = [];
    private static array $scripts                   = [];
    private static array $modules                   = [];
    private static array $moduleVariants            = [];
    private static ?bool $scriptModuleArgsSupported = null;

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
        $classes = ['G3-Web'];

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
     * Register all configured frontend assets once per request.
     */
    public static function registerAssets(): void
    {
        self::registerStyles();
        self::registerScripts();
        self::registerModules();
    }

    /**
     * Register configured styles.
     */
    public static function registerStyles(): void
    {
        if (self::$stylesRegistered || !function_exists('wp_register_style')) {
            return;
        }

        $styles = self::loadAssetConfig('css');
        $styles = apply_filters('g3_filter_css', $styles);
        $styles = apply_filters('g3_filter_styles', $styles);

        foreach ($styles as $handle => $style) {
            if (!is_string($handle) || !is_array($style)) {
                continue;
            }

            $style = self::normalizeAsset($style);
            if ($style === null) {
                continue;
            }

            self::$styles[$handle] = $style;
            if ($style['src'] !== '') {
                wp_register_style($handle, $style['src'], $style['deps'], $style['version'], $style['media']);
            }
        }

        self::$stylesRegistered = true;
    }

    /**
     * Register configured classic scripts.
     */
    public static function registerScripts(): void
    {
        if (self::$scriptsRegistered || !function_exists('wp_register_script')) {
            return;
        }

        $scripts = self::loadAssetConfig('umd');
        $scripts = apply_filters('g3_filter_umd', $scripts);
        $scripts = apply_filters('g3_filter_scripts', $scripts);

        foreach ($scripts as $handle => $script) {
            if (!is_string($handle) || !is_array($script)) {
                continue;
            }

            $script = self::normalizeAsset($script);
            if ($script === null) {
                continue;
            }

            self::$scripts[$handle] = $script;
            if ($script['src'] !== '') {
                wp_register_script(
                    $handle,
                    $script['src'],
                    $script['deps'],
                    $script['version'],
                    self::normalizeScriptArgs($script['args'])
                );
            }
        }

        self::$scriptsRegistered = true;
    }

    /**
     * Register configured script modules.
     */
    public static function registerModules(): void
    {
        if (self::$modulesRegistered || !function_exists('wp_register_script_module')) {
            return;
        }

        $modules = self::loadAssetConfig('esm');
        $modules = apply_filters('g3_filter_esm', $modules);
        $modules = apply_filters('g3_filter_modules', $modules);

        foreach ($modules as $handle => $module) {
            if (!is_string($handle) || !is_array($module)) {
                continue;
            }

            $module = self::normalizeAsset($module);
            if ($module === null) {
                continue;
            }

            self::$modules[$handle] = $module;
            if ($module['src'] !== '') {
                self::registerScriptModule($handle, $module['src'], $module['deps'], $module['version'], self::normalizeModuleArgs($module['args']));
            }
        }

        self::$modulesRegistered = true;
    }

    /**
     * Enqueue registered styles by handle.
     *
     * @param string|array<int,string> $handle
     */
    public static function css(string|array $handle, bool $cdn = false, string $media = 'all'): bool
    {
        if (!function_exists('wp_enqueue_style')) {
            return false;
        }

        self::registerStyles();

        return self::enqueueHandles($handle, static function (string $name) use ($cdn, $media): bool {
            $queueHandle = self::resolveStyleHandle($name, $cdn, $media);
            if ($queueHandle === null) {
                return false;
            }

            wp_enqueue_style($queueHandle);
            return true;
        });
    }

    /**
     * Enqueue registered classic scripts by handle.
     *
     * @param string|array<int,string> $handle
     */
    public static function umd(string|array $handle, bool $cdn = false, bool $inFooter = true): bool
    {
        if (!function_exists('wp_enqueue_script')) {
            return false;
        }

        self::registerScripts();

        return self::enqueueHandles($handle, static function (string $name) use ($cdn, $inFooter): bool {
            $queueHandle = self::resolveScriptHandle($name, $cdn, $inFooter);
            if ($queueHandle === null) {
                return false;
            }

            wp_enqueue_script($queueHandle);
            return true;
        });
    }

    /**
     * Enqueue registered script modules by handle.
     *
     * @param string|array<int,string> $handle
     */
    public static function esm(string|array $handle, bool $cdn = false, bool $inFooter = true): bool
    {
        if (!function_exists('wp_enqueue_script_module')) {
            return false;
        }

        self::registerModules();

        return self::enqueueHandles($handle, static function (string $name) use ($cdn, $inFooter): bool {
            $queueHandle = self::resolveModuleHandle($name, $cdn, $inFooter);
            if ($queueHandle === null) {
                return false;
            }

            wp_enqueue_script_module($queueHandle);
            return true;
        });
    }

    /**
     * Alias for enqueueing registered script modules.
     *
     * @param string|array<int,string> $handle
     */
    public static function module(string|array $handle, bool $cdn = false, bool $inFooter = true): bool
    {
        return self::esm($handle, $cdn, $inFooter);
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

    private static function loadAssetConfig(string $name): array
    {
        $file = G3_CONFIG_DIR . '/' . $name . '.php';
        if (!is_file($file)) {
            return [];
        }

        $config = require $file;
        return is_array($config) ? $config : [];
    }

    private static function normalizeDependencies(mixed $dependencies): array
    {
        return is_array($dependencies) ? $dependencies : [];
    }

    private static function normalizeAsset(array $asset): ?array
    {
        $src = is_string($asset[0] ?? null) ? $asset[0] : '';
        if ($src === '' && !self::isUsableUrl($asset[3] ?? null)) {
            return null;
        }

        return [
            'src'     => $src,
            'deps'    => self::normalizeDependencies($asset[1] ?? []),
            'version' => $asset[2] ?? false,
            'cdn'     => $asset[3] ?? null,
            'media'   => is_string($asset['media'] ?? null) ? $asset['media'] : 'all',
            'args'    => $asset['args'] ?? true,
        ];
    }

    private static function normalizeScriptArgs(mixed $args): array|bool
    {
        if (is_array($args)) {
            return $args;
        }

        return ['in_footer' => (bool) $args];
    }

    private static function normalizeModuleArgs(mixed $args): array
    {
        return is_array($args) ? $args : ['in_footer' => (bool) $args];
    }

    private static function enqueueHandles(string|array $handles, callable $enqueue): bool
    {
        $handles = is_array($handles) ? $handles : [$handles];
        $queued  = false;

        foreach ($handles as $handle) {
            if (!is_string($handle) || $handle === '') {
                continue;
            }

            if ($enqueue($handle)) {
                $queued = true;
            }
        }

        return $queued;
    }

    private static function resolveStyleHandle(string $handle, bool $cdn, string $media, array $resolving = []): ?string
    {
        if (!isset(self::$styles[$handle])) {
            return self::isStyleRegistered($handle) ? $handle : null;
        }

        $asset = self::$styles[$handle];
        $src   = self::resolveAssetSrc($asset, $cdn);
        if ($src === '') {
            return null;
        }

        if ($src === $asset['src'] && $media === $asset['media']) {
            return $handle;
        }

        $variant = self::variantHandle('style', $handle, [$src, $media]);
        if (!self::isStyleRegistered($variant)) {
            $resolving[$handle] = true;
            wp_register_style(
                $variant,
                $src,
                self::resolveStyleDependencies($asset['deps'], $cdn, $media, $resolving),
                $asset['version'],
                $media
            );
        }

        return $variant;
    }

    private static function resolveScriptHandle(string $handle, bool $cdn, bool $inFooter, array $resolving = []): ?string
    {
        if (!isset(self::$scripts[$handle])) {
            return self::isScriptRegistered($handle) ? $handle : null;
        }

        $asset = self::$scripts[$handle];
        $src   = self::resolveAssetSrc($asset, $cdn);
        if ($src === '') {
            return null;
        }

        $args = self::withScriptFooter(self::normalizeScriptArgs($asset['args']), $inFooter);
        if ($src === $asset['src'] && $args === self::normalizeScriptArgs($asset['args'])) {
            return $handle;
        }

        $variant = self::variantHandle('script', $handle, [$src, $inFooter ? 'footer' : 'header']);
        if (!self::isScriptRegistered($variant)) {
            $resolving[$handle] = true;
            wp_register_script(
                $variant,
                $src,
                self::resolveScriptDependencies($asset['deps'], $cdn, $inFooter, $resolving),
                $asset['version'],
                $args
            );
        }

        return $variant;
    }

    private static function resolveModuleHandle(string $handle, bool $cdn, bool $inFooter, array $resolving = []): ?string
    {
        if (!isset(self::$modules[$handle])) {
            return null;
        }

        $asset = self::$modules[$handle];
        $src   = self::resolveAssetSrc($asset, $cdn);
        if ($src === '') {
            return null;
        }

        $args = self::withModuleFooter(self::normalizeModuleArgs($asset['args']), $inFooter);
        if ($src === $asset['src'] && $args === self::normalizeModuleArgs($asset['args'])) {
            return $handle;
        }

        $variant = self::variantHandle('module', $handle, [$src, $inFooter ? 'footer' : 'header']);
        if (!isset(self::$moduleVariants[$variant])) {
            $resolving[$handle] = true;
            self::registerScriptModule(
                $variant,
                $src,
                self::resolveModuleDependencies($asset['deps'], $cdn, $inFooter, $resolving),
                $asset['version'],
                $args
            );
            self::$moduleVariants[$variant] = true;
        }

        return $variant;
    }

    private static function resolveScriptDependencies(array $dependencies, bool $cdn, bool $inFooter, array $resolving): array
    {
        foreach ($dependencies as $index => $dependency) {
            if (!is_string($dependency) || isset($resolving[$dependency]) || !isset(self::$scripts[$dependency])) {
                continue;
            }

            $dependencies[$index] = self::resolveScriptHandle($dependency, $cdn, $inFooter, $resolving) ?? $dependency;
        }

        return $dependencies;
    }

    private static function resolveStyleDependencies(array $dependencies, bool $cdn, string $media, array $resolving): array
    {
        foreach ($dependencies as $index => $dependency) {
            if (!is_string($dependency) || isset($resolving[$dependency]) || !isset(self::$styles[$dependency])) {
                continue;
            }

            $dependencies[$index] = self::resolveStyleHandle($dependency, $cdn, $media, $resolving) ?? $dependency;
        }

        return $dependencies;
    }

    private static function resolveModuleDependencies(array $dependencies, bool $cdn, bool $inFooter, array $resolving): array
    {
        foreach ($dependencies as $index => $dependency) {
            if (!is_string($dependency) || isset($resolving[$dependency]) || !isset(self::$modules[$dependency])) {
                continue;
            }

            $dependencies[$index] = self::resolveModuleHandle($dependency, $cdn, $inFooter, $resolving) ?? $dependency;
        }

        return $dependencies;
    }

    private static function resolveAssetSrc(array $asset, bool $cdn): string
    {
        if (!$cdn || !self::isUsableUrl($asset['cdn'])) {
            return $asset['src'];
        }

        return $asset['cdn'];
    }

    private static function isUsableUrl(mixed $url): bool
    {
        if (!is_string($url) || $url === '') {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true);
    }

    private static function withScriptFooter(array|bool $args, bool $inFooter): array|bool
    {
        if (!is_array($args)) {
            return ['in_footer' => $inFooter];
        }

        $args['in_footer'] = $inFooter;
        return $args;
    }

    private static function withModuleFooter(array $args, bool $inFooter): array
    {
        $args['in_footer'] = $inFooter;
        return $args;
    }

    private static function registerScriptModule(
        string $handle,
        string $src,
        array $dependencies,
        string|false|null $version,
        array $args
    ): void
    {
        if (self::supportsScriptModuleArgs()) {
            wp_register_script_module($handle, $src, $dependencies, $version, $args);
            return;
        }

        wp_register_script_module($handle, $src, $dependencies, $version);
    }

    private static function supportsScriptModuleArgs(): bool
    {
        if (self::$scriptModuleArgsSupported !== null) {
            return self::$scriptModuleArgsSupported;
        }

        try {
            self::$scriptModuleArgsSupported = (new ReflectionFunction('wp_register_script_module'))->getNumberOfParameters() >= 5;
        }
        catch (ReflectionException) {
            self::$scriptModuleArgsSupported = false;
        }

        return self::$scriptModuleArgsSupported;
    }

    private static function variantHandle(string $type, string $handle, array $parts): string
    {
        return 'g3-' . $type . '-' . md5($handle . '|' . implode('|', $parts));
    }

    private static function isStyleRegistered(string $handle): bool
    {
        return function_exists('wp_style_is') && wp_style_is($handle, 'registered');
    }

    private static function isScriptRegistered(string $handle): bool
    {
        return function_exists('wp_script_is') && wp_script_is($handle, 'registered');
    }
}
