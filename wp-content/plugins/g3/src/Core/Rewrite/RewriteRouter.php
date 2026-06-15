<?php
namespace JEALER\G3\Core\Rewrite;

use JEALER\G3\Components\Components;
use JEALER\G3\Core\ComponentRegistry;
use JEALER\G3\Core\Container\Container;
use ReflectionMethod;
use Throwable;
use WP_Error;

class RewriteRouter {
    private const ROUTE_QUERY_VAR = 'g3_rewrite_route';
    private const ERROR_OPTION    = 'g3_rewrite_last_error';

    private array     $config    = [];
    private array     $routes    = [];
    private ?array    $activeRoutesCache = null;
    private ?WP_Error $lastError = null;
    private Container $container;
    private ComponentRegistry $componentRegistry;

    public function __construct()
    {
        if (!isset($this->container)) {
            $this->container = Container::run();
        }

        $this->componentRegistry = ComponentRegistry::run();
        $this->reload();
    }

    public function reload(): void
    {
        $this->config            = [];
        $this->routes            = [];
        $this->activeRoutesCache = null;
        $this->lastError         = null;

        $this->loadConfig();
    }

    private function loadConfig(): void
    {
        $pluginConfigFile = $this->pluginDir() . '/config/rewriteRouter.php';
        $themeConfigFile  = $this->themeDir() . '/config/rewriteRouter.php';

        $pluginConfig = $this->loadConfigFile($pluginConfigFile, true);
        if ($pluginConfig instanceof WP_Error) {
            $this->lastError = $pluginConfig;
            return;
        }

        $themeConfig = $this->loadConfigFile($themeConfigFile, false);
        if ($themeConfig instanceof WP_Error) {
            $this->lastError = $themeConfig;
            return;
        }

        [$config, $sourceMap] = $this->mergeConfigSources($pluginConfig, $themeConfig);
        $routes               = $this->normalizeRoutes($config, $sourceMap);

        if ($routes instanceof WP_Error) {
            $this->lastError = $routes;
            return;
        }

        $this->config = $config;
        $this->routes = $routes;
    }

    private function loadConfigFile(string $file, bool $required): array|WP_Error
    {
        if (!file_exists($file)) {
            if ($required) {
                return new WP_Error(
                    'g3_rewrite_config_missing',
                    sprintf('[G3 Rewrite] Required rewrite config file not found: %s', $file)
                );
            }

            return [];
        }

        $config = require $file;
        if (!is_array($config)) {
            return new WP_Error(
                'g3_rewrite_config_invalid',
                sprintf('[G3 Rewrite] Rewrite config file must return an array: %s', $file)
            );
        }

        return $config;
    }

    private function mergeConfigSources(array $pluginConfig, array $themeConfig): array
    {
        $config    = [];
        $sourceMap = [];

        foreach ($pluginConfig as $pattern => $route) {
            if ($this->routeDisabled($route)) {
                continue;
            }

            $config[$pattern]    = $route;
            $sourceMap[$pattern] = 'plugin';
        }

        foreach ($themeConfig as $pattern => $route) {
            if ($this->routeDisabled($route)) {
                unset($config[$pattern], $sourceMap[$pattern]);
                continue;
            }

            $config[$pattern]    = $route;
            $sourceMap[$pattern] = 'theme';
        }

        return [$config, $sourceMap];
    }

    private function routeDisabled(mixed $route): bool
    {
        return $route === false || (is_array($route) && array_key_exists('enabled', $route) && $route['enabled'] === false);
    }

    private function normalizeRoutes(array $config, array $sourceMap): array|WP_Error
    {
        $routes = [];

        foreach ($config as $pattern => $route) {
            $normalized = $this->normalizeRoute($pattern, $route, $sourceMap[$pattern] ?? 'plugin');
            if ($normalized instanceof WP_Error) {
                return $normalized;
            }

            $routeId = $normalized['id'];
            if (isset($routes[$routeId])) {
                return new WP_Error(
                    'g3_rewrite_route_id_conflict',
                    sprintf('[G3 Rewrite] Duplicate route id generated for pattern: %s', $pattern)
                );
            }

            $routes[$routeId] = $normalized;
        }

        return $routes;
    }

