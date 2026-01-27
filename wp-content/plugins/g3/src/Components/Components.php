<?php
namespace JEALER\G3\Components;

use JEALER\G3\Helper\Helper;
use JEALER\G3\Container\Container;
use JEALER\G3\Queue\Queue;
use JEALER\G3\Utilities\Message;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Utilities\System;
use JEALER\G3\Utilities\Common;
use JEALER\G3\Utilities\Context;
use JEALER\G3\Utilities\Event;

/**
 * Base Components Class - Provides foundational methods for all components
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
abstract class Components {

    /**
     * @var Container|null 容器实例（用户不需要知道）
     */
    private ?Container $container = null;

    /**
     * @var string 组件名称（自动推断）
     */
    protected string $componentName;


    /**
     * @var array Loaded components instances. Key is component ID, value is component instance.
     */
    public static array $components = [];

    /**
     * @var string Component unique identifier.
     */
    protected string $componentId;

    /**
     * @var array Global component configuration.
     */
    protected static array $globalConfig = [];

    /**
     * @var array Component configuration.
     */
    private array $config = [];

    /**
     * @var bool Whether the component has been initialized.
     */
    protected bool $initialized = false;

    protected ?Helper $loader;

    public function __construct()
    {
        // $this->initialize();

        // 自动推断组件名称
        $this->componentName = $this->getComponentName();

        // 内部初始化（用户不感知）
        $this->internalInit();
    }

    /**
     * 获取组件名称（自动推断）
     * 
     * @return string
     */
    private function getComponentName(): string
    {
        $className = (new \ReflectionClass($this))->getShortName();
        return strtolower($className);
    }

    /**
     * 内部初始化（用户不需要知道）
     * 
     * @return void
     */
    private function internalInit(): void
    {
        if ($this->container === null) {
            $this->container = Container::run();
        }

        // 自动注册到容器（用户不感知）
        $serviceId = 'component.' . $this->componentName;
        if (!$this->container->has($serviceId)) {
            $this->container->setRawDefinition($serviceId, $this);
        }

        $this->loader = $this->container->get('loader');
        // $this->_init();
        $this->start();

        /**
         * Compatible with traditional theme mode and front-end separation mode
         */
        if (Common::themeModeAvailable()) {
            add_action('after_setup_theme', [$this, 'initDataActions']);
        } else {
            if (!$this->loader) return;
            add_action('plugins_loaded', [$this, 'initDataActions']);
        }

        add_action('init', [$this, 'initActions']);
        add_action('admin_init', [$this, 'adminInitActions']);
        add_action('admin_menu', [$this, 'adminMenuActions']);
        add_action('widgets_init', [$this, 'widgetsInitActions']);
        add_action('wp_dashboard_setup', [$this, 'dashboardSetupActions']);
        add_filter('query_vars', [$this, 'queryVars']);

        add_action('add_meta_boxes', [$this, 'metaBoxActions']);
        add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScriptsActions']);
        add_action('wp_enqueue_scripts', [$this, 'wpEnqueueScriptsActions'], 20);

        $this->end();
    }

    // ========== 可选的便利方法 ==========
    // 用户可以选择使用，也可以完全忽略

    /**
     * 获取服务（可选使用）
     * 
     * @param string $serviceClass 服务类名
     * @return object|null
     */
    protected function getService(string $serviceClass): ?object
    {
        try {
            return $this->container->get($serviceClass);
        }
        catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 检查服务是否存在（可选使用）
     * 
     * @param string $serviceClass 服务类名
     * @return bool
     */
    protected function hasService(string $serviceClass): bool
    {
        return $this->container->has($serviceClass);
    }

    /**
     * 调试日志（可选使用）
     * 
     * @param string $message 日志信息
     * @return void
     */
    protected function debug(string $message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[G3 Debug][{$this->componentName}] {$message}");
        }
    }

    // ========== 用户完全不需要重写的方法 ==========
    // 这些方法存在只是为了内部使用

    /**
     * 获取组件名称
     * 
     * @return string
     */
    public function getName(): string
    {
        return $this->componentName;
    }

    /**
     * 获取容器实例（高级用户可选使用）
     * 
     * @return Container|null
     */
    protected function getContainer(): ?Container
    {
        return $this->container;
    }

    private function _init(): void
    {
        $this->loader = Context::get('Loader');
    }



    /**
     * Register query variables
     * @param array $vars
     * @return array
     * @since 1.0.0
     */
    public function queryVars(array $vars): array
    {
        return $vars;
    }

    /**
     * make a component instance
     * 
     * 创建组件实例
     * 
     * @param string $componentName
     * @return mixed
     * @since 1.0.0
     */
    public static function make(string $componentName)
    {
        /**
         * @var Components $component instance cache
         */
        if (isset(self::$components[$componentName])) {
            return self::$components[$componentName];
        }

        // check component namespace
        $fullClassName = (strpos($componentName, 'JEALER\\G3\\Components\\') === 0)
            ? $componentName
            : 'JEALER\\G3\\Components\\' . $componentName;

        $component = Container::use($fullClassName);
        if (!empty($component)) {
            self::$components[$componentName] = $component;
        }
        return $component;
    }

    /**
     * Get component property value
     * 
     * 获取组件属性值
     * 
     * @param string $componentName
     * @param string $propertyName
     * @return mixed
     * @since 1.0.0
     */
    public static function getProperty(string $componentName, string $propertyName)
    {
        return self::$components[$componentName]->$propertyName;
    }

    /**
     * Check if component exists
     * 
     * 检查组件是否存在
     * 
     * @param string $componentName
     * @return bool
     * @since 1.0.0
     */
    public static function hasComponent(string $componentName): bool
    {
        return isset(self::$components[$componentName]);
    }

    public function initDataActions(): void
    {
        if ($this->loader->admin()) $this->ready();
        $this->options();
        if ($this->loader->x()) $this->x();
        if ($this->loader->y()) $this->y();
    }
    public function initActions(): void
    {
        if ($this->loader->admin()) $this->system();
        $this->init();
        if (!is_admin()) $this->front();
        $this->postType();
        $this->taxonomy();
    }
    public function adminInitActions(): void
    {
        if ($this->loader->admin()) $this->admin();
        $this->form();
        $this->ajax();
        $this->settings();
    }
    public function adminMenuActions(): void
    {
        $this->adminMenu();
    }
    public function widgetsInitActions(): void
    {
        add_theme_support('widgets');
        $this->sidebar();
        $this->widgets();
    }
    public function dashboardSetupActions(): void
    {
        $this->dashboard();
    }
    public function metaBoxActions(): void
    {
        $this->metaBox();
    }
    public function adminEnqueueScriptsActions(): void
    {
        $this->adminScripts();
    }
    public function wpEnqueueScriptsActions(): void
    {
        $this->scripts();
    }
    protected function ready(): void
    {
    }
    protected function start(): void
    {
    }
    protected function system(): void
    {
    }
    protected function front(): void
    {
    }
    protected function cleanUp(): void
    {
    }
    protected function x(): void
    {
    }
    protected function y(): void
    {
    }
    protected function end(): void
    {
    }
    protected function options(): void
    {
    }
    protected function form(): void
    {
    }
    protected function init(): void
    {
    }
    protected function admin(): void
    {
    }
    protected function adminMenu(): void
    {
    }
    protected function sidebar(): void
    {
    }
    protected function widgets(): void
    {
    }
    protected function dashboard(): void
    {
    }
    protected function metaBox(): void
    {
    }
    protected function postType(): void
    {
    }
    protected function taxonomy(): void
    {
    }
    protected function ajax(): void
    {
    }
    protected function settings(): void
    {
    }
    protected function adminScripts(): void
    {
    }
    protected function scripts(): void
    {
    }
}