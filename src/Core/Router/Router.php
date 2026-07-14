<?php
namespace JEALER\G3\Core\Router;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Core\Container\FactoryDefinition;
use WP_Error;
use WP_REST_Request;

class Router {
    private Container $container;

    /**
     * @var RouteSource[]
     */
    private array $sources = [];

    /**
     * @var array<int,array>
     */
    private array $restDefs = [];

    private ?WP_Error      $lastError = null;
    private ?RouteManifest $manifest  = null;

    /**
     * @param RouteSource[]|string $sources
     */
    public function __construct(
        array|string $sources,
        ?string $baseNamespace = null,
        mixed $container = null
    )
    {
        $this->container = $container instanceof Container ? $container : Container::run();

        if (is_string($sources)) {
            $this->sources[] = new RouteSource('main', rtrim($sources, '/'), (string) $baseNamespace);
        } else {
            $this->sources = $sources;
        }
    }

    public function addSource(RouteSource $source): void
    {
        $this->sources[] = $source;
        $this->manifest  = null;
    }

    /**
     * Backward-compatible adapter for old additional router usage.
     */
    public function addRouter(Router $router): void
    {
        foreach ($router->getSources() as $source) {
            $this->addSource($source);
        }
    }

    /**
     * @return RouteSource[]
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    public function discover(bool $force = false): bool|WP_Error
    {
        $manifest = $this->manifest()->load($force);
        if (is_wp_error($manifest)) {
            $this->lastError = $manifest;
            $this->restDefs  = [];
            return $manifest;
        }

        $this->lastError = null;
        $this->restDefs  = $manifest['routes'] ?? [];
        return true;
    }

    public function rebuildCache(): bool|WP_Error
    {
        $manifest = $this->manifest()->rebuild();
        if (is_wp_error($manifest)) {
            $this->lastError = $manifest;
            return $manifest;
        }

        $this->lastError = null;
        $this->restDefs  = $manifest['routes'] ?? [];
        return true;
    }

    public function clearCache(): bool
    {
        return $this->manifest()->clear();
    }

    public function registerRestRoutes(): void
    {
        if (!$this->restDefs && !$this->lastError) {
            $this->discover();
        }

        if ($this->lastError) {
            $this->registerErrorRoute($this->lastError);
            return;
        }

        foreach ($this->restDefs as $def) {
            register_rest_route($def['namespace'], $def['route'], [
                'methods'             => $def['methods'],
                'callback'            => function (WP_REST_Request $req) use ($def) {
                    foreach ($def['middlewares'] as $middlewareDef) {
                        $middlewareClass = $middlewareDef['class'];
                        $params          = $middlewareDef['params'] ?? [];

                        if (!class_exists($middlewareClass)) {
                            continue;
                        }

                        $middleware = !empty($params)
                            ? new $middlewareClass(...$params)
                            : new $middlewareClass();

                        if (method_exists($middleware, 'handle')) {
                            $result = $middleware->handle($req);
                            if ($result !== true) {
                                return $result;
                            }
                        }
                    }

                    $controllerClass = $def['class'];
                    $factory         = new FactoryDefinition($controllerClass);
                    $factory->singleton(false);
                    $this->container->setRawDefinition($controllerClass, $factory);
                    $controller = $this->container->get($controllerClass);

                    return $controller->{$def['method']}($req);
                },
                'permission_callback' => '__return_true',
            ]);
        }
    }

    /**
     * @return array<int,array>
     */
    public function getRestDefs(): array
    {
        return $this->restDefs;
    }

    public function getLastError(): ?WP_Error
    {
        return $this->lastError;
    }

    private function manifest(): RouteManifest
    {
        if (!$this->manifest) {
            $this->manifest = new RouteManifest($this->sources);
        }
        return $this->manifest;
    }

    private function registerErrorRoute(WP_Error $error): void
    {
        register_rest_route('api/v1', '(?P<g3_router_failed_route>.*)', [
            'methods'             => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'callback'            => fn() => $error,
            'permission_callback' => '__return_true',
        ]);
    }
}