    private function normalizeRoute(int|string $pattern, mixed $route, string $source): array|WP_Error
    {
        if (!is_string($pattern) || trim($pattern) === '') {
            return new WP_Error('g3_rewrite_pattern_invalid', '[G3 Rewrite] Rewrite pattern must be a non-empty string.');
        }

        if (!is_array($route)) {
            return new WP_Error(
                'g3_rewrite_route_invalid',
                sprintf('[G3 Rewrite] Rewrite route must be an array for pattern: %s', $pattern)
            );
        }

        if (!array_key_exists('var', $route) || !array_key_exists('path', $route)) {
            return new WP_Error(
                'g3_rewrite_route_required_keys_missing',
                sprintf('[G3 Rewrite] Rewrite route requires "var" and "path" keys for pattern: %s', $pattern)
            );
        }

        $vars = $this->normalizeVars($route['var']);
        if ($vars instanceof WP_Error) {
            return $vars;
        }

        if (!is_string($route['path']) || trim($route['path']) === '') {
            return new WP_Error(
                'g3_rewrite_template_invalid',
                sprintf('[G3 Rewrite] Rewrite route path must be a non-empty string for pattern: %s', $pattern)
            );
        }

        $priority = $this->normalizePriority($route['priority'] ?? [], $pattern);
        if ($priority instanceof WP_Error) {
            return $priority;
        }

        $routeId = $this->routeId($pattern, $vars);

        return [
            'id'         => $routeId,
            'source'     => $source,
            'pattern'    => $pattern,
            'vars'       => $vars,
            'query'      => $this->buildQuery($routeId, $vars),
            'path'       => $route['path'],
            'priority'   => $priority,
            'dependency' => $route['dependency'] ?? true,
            'position'   => $this->normalizePosition($route['position'] ?? 'top'),
            'raw'        => $route,
        ];
    }

    private function normalizeVars(mixed $vars): array|WP_Error
    {
        $vars = is_array($vars) ? array_values($vars) : [$vars];
        if ($vars === []) {
            return new WP_Error('g3_rewrite_vars_empty', '[G3 Rewrite] Rewrite route vars cannot be empty.');
        }

        foreach ($vars as $var) {
            if (!is_string($var) || trim($var) === '') {
                return new WP_Error('g3_rewrite_var_invalid', '[G3 Rewrite] Every rewrite query var must be a non-empty string.');
            }
        }

        return $vars;
    }

    private function normalizePriority(mixed $priority, string $pattern): array|WP_Error
    {
        if ($priority === null) {
            return [];
        }

        if (!is_array($priority)) {
            return new WP_Error(
                'g3_rewrite_priority_invalid',
                sprintf('[G3 Rewrite] Rewrite priority must be an array for pattern: %s', $pattern)
            );
        }

        foreach ($priority as $entry) {
            if (!is_array($entry)) {
                return new WP_Error(
                    'g3_rewrite_priority_entry_invalid',
                    sprintf('[G3 Rewrite] Every rewrite priority entry must be an array for pattern: %s', $pattern)
                );
            }

            if (isset($entry['path']) && (!is_string($entry['path']) || trim($entry['path']) === '')) {
                return new WP_Error(
                    'g3_rewrite_priority_path_invalid',
                    sprintf('[G3 Rewrite] Rewrite priority path must be a non-empty string for pattern: %s', $pattern)
                );
            }
        }

        return array_values($priority);
    }

    private function buildQuery(string $routeId, array $vars): string
    {
        $queryParts = [self::ROUTE_QUERY_VAR . '=' . $routeId];

        foreach ($vars as $index => $varName) {
            $queryParts[] = $varName . '=$matches[' . ($index + 1) . ']';
        }

        return 'index.php?' . implode('&', $queryParts);
    }

    private function normalizePosition(mixed $position): string
    {
        return $position === 'bottom' ? 'bottom' : 'top';
    }

