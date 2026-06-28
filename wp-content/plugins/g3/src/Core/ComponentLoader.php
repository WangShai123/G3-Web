<?php
namespace JEALER\G3\Core;
use JEALER\G3\Components\ComponentManager;
use JEALER\G3\Components\Components;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Core\Container\FactoryDefinition;
use ReflectionFunction;
use ReflectionMethod;
use Throwable;

/**
 * Component Loader
 *
 * 负责读取组件配置、解析依赖、实例化组件，并注册组件生命周期。
 */
class ComponentLoader {

    /** @var array<int, array{code: string, message: string}> */
    private array $errors = [];

    /** @var array<string, bool> */
    private array $displayedErrors = [];

    private bool $initialized = false;

    private Container $container;

    private ComponentRegistry $registry;

    public function __construct()
    {
        $this->container = Container::run();
        $this->registry  = ComponentRegistry::run();
    }

    public function load(): void
    {
        if ($this->initialized) {
            return;
        }

        $config = $this->getConfig();
        if (($config['enabled'] ?? true) === false) {
            $this->initialized = true;
            return;
        }

        $components = $config['components'] ?? [];
        if (!is_array($components)) {
            $this->addError('g3_components_config_invalid', '[G3 ComponentLoader] components config must be an array.');
            $this->initialized = true;
            return;
        }

        $specs = $this->normalizeComponentSpecs($components);
        $order = $this->resolveLoadOrder($specs);

        foreach ($order as $componentName) {
            $this->loadComponent($specs[$componentName]);
        }

        $this->initialized = true;
    }

    private function getConfig(): array
    {
        $config = $this->loadConfigFile(G3_PLUGIN_DIR . '/config/components.php');

        $themeConfig = get_stylesheet_directory() . '/config/components.php';
        if (file_exists($themeConfig)) {
            $themeData = $this->loadConfigFile($themeConfig);
            $config    = $this->mergeConfig($config, $themeData);
        }

        return $config;
    }

    private function mergeConfig(array $pluginConfig, array $themeConfig): array
    {
        $config = array_replace($pluginConfig, $themeConfig);

        if (!array_key_exists('components', $themeConfig)) {
            return $config;
        }

        $pluginComponents = $pluginConfig['components'] ?? [];
        $themeComponents  = $themeConfig['components'];

        if (is_array($pluginComponents) && is_array($themeComponents)) {
            $config['components'] = $this->mergeComponentConfigs($pluginComponents, $themeComponents);
        }

        return $config;
    }

    private function mergeComponentConfigs(array $pluginComponents, array $themeComponents): array
    {
        $components = $pluginComponents;
        $forced     = $this->forcedPluginComponents($pluginComponents);

        foreach ($themeComponents as $componentName => $definition) {
            if (!is_string($componentName)) {
                $components[$componentName] = $definition;
                continue;
            }

            $key = $this->registry->normalizeName($componentName);
            if (isset($forced[$key])) {
                $this->addError(
                    'g3_component_force_override',
                    sprintf(
                        'Theme config cannot override the built-in component "%s". Remove "%s" from the theme components config or rename it.',
                        $forced[$key],
                        $componentName
                    ),
                    true
                );
                continue;
            }

            $components[$componentName] = $definition;
        }

        return $components;
    }

    /**
     * @return array<string, string>
     */
    private function forcedPluginComponents(array $components): array
    {
        $forced = [];

        foreach ($components as $componentName => $definition) {
            if (!is_string($componentName) || !$this->componentForced($definition)) {
                continue;
            }

            $forced[$this->registry->normalizeName($componentName)] = $componentName;
        }

        return $forced;
    }

    private function componentForced(mixed $definition): bool
    {
        return is_array($definition)
            && array_key_exists('force', $definition)
            && ($definition['force'] === true || $definition['force'] === '1');
    }

    private function loadConfigFile(string $file): array
    {
        try {
            $config = require $file;
        }
        catch (Throwable $e) {
            $this->addError('g3_components_config_load_failed', "[G3 ComponentLoader] Failed to load config file {$file}: " . $e->getMessage());
            return [];
        }

        if (!is_array($config)) {
            $this->addError('g3_components_config_invalid', "[G3 ComponentLoader] Config file must return an array: {$file}");
            return [];
        }

        return $config;
    }

    private function normalizeComponentSpecs(array $components): array
    {
        $specs = [];
        $index = 0;

        foreach ($components as $componentName => $definition) {
            if (!is_string($componentName) || !$this->isValidComponentName($componentName)) {
                $this->addError('g3_component_name_invalid', '[G3 ComponentLoader] Invalid component name: ' . (string) $componentName);
                continue;
            }

            $spec = $this->normalizeComponentSpec($componentName, $definition, $index++);
            if ($spec === null) {
                continue;
            }

            $key = $this->registry->normalizeName($componentName);
            if (isset($specs[$key])) {
                $this->addError('g3_component_duplicate', "[G3 ComponentLoader] Duplicate component config: {$componentName}");
                continue;
            }

            $specs[$key] = $spec;
        }

        return $specs;
    }

