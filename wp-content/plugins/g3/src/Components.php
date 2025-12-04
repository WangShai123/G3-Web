<?php
namespace JEALER\G3;
use JEALER\G3\Utilities\Common;

/**
 * Base Components Class - Provides foundational methods for all components
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class Components {
    /**
     * @var array Loaded components instances. Key is component ID, value is component instance.
     */
    public static array $components = [];

    /**
     * @var string Component unique identifier.
     */
    protected string $componentId;

    /**
     * @var string Component name.
     */
    protected string $componentName;

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

    public function __construct()
    {
        $this->initialize();
    }

    /**
     * Initialize the component.
     * @return void
     */
    protected function initialize(): void
    {
        if ($this->initialized) return;

        global $loader;

        $this->start();
        if ($loader->admin() && !is_admin()) $this->front();
        if ($loader->x()) $this->x();
        if ($loader->y()) $this->y();

        add_action('init', [$this, 'initActions']);
        add_action('admin_init', [$this, 'adminInitActions']);
        add_action('admin_menu', [$this, 'adminMenuActions']);
        add_action('widgets_init', [$this, 'widgetsInitActions']);
        add_action('wp_dashboard_setup', [$this, 'dashboardSetupActions']);
        add_filter('query_vars', [$this, 'registerQueryVars']);
        add_action('init', [$this, 'initRewrite']);
        add_action('rest_api_init', [$this, 'registerRest']);
        add_action('add_meta_boxes', [$this, 'metaBoxActions']);

        if (defined('WP_DEBUG') && WP_DEBUG) $this->debug();
        $this->end();

        $this->initialized = true;
    }

    /**
     * Get default component ID.
     * @return string Component ID
     */
    protected function getComponentId(): string
    {
        $className = (new \ReflectionClass($this))->getShortName();
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
    }

    /**
     * Get default component name
     * @return string Component name
     */
    protected function getComponentName(): string
    {
        return ucwords(str_replace('_', ' ', $this->componentId));
    }

    protected function getConfig(): array
    {
        return $this->config;
    }

    public function __destruct()
    {
        $this->cleanUp();
    }

    /**
     * Register REST API routes
     * @return void
     * @since 1.0.0
     */
    public function registerRest(): void
    {
        $router = $this->router();
        $router->registerRestRoutes();
    }

    /**
     * Initialize rewrite rules
     * @return void
     * @since 1.0.0
     */
    public function initRewrite(): void
    {
        $rewrite = self::rewrite();
        add_filter('query_vars', [$rewrite, 'registerQueryVars'], 10);
        add_filter('template_include', [$rewrite, 'bindTemplateDispatch'], 99);
        // Auto check and fix rewrite rules in development environment
        if (
            (defined('WP_DEBUG') && WP_DEBUG)
            ||
            (defined('WP_ENVIRONMENT_TYPE')
                && in_array(WP_ENVIRONMENT_TYPE, ['local', 'development']))
        ) {
            add_action('parse_request', [$rewrite, 'checkAndFixRewriteRules'], 1);
        }
    }

    /**
     * Register query variables
     * @param array $vars
     * @return array
     * @since 1.0.0
     */
    public function registerQueryVars(array $vars): array
    {
        return $vars;
    }

    /**
     * Create the component instance
     * 
     * 创建组件实例
     * 
     * @param string $className
     * @return mixed
     * @since 1.0.0
     */
    private static function instance(string $className)
    {
        if (!class_exists($className)) {
            if (WP_DEBUG) {
                wp_die('Sorry! ' . $className . ' Component CLASS does not exit.');
            }
            return;
        } else {
            return new $className();
        }
    }

    /**
     * create a component instance
     * 
     * 创建组件实例
     * 
     * @param string $componentName
     * @return mixed
     * @since 1.0.0
     */
    public static function create(string $componentName)
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

        $component = self::instance($fullClassName);
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
     * Register REST API Routes
     * @return Router
     * @since 1.0.0
     * @author Wang Shai
     */
    private function router(): Router
    {
        static $router = null;
        if (!$router) {
            // Create Main Router (Plugin Controller)
            $router = new Router(
                baseDir: WP_PLUGIN_DIR . "/g3/src/Controllers",
                baseNamespace: "JEALER\\G3\\Controllers"
            );

            // Reflectively scan plugin controllers
            $router->discover();

            // Check and scan theme controllers directory
            $themeControllersDir =
                get_stylesheet_directory() . "/src/Controllers";
            if (file_exists($themeControllersDir)) {
                // Create additional router for theme controllers
                $themeRouter = new Router(
                    baseDir: $themeControllersDir,
                    baseNamespace: "G3\\Controllers"
                );
                // Reflectively scan theme controllers
                $themeRouter->discover();

                // Add theme router to main router
                $router->addRouter($themeRouter);
            }
        }
        return $router;
    }

    /**
     * Register Rewrite Rules
     * @return Rewrite
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function rewrite(): Rewrite
    {
        return Rewrite::getInstance();
    }

    /**
     * Load Plugin Core Files
     * @return
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function loader(): void
    {
        // initialize Components system
        Common::singleton(__CLASS__);

        // load all components
        self::loadComponents();
    }

    /**
     * Load All Components
     * @return 
     * @since 1.0.0
     * @author Wang Shai
     */
    private static function loadComponents(): void
    {
        /**
         * @var array Default component mapping configuration
         */
        $defaultMap = require_once WP_PLUGIN_DIR . "/g3/config/components.php";

        /**
         * @var array User component mapping configuration
         */
        $userMap = file_exists(G3_THEME_CONFIG_DIR . "/components.php")
            ? require_once G3_THEME_CONFIG_DIR . "/components.php"
            : [];

        $componentsMap = array_merge($defaultMap, $userMap);

        self::components($componentsMap);
    }

    /**
     * Load Component files and create instances
     *
     * @param array $componentsMap format as [
     *     'component_name' => true,
     * ]
     *
     * @return array Loaded component instances
     * @since 1.0.0
     * @author Wang Shai
     */
    private static function components(array $componentsMap): array
    {
        $loadedComponents = [];

        foreach ($componentsMap as $componentName => $shouldLoad) {
            // only load component when value is true
            if ($shouldLoad !== true) {
                continue;
            }
            // check component className
            $className     = ucfirst($componentName);
            $componentFile = WP_PLUGIN_DIR . "/g3/src/Components/{$componentName}/{$className}.php";

            if (file_exists($componentFile)) {
                require_once $componentFile;

                $fullClassName = "JEALER\G3\Components\\{$className}";

                if (class_exists($fullClassName)) {
                    /**
                     * modify: Components::create only accept one parameter
                     * Component configuration no longer pass parameters in configuration
                     */
                    $loadedComponents[$componentName] = self::create($className);
                } else {
                    wp_die("G3 Error: Something Wrong with Components Configuration: {$componentName}");
                }
            } else {
                wp_die("G3 Error: Something Wrong with Components Configuration: {$componentName}");
            }
        }

        return $loadedComponents;
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

    public function initActions(): void
    {
        global $loader;
        $this->options();
        if ($loader->admin()) $this->system();
        $this->init();
        $this->postType();
        $this->taxonomy();
    }
    public function adminInitActions(): void
    {
        $this->admin();
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
    protected function start(): void
    {
    }
    protected function system(): void
    {
    }
    protected function front(): void
    {
    }
    protected function debug(): void
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
}