    private function routeId(string $pattern, array $vars): string
    {
        return 'g3_' . substr(sha1($pattern . '|' . implode('|', $vars)), 0, 12);
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function getLastError(): ?WP_Error
    {
        return $this->lastError;
    }

    public function registerRewriteRules(): void
    {
        if ($this->lastError instanceof WP_Error) {
            $this->logError($this->lastError);
            return;
        }

        foreach ($this->activeRoutes() as $route) {
            add_rewrite_rule($route['pattern'], $route['query'], $route['position']);
        }
    }

    public static function flushRewriteRules(bool $hard = true): true|WP_Error
    {
        try {
            $container = Container::run();
            $instance  = $container->has('rewrite') ? $container->get('rewrite') : new self();
        }
        catch (Throwable $e) {
            error_log('[G3 Rewrite] Container access failed, creating direct instance: ' . $e->getMessage());
            $instance = new self();
        }

        if (!$instance instanceof self) {
            return new WP_Error('g3_rewrite_instance_invalid', '[G3 Rewrite] Rewrite service is not a RewriteRouter instance.');
        }

        $instance->reload();
        if ($instance->lastError instanceof WP_Error) {
            $instance->storeError($instance->lastError);
            $instance->logError($instance->lastError);
            return $instance->lastError;
        }

        $instance->registerRewriteRules();
        flush_rewrite_rules($hard);
        delete_option(self::ERROR_OPTION);

        return true;
    }

    public function checkAndFixRewriteRules(): void
    {
        if ($this->lastError instanceof WP_Error) {
            $this->storeError($this->lastError);
            $this->logError($this->lastError);
            return;
        }

        if (!$this->verifyRewriteRules()) {
            $result = self::flushRewriteRules(false);
            if ($result instanceof WP_Error) {
                $this->storeError($result);
                $this->logError($result);
            }
        }
    }

    private function verifyRewriteRules(): bool
    {
        $rules = $this->storedRewriteRules();
        if (!is_array($rules)) {
            return false;
        }

        $activeRoutes = $this->activeRoutes();
        foreach ($activeRoutes as $route) {
            if (!isset($rules[$route['pattern']]) || $rules[$route['pattern']] !== $route['query']) {
                return false;
            }
        }

        return !$this->hasStaleG3Rules($rules, array_keys($activeRoutes));
    }

    private function storedRewriteRules(): ?array
    {
        $rules = get_option('rewrite_rules');
        if (is_array($rules)) {
            return $rules;
        }

        global $wp_rewrite;
        return isset($wp_rewrite->rules) && is_array($wp_rewrite->rules) ? $wp_rewrite->rules : null;
    }

    private function hasStaleG3Rules(array $rules, array $activeRouteIds): bool
    {
        $activeRouteIds = array_flip($activeRouteIds);

        foreach ($rules as $query) {
            if (!is_string($query)) {
                continue;
            }

            if (!preg_match('/(?:^|[?&])' . preg_quote(self::ROUTE_QUERY_VAR, '/') . '=([^&]+)/', $query, $matches)) {
                continue;
            }

            if (!isset($activeRouteIds[$matches[1]])) {
                return true;
            }
        }

        return false;
    }

    private function storeError(WP_Error $error): void
    {
        update_option(self::ERROR_OPTION, [
            'code'    => $error->get_error_code(),
            'message' => $error->get_error_message(),
        ], false);
    }

    public function registerQueryVars(array $vars): array
    {
        if ($this->lastError instanceof WP_Error) {
            return $vars;
        }

        $vars[] = self::ROUTE_QUERY_VAR;
        foreach ($this->activeRoutes() as $route) {
            $vars = array_merge($vars, $route['vars']);
        }

        return array_values(array_unique($vars));
    }

    public function bindTemplateDispatch(string $template): string
    {
        if ($this->lastError instanceof WP_Error) {
            return $template;
        }

        global $wp_query;
        $queryVars = is_object($wp_query) && isset($wp_query->query_vars) && is_array($wp_query->query_vars)
            ? $wp_query->query_vars
            : [];

        $route = $this->matchedRoute($queryVars);
        if ($route === null) {
            return $template;
        }

        $templateFile = $this->selectTemplateFile($route, $queryVars);
        $templatePath = $this->resolveTemplatePath($templateFile);

        return $templatePath ?: $template;
    }

    private function matchedRoute(array $queryVars): ?array
    {
        $routeId = $queryVars[self::ROUTE_QUERY_VAR] ?? null;
        $activeRoutes = $this->activeRoutes();
        if (is_string($routeId) && isset($activeRoutes[$routeId])) {
            return $activeRoutes[$routeId];
        }

        foreach ($activeRoutes as $route) {
            if ($this->routeMatchesQueryVars($route, $queryVars)) {
                return $route;
            }
        }

        return null;
    }

    private function routeMatchesQueryVars(array $route, array $queryVars): bool
    {
        foreach ($route['vars'] as $varName) {
            if (array_key_exists($varName, $queryVars) && $queryVars[$varName] !== '' && $queryVars[$varName] !== null) {
                return true;
            }
        }

        return false;
    }

    private function selectTemplateFile(array $route, array $queryVars): string
    {
        $context = $this->routeContext($route, $queryVars);

        foreach ($route['priority'] as $priority) {
            $template = $this->priorityTemplate($priority, $context);
            if (is_string($template) && $template !== '') {
                return $template;
            }
        }

        return $route['path'];
    }

    private function priorityTemplate(array $priority, array $context): ?string
    {
        if (array_key_exists('callback', $priority)) {
            $result = $this->invokeConfigCallback($priority['callback'], $context);

            if (is_array($result) && isset($result['path']) && is_string($result['path'])) {
                return $result['path'];
            }

            if (is_string($result) && $result !== '') {
                return $result;
            }

            if ($result === true && isset($priority['path']) && is_string($priority['path'])) {
                return $priority['path'];
            }

            return null;
        }

        if (!array_key_exists('value', $priority) || !isset($priority['path']) || !is_string($priority['path'])) {
            return null;
        }

        $expected = $priority['value'];
        if ($this->isPriorityValueCallback($expected)) {
            $expected = $this->invokeConfigCallback($expected, $context);
            if ($expected === true) {
                return $priority['path'];
            }
        }

        return $this->priorityValueMatches($expected, $priority, $context) ? $priority['path'] : null;
    }

    private function priorityValueMatches(mixed $expected, array $priority, array $context): bool
    {
        if ($expected === false || $expected === null) {
            return false;
        }

        $actual = $context['value'];
        if (isset($priority['var']) && is_string($priority['var'])) {
            $actual = $context['values'][$priority['var']] ?? null;
        }

        if (is_array($expected)) {
            return in_array($actual, $expected, false);
        }

        return $expected == $actual;
    }

    private function routeContext(array $route, array $queryVars): array
    {
        $values = [];
        $value  = null;

        foreach ($route['vars'] as $varName) {
            if (!array_key_exists($varName, $queryVars)) {
                continue;
            }

            $values[$varName] = $queryVars[$varName];
            if ($value === null && $queryVars[$varName] !== '' && $queryVars[$varName] !== null) {
                $value = $queryVars[$varName];
            }
        }

        return [
            'value'      => $value,
            'values'     => $values,
            'route'      => $route,
            'query_vars' => $queryVars,
        ];
    }

    private function resolveTemplatePath(string $templateFile): ?string
    {
        if ($this->unsafeTemplatePath($templateFile)) {
            $this->logMessage('[G3 Rewrite] Unsafe template path rejected: ' . $templateFile);
            return null;
        }

        foreach ([$this->themeDir() . '/templates', $this->pluginDir() . '/templates'] as $baseDir) {
            $base = realpath($baseDir);
            if ($base === false) {
                continue;
            }

            $candidate = $base . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $templateFile), DIRECTORY_SEPARATOR);
            $real      = realpath($candidate);
            if ($real !== false && ($real === $base || str_starts_with($real, $base . DIRECTORY_SEPARATOR))) {
                return $real;
            }
        }