    private function normalizeComponentSpec(string $componentName, mixed $definition, int $index): ?array
    {
        $enabled    = false;
        $dependency = true;

        if (is_bool($definition) || $definition === '1' || $definition === '0') {
            $enabled = $definition === true || $definition === '1';
        } elseif (is_array($definition)) {
            if (array_key_exists('enabled', $definition)) {
                $enabled = $definition['enabled'] === true || $definition['enabled'] === '1';
            } elseif (array_key_exists(0, $definition)) {
                $enabled = $definition[0] === true || $definition[0] === '1';
            }

            if (array_key_exists('dependency', $definition)) {
                $dependency = $definition['dependency'];
            } elseif (array_key_exists(1, $definition)) {
                $dependency = $definition[1];
            }
        } else {
            $this->addError('g3_component_definition_invalid', "[G3 ComponentLoader] Invalid component definition for {$componentName}.");
            return null;
        }

        if (!$enabled) {
            return null;
        }

        return [
            'name'       => $componentName,
            'key'        => $this->registry->normalizeName($componentName),
            'class_name' => ucfirst($componentName),
            'dependency' => $dependency,
            'index'      => $index,
            'raw'        => $definition,
        ];
    }

    private function resolveLoadOrder(array $specs): array
    {
        $order     = [];
        $visiting  = [];
        $visited   = [];
        $available = array_fill_keys(array_keys($specs), true);

        uasort($specs, static fn(array $a, array $b): int => $a['index'] <=> $b['index']);

        foreach ($specs as $key => $spec) {
            $this->visitComponent($key, $specs, $available, $visiting, $visited, $order);
        }

        return $order;
    }

    private function visitComponent(string $key, array $specs, array $available, array &$visiting, array &$visited, array &$order): bool
    {
        if (isset($visited[$key])) {
            return $visited[$key];
        }

        if (isset($visiting[$key])) {
            $this->addError('g3_component_dependency_cycle', "[G3 ComponentLoader] Circular component dependency detected at {$specs[$key]['name']}.");
            $visited[$key] = false;
            return false;
        }

        $spec           = $specs[$key];
        $visiting[$key] = true;
        $canLoad        = true;
        $dependencies   = $this->componentDependencyNames($spec['dependency']);

        if ($dependencies !== null) {
            foreach ($dependencies as $dependencyName) {
                $dependencyKey = $this->registry->normalizeName($dependencyName);
                if (!isset($available[$dependencyKey])) {
                    $this->addError('g3_component_dependency_missing', "[G3 ComponentLoader] Component {$spec['name']} depends on missing or disabled component {$dependencyName}.");
                    $canLoad = false;
                    continue;
                }

                if (!$this->visitComponent($dependencyKey, $specs, $available, $visiting, $visited, $order)) {
                    $canLoad = false;
                }
            }
        }

        unset($visiting[$key]);

        if ($canLoad) {
            $order[] = $key;
        }

        $visited[$key] = $canLoad;
        return $canLoad;
    }

    private function loadComponent(array $spec): void
    {
        $componentName = $spec['name'];

        try {
            if (!$this->isDependencySatisfied($spec['dependency'])) {
                return;
            }

            $componentInfo = $this->resolveComponentFile($componentName);
            if (!$componentInfo) {
                $this->addError('g3_component_file_missing', "[G3 ComponentLoader] Component file not found: {$componentName}");
                return;
            }

            require_once $componentInfo['file_path'];

            if (!class_exists($componentInfo['class_name'])) {
                $this->addError('g3_component_class_missing', "[G3 ComponentLoader] Component class not found: {$componentInfo['class_name']}");
                return;
            }

            if (!$this->container->has($componentInfo['class_name'])) {
                $factory = new FactoryDefinition($componentInfo['class_name']);
                $factory->singleton();
                $this->container->setRawDefinition($componentInfo['class_name'], $factory);
            }

            $component = $this->container->get($componentInfo['class_name']);
            if (!$component instanceof Components) {
                $this->addError('g3_component_type_invalid', "[G3 ComponentLoader] Component must extend Components: {$componentInfo['class_name']}");
                return;
            }

            $this->registry->register($componentName, $component);

            ComponentManager::run()->registerComponent($component);
        }
        catch (Throwable $e) {
            $this->addError('g3_component_load_failed', "[G3 ComponentLoader] Failed to load component {$componentName}: " . $e->getMessage());
        }
    }

