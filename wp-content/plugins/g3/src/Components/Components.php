<?php
namespace JEALER\G3\Components;
use JEALER\G3\Core\ComponentRegistry;
use JEALER\G3\Core\Helper\Helper;
use JEALER\G3\Core\Container\Container;
use Exception;
use ReflectionClass;

abstract class Components {

    /**
     * @var Container|null Container instance, used for dependency injection and service management
     */
    protected ?Container $container = null;

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

        $this->start();
        $this->hooks();
        $this->end();
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
    protected function debug(string $message): void
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

    /**
     * Register batch action subscriptions.
     *
     * @param array<string, mixed> $actions
     * @return void
     */
    protected function action(array $actions): void
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
    protected function filter(array $filters): void
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
    protected function subscribeAction(string $hook, callable|string $callback, int $priority = 10, int $acceptedArgs = 0, array $extraArgs = []): void
    {
        $this->action([
            $hook => [$callback, $priority, $acceptedArgs, $extraArgs],
        ]);
    }

    /**
     * Backward compatibility alias for subscribeFilter.
     */
    protected function subscribeFilter(string $hook, callable|string $callback, int $priority = 10, int $acceptedArgs = 1, array $extraArgs = []): void
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

    public function prepareDataActions(): void
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
        $this->prepareInAdmin();
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
    public function queryVars(array $var)
    {
        return $var;
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
    protected function prepareInAdmin(): void
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
    protected function hooks(): void
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