        return null;
    }

    private function unsafeTemplatePath(string $templateFile): bool
    {
        return $templateFile === ''
            || str_contains($templateFile, "\0")
            || str_starts_with($templateFile, '/')
            || preg_match('#(^|[\\/])\.\.([\\/]|$)#', $templateFile) === 1;
    }

    private function activeRoutes(): array
    {
        if ($this->activeRoutesCache !== null) {
            return $this->activeRoutesCache;
        }

        $routes = [];
        foreach ($this->routes as $routeId => $route) {
            if ($this->isDependencySatisfied($route['dependency'])) {
                $routes[$routeId] = $route;
            }
        }

        $this->activeRoutesCache = $routes;
        return $this->activeRoutesCache;
    }

    private function isDependencySatisfied(mixed $dependency): bool
    {
        if ($dependency === null) {
            return true;
        }

        if (is_bool($dependency)) {
            return $dependency;
        }

        if (is_string($dependency)) {
            return $this->componentRegistry->has($dependency);
        }

        if (is_array($dependency) && $this->isComponentDependencyList($dependency)) {
            foreach ($dependency as $componentName) {
                if (!$this->componentRegistry->has($componentName)) {
                    return false;
                }
            }

            return true;
        }

        $callback = $this->resolveConfigCallback($dependency, false);
        if ($callback === null) {
            $this->logMessage('[G3 Rewrite] Invalid dependency definition rejected.');
            return false;
        }

        try {
            return (bool) $this->callWithContext($callback, [null, [], [], []]);
        }
        catch (Throwable $e) {
            $this->logMessage('[G3 Rewrite] Dependency callback failed: ' . $e->getMessage());
            return false;
        }
    }

    private function invokeConfigCallback(mixed $callback, array $context): mixed
    {
        $callable = $this->resolveConfigCallback($callback);
        if ($callable === null) {
            return null;
        }

        try {
            return $this->callWithContext($callable, [
                $context['value'],
                $context['values'],
                $context['route'],
                $context['query_vars'],
            ]);
        }
        catch (Throwable $e) {
            $this->logMessage('[G3 Rewrite] Priority callback failed: ' . $e->getMessage());
            return null;
        }
    }

    private function resolveConfigCallback(mixed $callback, bool $allowFunctionString = true): ?callable
    {
        if (is_string($callback) && str_contains($callback, '::')) {
            [$className, $method] = explode('::', $callback, 2);
            $callback             = [$className, $method];
        }

        if (is_string($callback)) {
            return $allowFunctionString && is_callable($callback) ? $callback : null;
        }

        if ($callback instanceof \Closure || (is_object($callback) && is_callable($callback))) {
            return $callback;
        }

        if (
            !is_array($callback)
            || count($callback) !== 2
            || !array_key_exists(0, $callback)
            || !array_key_exists(1, $callback)
            || !is_string($callback[1])
        ) {
            return is_callable($callback) ? $callback : null;
        }

        [$target, $method] = $callback;
        if (is_object($target)) {
            return method_exists($target, $method) ? [$target, $method] : null;
        }

        if (!is_string($target) || !class_exists($target) || !method_exists($target, $method)) {
            return null;
        }

        $reflection = new ReflectionMethod($target, $method);
        if ($reflection->isStatic()) {
            return [$target, $method];
        }

        $component = $this->loadedComponent($target);
        if ($component !== null) {
            return [$component, $method];
        }

        if (is_subclass_of($target, Components::class)) {
            return null;
        }

        try {
            $instance = $this->container->get($target);
            return [$instance, $method];
        }
        catch (Throwable $e) {
            $this->logMessage('[G3 Rewrite] Callback service resolution failed: ' . $e->getMessage());
            return null;
        }
    }

    private function loadedComponent(string $className): ?object
    {
        if (!is_subclass_of($className, Components::class)) {
            return null;
        }

        $componentName = (new \ReflectionClass($className))->getShortName();
        return $this->componentRegistry->get($componentName);
    }

    private function callWithContext(callable $callable, array $arguments): mixed
    {
        $reflection = $this->callbackReflection($callable);
        if ($reflection === null) {
            return $callable(...$arguments);
        }

        if ($reflection->isVariadic()) {
            return $callable(...$arguments);
        }

        return $callable(...array_slice($arguments, 0, $reflection->getNumberOfParameters()));
    }

    private function callbackReflection(callable $callable): \ReflectionFunction|ReflectionMethod|null
    {
        try {
            if (is_array($callable)) {
                return new ReflectionMethod($callable[0], $callable[1]);
            }

            if (is_object($callable) && !$callable instanceof \Closure) {
                return new ReflectionMethod($callable, '__invoke');
            }

            return new \ReflectionFunction($callable);
        }
        catch (Throwable) {
            return null;
        }
    }

    private function isPriorityValueCallback(mixed $value): bool
    {
        return $this->isCallbackReference($value);
    }

    private function isCallbackReference(mixed $value): bool
    {
        if ($value instanceof \Closure || (is_object($value) && is_callable($value))) {
            return true;
        }

        if (is_string($value) && str_contains($value, '::')) {
            return true;
        }

        return is_array($value)
            && count($value) === 2
            && array_key_exists(0, $value)
            && array_key_exists(1, $value)
            && is_string($value[1])
            && (
                (is_object($value[0]) && method_exists($value[0], $value[1]))
                || (is_string($value[0]) && str_contains($value[0], '\\'))
            );
    }

    private function isComponentDependencyList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        if ($this->isCallbackReference($value)) {
            return false;
        }

        foreach ($value as $componentName) {
            if (!is_string($componentName) || $componentName === '') {
                return false;
            }
        }

        return true;
    }

    private function pluginDir(): string
    {
        return defined('G3_PlUGIN_DIR') ? G3_PlUGIN_DIR : WP_PLUGIN_DIR . '/g3';
    }

    private function themeDir(): string
    {
        return function_exists('get_stylesheet_directory') ? get_stylesheet_directory() : '';
    }

    private function logError(WP_Error $error): void
    {
        $this->logMessage($error->get_error_message());
    }

    private function logMessage(string $message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($message);
        }
    }
}
