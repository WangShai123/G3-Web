<?php
namespace JEALER\G3\Components;
use JEALER\G3\Core\ComponentRegistry;
use JEALER\G3\Core\Helper\Helper;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Core\Admin\Panel;
use JEALER\G3\Core\Admin\PanelRenderer;
use JEALER\G3\Core\State\StateBag;
use JEALER\G3\Core\State\StateDefinition;
use JEALER\G3\Core\State\StateManager;
use Exception;
use ReflectionClass;

abstract class Components {

    /**
     * @var Container|null Container instance, used for dependency injection and service management
     */
    protected ?Container $container = null;

    protected PanelRenderer $panelRenderer;

    /**
     * @var string component name (automatically inferred)
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

    /**
     * @var array<string, array<int,array>> action subscriptions
     */
    protected array $actionSubscriptions = [];

    /**
     * @var array<string, array<int,array>> filter subscriptions
     */
    protected array $filterSubscriptions = [];

    private ?array $stateDefinitions = null;

    private ?array $adminPanelDefinitions = null;

    public function __construct()
    {
        $this->componentName = $this->getComponentName();
        $this->internalInit();
    }

    /**
     * Automatically infer component name based on class name
     * 
     * 根据类名自动推断组件名称
     * 
     * @return string
     */
    private function getComponentName(): string
    {
        $className = (new ReflectionClass($this))->getShortName();
        return strtolower($className);
    }

    private function internalInit(): void
    {
        if ($this->container === null) {
            $this->container = Container::run();
        }

        // Component registration in the container
        $serviceId = 'component.' . $this->componentName;
        if (!$this->container->has($serviceId)) {
            $this->container->setRawDefinition($serviceId, $this);
        }

        $this->loader = $this->container->get('loader');

        if (!$this->container->has('panelRenderer')) {
            $this->container->setRawDefinition('panelRenderer', new PanelRenderer());
        }
        $this->panelRenderer = $this->container->get('panelRenderer');

        $this->registerComponentStates();

        $this->start();
        $this->hooks();
        $this->end();
    }

    private function registerComponentStates(): void
    {
        $definitions = $this->getStateDefinitions();
        if (empty($definitions)) {
            return;
        }

        StateManager::run()->register($this->componentName, $definitions);
    }

    protected function hydrateStates(): void
    {
        foreach ($this->getStateDefinitions() as $property => $definition) {
            if (!$definition instanceof StateDefinition) {
                continue;
            }

            $this->{$property} = StateManager::run()->bag($this->componentName . '.' . $property)->all();
        }
    }

    /**
     * Get service instance from the container
     * 
     * 从容器中获取服务实例
     * 
     * @param string $serviceClass 服务类名
     * @return object|null
     */
    protected function getService(string $serviceClass): ?object
    {
        try {
            return $this->container->get($serviceClass);
        }
        catch (Exception $e) {
            return null;
        }
    }

    /**
     * Check if service exists
     * 
     * 检查服务是否存在
     * 
     * @param string $serviceClass 服务类名
     * @return bool
     */
    protected function hasService(string $serviceClass): bool
    {
        return $this->container->has($serviceClass);
    }

