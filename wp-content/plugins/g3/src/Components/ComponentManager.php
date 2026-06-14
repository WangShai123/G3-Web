<?php
namespace JEALER\G3\Components;
use JEALER\G3\Utilities\Common;

class ComponentManager {

    private static ?self $instance = null;

    /** @var Components[] */
    private array $components = [];

    private bool $prepareDataActionsFired = false;

    private function __construct()
    {
        // core lifecycle dispatch hooks
        if (Common::themeModeAvailable()) {
            add_action('after_setup_theme', [$this, 'dispatchPrepareDataActions']);
        } else {
            add_action('plugins_loaded', [$this, 'dispatchPrepareDataActions']);
        }

        add_action('init', [$this, 'dispatchInitActions']);
        add_action('admin_init', [$this, 'dispatchAdminInitActions']);
        add_action('admin_menu', [$this, 'dispatchAdminMenuActions']);
        add_action('widgets_init', [$this, 'dispatchWidgetsInitActions']);
        add_action('wp_dashboard_setup', [$this, 'dispatchDashboardSetupActions']);
        add_action('add_meta_boxes', [$this, 'dispatchMetaBoxActions']);
        add_action('admin_enqueue_scripts', [$this, 'dispatchAdminEnqueueScriptsActions']);
        add_action('wp_enqueue_scripts', [$this, 'dispatchWpEnqueueScriptsActions'], 20);
        add_filter('query_vars', [$this, 'dispatchQueryVars'], 10, 1);
    }

    public static function run(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function registerComponent(Components $component): void
    {
        $componentName = $component->getName();
        if (isset($this->components[$componentName])) {
            return;
        }

        $this->components[$componentName] = $component;
        $this->registerHooks($component);
    }

    private function registerHooks(Components $component): void
    {
        foreach ($this->normalizeHookDefinitions($component->getSubscribedActions()) as $hook => $handlers) {
            foreach ($handlers as $handler) {
                add_action(
                    $hook,
                    $this->buildCallback($component, $handler['callback'], $handler['extra_args']),
                    $handler['priority'],
                    $handler['accepted_args']
                );
            }
        }

        foreach ($this->normalizeHookDefinitions($component->getSubscribedFilters()) as $hook => $handlers) {
            foreach ($handlers as $handler) {
                add_filter(
                    $hook,
                    $this->buildCallback($component, $handler['callback'], $handler['extra_args']),
                    $handler['priority'],
                    $handler['accepted_args']
                );
            }
        }
    }

    private function normalizeHookDefinitions(array $definitions): array
    {
        $result = [];

        foreach ($definitions as $hook => $value) {
            $entries = [];

            if (is_callable($value) || is_string($value)) {
                $entries[] = ['callback' => $value];
            } elseif (is_array($value) && isset($value['callback'])) {
                $entries[] = $value;
            } elseif (is_array($value) && array_is_list($value)) {
                foreach ($value as $item) {
                    if (is_callable($item) || is_string($item)) {
                        $entries[] = ['callback' => $item];
                    } elseif (is_array($item) && isset($item['callback'])) {
                        $entries[] = $item;
                    }
                }
            }

            foreach ($entries as $entry) {
                $result[$hook][] = [
                    'callback'      => $entry['callback'],
                    'priority'      => isset($entry['priority']) ? (int) $entry['priority'] : 10,
                    'accepted_args' => isset($entry['accepted_args']) ? (int) $entry['accepted_args'] : 0,
                    'extra_args'    => isset($entry['extra_args']) && is_array($entry['extra_args']) ? $entry['extra_args'] : [],
                ];
            }
        }

        return $result;
    }

    private function buildCallback(Components $component, callable|string $callback, array $extraArgs): callable
    {
        return function (...$args) use ($component, $callback, $extraArgs) {
            $callable = is_string($callback) ? [$component, $callback] : $callback;
            return $callable(...array_merge($args, $extraArgs));
        };
    }

    public function dispatchPrepareDataActions(): void
    {
        if ($this->prepareDataActionsFired) {
            return;
        }

        foreach ($this->components as $component) {
            $component->prepareDataActions();
        }

        $this->prepareDataActionsFired = true;
    }

    public function dispatchInitActions(): void
    {
        foreach ($this->components as $component) {
            $component->initActions();
        }
    }

    public function dispatchAdminInitActions(): void
    {
        foreach ($this->components as $component) {
            $component->adminInitActions();
        }
    }

    public function dispatchAdminMenuActions(): void
    {
        foreach ($this->components as $component) {
            $component->adminMenuActions();
        }
    }

    public function dispatchWidgetsInitActions(): void
    {
        foreach ($this->components as $component) {
            $component->widgetsInitActions();
        }
    }

    public function dispatchDashboardSetupActions(): void
    {
        foreach ($this->components as $component) {
            $component->dashboardSetupActions();
        }
    }

    public function dispatchMetaBoxActions(): void
    {
        foreach ($this->components as $component) {
            $component->metaBoxActions();
        }
    }

    public function dispatchAdminEnqueueScriptsActions(): void
    {
        foreach ($this->components as $component) {
            $component->adminEnqueueScriptsActions();
        }
    }

    public function dispatchWpEnqueueScriptsActions(): void
    {
        foreach ($this->components as $component) {
            $component->wpEnqueueScriptsActions();
        }
    }

    public function dispatchQueryVars(array $vars): array
    {
        foreach ($this->components as $component) {
            $vars = $component->queryVars($vars);
        }
        return $vars;
    }
}