    private function resolveComponentFile(string $componentName): ?array
    {
        $className = ucfirst($componentName);

        $themeComponentFile = get_stylesheet_directory() . "/src/Components/{$className}/{$className}.php";
        if (file_exists($themeComponentFile)) {
            return [
                'file_path'         => $themeComponentFile,
                'class_name'        => "JEALER\\G3\\Components\\{$className}",
                'is_theme_override' => true,
            ];
        }

        $pluginComponentFile = G3_PLUGIN_DIR . "/src/Components/{$className}/{$className}.php";
        if (file_exists($pluginComponentFile)) {
            return [
                'file_path'         => $pluginComponentFile,
                'class_name'        => "JEALER\\G3\\Components\\{$className}",
                'is_theme_override' => false,
            ];
        }

        return null;
    }

    private function isDependencySatisfied(mixed $dependency): bool
    {
        if ($dependency === null || $dependency === true) {
            return true;
        }

        if ($dependency === false) {
            return false;
        }

        $dependencies = $this->componentDependencyNames($dependency);
        if ($dependencies !== null) {
            foreach ($dependencies as $dependencyName) {
                if (!$this->registry->has($dependencyName)) {
                    return false;
                }
            }

            return true;
        }

        if (!is_callable($dependency)) {
            $this->addError('g3_component_dependency_invalid', '[G3 ComponentLoader] Invalid component dependency definition.');
            return false;
        }

        try {
            return (bool) $this->callDependencyCallback($dependency, [$this->registry, $this]);
        }
        catch (Throwable $e) {
            $this->addError('g3_component_dependency_failed', '[G3 ComponentLoader] Component dependency callback failed: ' . $e->getMessage());
            return false;
        }
    }

    private function componentDependencyNames(mixed $dependency): ?array
    {
        if (is_string($dependency) && $dependency !== '') {
            return [$dependency];
        }

        if ($dependency === []) {
            return [];
        }

        if (!is_array($dependency)) {
            return null;
        }

        if ($this->isCallableArray($dependency)) {
            return null;
        }

        $names = [];
        foreach ($dependency as $name) {
            if (!is_string($name) || $name === '') {
                return null;
            }
            $names[] = $name;
        }

        return $names;
    }

    private function isCallableArray(array $value): bool
    {
        if (
            count($value) !== 2
            || !array_key_exists(0, $value)
            || !array_key_exists(1, $value)
            || !is_string($value[1])
        ) {
            return false;
        }

        [$target, $method] = $value;

        if (is_object($target)) {
            return method_exists($target, $method) || is_callable($value);
        }

        return is_string($target) && str_contains($target, '\\');
    }

    private function callDependencyCallback(callable $callback, array $arguments): mixed
    {
        $reflection = $this->dependencyCallbackReflection($callback);
        if ($reflection === null || $reflection->isVariadic()) {
            return $callback(...$arguments);
        }

        return $callback(...array_slice($arguments, 0, $reflection->getNumberOfParameters()));
    }

    private function dependencyCallbackReflection(callable $callback): ReflectionFunction|ReflectionMethod|null
    {
        try {
            if (is_array($callback)) {
                return new ReflectionMethod($callback[0], $callback[1]);
            }

            if (is_object($callback) && !$callback instanceof \Closure) {
                return new ReflectionMethod($callback, '__invoke');
            }

            return new ReflectionFunction($callback);
        }
        catch (Throwable) {
            return null;
        }
    }

    private function isValidComponentName(string $componentName): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $componentName) === 1;
    }

    private function addError(string $code, string $message, bool $display = false): void
    {
        $this->errors[] = [
            'code'    => $code,
            'message' => $message,
        ];

        $this->registry->addError($code, $message);
        error_log($message);

        if ($display) {
            $this->displayError($code, $message);
        }
    }

    private function displayError(string $code, string $message): void
    {
        if (isset($this->displayedErrors[$code . ':' . $message])) {
            return;
        }

        $this->displayedErrors[$code . ':' . $message] = true;

        if (function_exists('add_action')) {
            add_action('admin_notices', function () use ($message): void {
                echo '<div class="notice notice-error"><p><strong>G3 Component Error:</strong> ' . esc_html($message) . '</p></div>';
            });

            add_action('wp_footer', function () use ($message): void {
                if (function_exists('current_user_can') && !current_user_can('manage_options')) {
                    return;
                }

                echo '<div style="position:fixed;left:16px;right:16px;bottom:16px;z-index:999999;padding:12px 16px;border-left:4px solid #d63638;background:#fff;color:#1d2327;box-shadow:0 2px 12px rgba(0,0,0,.18);font:14px/1.5 -apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;"><strong>G3 Component Error:</strong> ' . esc_html($message) . '</div>';
            });
        }
    }

    public function getLoadedComponents(): array
    {
        return $this->registry->all();
    }

    public function getComponent(string $componentName): ?object
    {
        return $this->registry->get($componentName);
    }

    public function isComponentLoaded(string $componentName): bool
    {
        return $this->registry->has($componentName);
    }

    public function getRegistry(): ComponentRegistry
    {
        return $this->registry;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