    /**
     * Debug component log output, only effective when WP_DEBUG is true
     * 
     * 调试组件日志输出，仅在 WP_DEBUG 为 true 时有效
     * 
     * @param string $message 日志信息
     * @return void
     */
    protected function debug(string $message)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[G3 {$this->componentName}] {$message}");
        }
    }

    /**
     * Get component name
     * 
     * 获取组件名称
     * 
     * @return string
     */
    public function getName(): string
    {
        return $this->componentName;
    }

    /**
     * Get container instance
     * 
     * 获取容器实例
     * 
     * @return Container|null
     */
    protected function getContainer(): ?Container
    {
        return $this->container;
    }

    /**
     * Get action hook subscriptions
     * 
     * @return array
     */
    public function getSubscribedActions(): array
    {
        return $this->actionSubscriptions;
    }

    /**
     * Get filter hook subscriptions
     * 
     * @return array
     */
    public function getSubscribedFilters(): array
    {
        return $this->filterSubscriptions;
    }

    public function getStateDefinitions(): array
    {
        if ($this->stateDefinitions === null) {
            $this->stateDefinitions = $this->state();
        }

        return $this->stateDefinitions;
    }

    public function getAdminPanelDefinitions(): array
    {
        if (!$this->shouldLoadAdminPanels()) {
            return [];
        }

        if ($this->adminPanelDefinitions === null) {
            $this->adminPanelDefinitions = $this->adminPanels();
        }

        return $this->adminPanelDefinitions;
    }

    protected function firstPanel(): ?Panel
    {
        return $this->getAdminPanelDefinitions()[0] ?? null;
    }

    protected function adminPanelPage(): string
    {
        return '';
    }

    protected function currentAdminPage(): string
    {
        $page = $_REQUEST['page'] ?? '';
        if (is_array($page)) {
            return '';
        }

        $page = function_exists('wp_unslash') ? wp_unslash($page) : $page;
        return $this->normalizeAdminPage((string) $page);
    }

    protected function shouldLoadAdminPanels(): bool
    {
        $page = $this->adminPanelPage();
        if ($page === '') {
            return true;
        }

        return $this->currentAdminPage() === $this->normalizeAdminPage($page);
    }

    private function normalizeAdminPage(string $page): string
    {
        if (function_exists('sanitize_key')) {
            return sanitize_key($page);
        }

        return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', $page) ?? '');
    }

    protected function optionState(string $optionName, array $defaults = []): StateDefinition
    {
        return StateDefinition::option($optionName, $defaults);
    }

    protected function memoryState(array $defaults = []): StateDefinition
    {
        return StateDefinition::memory($defaults);
    }

    protected function stateBag(string $name): StateBag
    {
        return StateManager::run()->bag($this->componentName . '.' . $name);
    }

    protected function stateValue(string $state, string $key, mixed $default = null): mixed
    {
        return $this->stateBag($state)->get($key, $default);
    }

    protected function panel(string $slug, string $title, string $menuTitle = ''): Panel
    {
        return Panel::make($slug, $title, $menuTitle);
    }

    /**
     * Register batch action subscriptions.
     *
     * @param array<string, mixed> $actions
     * @return void
     */
    protected function action(array $actions)
    {
        foreach ($actions as $hook => $params) {
            $normalized = $this->normalizeHookParams($params, 0);
            if ($normalized === null) {
                continue;
            }
            $this->actionSubscriptions[$hook][] = $normalized;
        }
    }

    /**
     * Register batch filter subscriptions.
     *
     * @param array<string, mixed> $filters
     * @return void
     */
    protected function filter(array $filters)
    {
        foreach ($filters as $hook => $params) {
            $normalized = $this->normalizeHookParams($params, 1);
            if ($normalized === null) {
                continue;
            }
            $this->filterSubscriptions[$hook][] = $normalized;
        }
    }

    private function normalizeHookParams(mixed $params, int $defaultAcceptedArgs): ?array
    {
        $defaults = [
            'callback'      => null,
            'priority'      => 10,
            'accepted_args' => $defaultAcceptedArgs,
            'extra_args'    => [],
        ];

        if (is_callable($params)) {
            $normalized             = $defaults;
            $normalized['callback'] = $params;
            return $normalized;
        }

        if (is_array($params)) {
            if (isset($params['callback'])) {
                $normalized = array_merge($defaults, $params);
                return [
                    'callback'      => $normalized['callback'],
                    'priority'      => (int) $normalized['priority'],
                    'accepted_args' => (int) $normalized['accepted_args'],
                    'extra_args'    => is_array($normalized['extra_args']) ? $normalized['extra_args'] : [],
                ];
            }

            if (isset($params[0]) && is_callable($params[0])) {
                $callback     = $params[0];
                $priority     = isset($params[1]) ? (int) $params[1] : $defaults['priority'];
                $acceptedArgs = isset($params[2]) ? (int) $params[2] : $defaults['accepted_args'];
                $extraArgs    = isset($params[3]) && is_array($params[3]) ? $params[3] : $defaults['extra_args'];
                return [
                    'callback'      => $callback,
                    'priority'      => $priority,
                    'accepted_args' => $acceptedArgs,
                    'extra_args'    => $extraArgs,
                ];
            }
        }

        return null;
    }

    /**
     * Backward compatibility alias for subscribeAction.
     */
    protected function subscribeAction(string $hook, callable|string $callback, int $priority = 10, int $acceptedArgs = 0, array $extraArgs = [])
    {
        $this->action([
            $hook => [$callback, $priority, $acceptedArgs, $extraArgs],
        ]);
    }

    /**
     * Backward compatibility alias for subscribeFilter.
     */
    protected function subscribeFilter(string $hook, callable|string $callback, int $priority = 10, int $acceptedArgs = 1, array $extraArgs = [])
    {
        $this->filter([
            $hook => [$callback, $priority, $acceptedArgs, $extraArgs],
        ]);
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
        $component = ComponentRegistry::run()->get($componentName);
        return $component ? $component->$propertyName : null;
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
        return ComponentRegistry::run()->has($componentName);
    }

    public function prepareDataActions()
    {
        $this->hydrateStates();
        if ($this->loader->admin()) $this->ready();
        $this->options();
        if ($this->loader->x()) $this->x();
        if ($this->loader->y()) $this->y();
    }

    public function initActions()
    {
        if ($this->loader->admin()) $this->system();
        $this->init();
        if (!is_admin()) $this->front();
        $this->postType();
        $this->taxonomy();
    }
    public function adminInitActions()
    {
        $this->saveAdminPanelStates();
        $this->hydrateStates();
        $this->prepareInAdmin();
        if ($this->loader->admin()) $this->admin();
        $this->form();
        $this->ajax();
        $this->settings();
    }
    public function adminMenuActions()
    {
        $this->adminMenu();
    }
    public function widgetsInitActions()
    {
        add_theme_support('widgets');
        $this->sidebar();
        $this->widgets();
    }
    public function dashboardSetupActions()
    {
        $this->dashboard();
    }
    public function metaBoxActions()
    {
        $this->metaBox();
    }
    public function adminEnqueueScriptsActions()
    {
        $this->adminScripts();
    }
    public function wpEnqueueScriptsActions()
    {
        $this->scripts();
    }
    public function queryVars(array $var)
    {
        return $var;
    }
    public function registerUMDFilters(array $scripts): array
    {
        return $this->registerUMD($scripts);
    }
    public function registerESMFilters(array $modules): array
    {
        return $this->registerESM($modules);
    }
    public function registerCSSFilters(array $styles): array
    {
        return $this->registerCSS($styles);
    }

    protected function ready()
    {
    }
    protected function start()
    {
    }
    protected function system()
    {
    }
    protected function front()
    {
    }
    protected function cleanUp()
    {
    }
    protected function x()
    {
    }
    protected function y()
    {
    }
    protected function end()
    {
    }
    protected function options()
    {
    }
    protected function state(): array
    {
        return [];
    }
    protected function adminPanels(): array
    {
        return [];
    }
    protected function form()
    {
    }
    protected function init()
    {
    }
    protected function prepareInAdmin()
    {
    }
    protected function admin()
    {
    }
    protected function adminMenu()
    {
    }
    protected function sidebar()
    {
    }
    protected function hooks()
    {
    }
    protected function widgets()
    {
    }
    protected function dashboard()
    {
    }
    protected function metaBox()
    {
    }
    protected function postType()
    {
    }
    protected function taxonomy()
    {
    }
    protected function ajax()
    {
    }
    protected function settings()
    {
        foreach ($this->getAdminPanelDefinitions() as $panel) {
            if (!$panel instanceof Panel) {
                continue;
            }

            $this->panelRenderer->register($this->componentName, $panel);
        }
    }
    protected function createPanel(): PanelRenderer
    {
        return $this->panelRenderer;
    }
    protected function adminScripts()
    {
    }
    protected function scripts()
    {
    }
    protected function registerUMD($scripts)
    {
        return $scripts;
    }
    protected function registerESM($modules)
    {
        return $modules;
    }
    protected function registerCSS($styles)
    {
        return $styles;
    }

    private function saveAdminPanelStates(): void
    {
        foreach ($this->getAdminPanelDefinitions() as $panel) {
            if (!$panel instanceof Panel) {
                continue;
            }

            $this->panelRenderer->saveSubmitted($this->componentName, $panel);
        }
    }
}
