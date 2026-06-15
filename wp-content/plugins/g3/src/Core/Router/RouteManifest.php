<?php
namespace JEALER\G3\Core\Router;
use WP_Error;
use Throwable;

class RouteManifest {
    private ControllerClassFinder  $finder;
    private RouteDefinitionBuilder $builder;

    /**
     * @param RouteSource[] $sources
     */
    public function __construct(private array $sources)
    {
        $this->finder  = new ControllerClassFinder();
        $this->builder = new RouteDefinitionBuilder();
    }

    public function load(bool $force = false): array|WP_Error
    {
        try {
            if (!$force && $this->cacheFresh()) {
                $manifest = $this->readCache();
                if (is_array($manifest)) {
                    return $manifest;
                }
            }

            return $this->rebuild();
        }
        catch (RouteConflictException $e) {
            return new WP_Error('g3_rest_route_conflict', $e->getMessage(), [
                'status'    => 500,
                'conflicts' => $e->getConflicts(),
            ]);
        }
        catch (Throwable $e) {
            return new WP_Error('g3_rest_route_manifest_error', $e->getMessage(), [
                'status' => 500,
            ]);
        }
    }

    public function rebuild(): array|WP_Error
    {
        try {
            $classes = [];
            foreach ($this->sources as $source) {
                $classes = array_merge($classes, $this->finder->find($source));
            }

            $this->assertNoClassConflicts($classes);

            $routes = $this->builder->build($classes);
            $this->assertNoConflicts($routes);

            $manifest = [
                'version'      => 1,
                'generated_at' => time(),
                'debug'        => $this->debug(),
                'sources'      => $this->sourceMeta(),
                'routes'       => $routes,
            ];

            $this->writeCache($manifest);
            return $manifest;
        }
        catch (RouteConflictException $e) {
            return new WP_Error('g3_rest_route_conflict', $e->getMessage(), [
                'status'    => 500,
                'conflicts' => $e->getConflicts(),
            ]);
        }
        catch (Throwable $e) {
            return new WP_Error('g3_rest_route_manifest_error', $e->getMessage(), [
                'status' => 500,
            ]);
        }
    }

    public function clear(): bool
    {
        $cleared = false;
        foreach ($this->cachePaths() as $path) {
            if (file_exists($path)) {
                $cleared = @unlink($path) || $cleared;
            }
        }
        return $cleared;
    }

    private function cacheFresh(): bool
    {
        $manifest = $this->readCache();
        if (!is_array($manifest)) {
            return false;
        }

        if (!$this->debug()) {
            return true;
        }

        return ($manifest['sources'] ?? []) === $this->sourceMeta();
    }

    private function readCache(): ?array
    {
        foreach ($this->cachePaths() as $path) {
            if (!file_exists($path)) {
                continue;
            }
            $manifest = require $path;
            return is_array($manifest) ? $manifest : null;
        }
        return null;
    }

    private function writeCache(array $manifest): void
    {
        $export = "<?php\nreturn " . var_export($manifest, true) . ";\n";

        foreach ($this->cachePaths() as $path) {
            $dir = dirname($path);
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                continue;
            }
            if (@file_put_contents($path, $export, LOCK_EX) !== false) {
                return;
            }
        }

        throw new \RuntimeException('Failed to write REST route cache file.');
    }

    /**
     * @return string[]
     */
    private function cachePaths(): array
    {
        return [
            G3_PlUGIN_DIR . '/cache/routes.php',
            WP_CONTENT_DIR . '/uploads/g3-cache/routes.php',
        ];
    }

    private function sourceMeta(): array
    {
        $meta = [];
        foreach ($this->sources as $source) {
            $meta[] = [
                'name'           => $source->name,
                'base_dir'       => $source->baseDir,
                'base_namespace' => $source->baseNamespace,
                'hash'           => $this->sourceHash($source),
            ];
        }
        return $meta;
    }

    private function sourceHash(RouteSource $source): string
    {
        $parts = [];
        foreach ($this->finder->phpFiles($source->baseDir) as $file) {
            $parts[] = implode(':', [
                str_replace(rtrim($source->baseDir, '/') . '/', '', $file->getPathname()),
                $file->getMTime(),
                $file->getSize(),
            ]);
        }
        return sha1(implode('|', $parts));
    }

    private function assertNoConflicts(array $routes): void
    {
        $seen      = [];
        $conflicts = [];

        foreach ($routes as $route) {
            $key = $this->routeKey($route);
            if (isset($seen[$key])) {
                $conflicts[] = [
                    'namespace' => $route['namespace'],
                    'route'     => $this->normalizeRoute($route['route']),
                    'previous'  => $seen[$key],
                    'current'   => $route,
                ];
                continue;
            }
            $seen[$key] = $route;
        }

        if ($conflicts) {
            throw new RouteConflictException($conflicts);
        }
    }

    private function assertNoClassConflicts(array $classes): void
    {
        $seen = [];
        foreach ($classes as $class) {
            if (!isset($seen[$class['class']])) {
                $seen[$class['class']] = $class;
                continue;
            }

            throw new \RuntimeException(sprintf(
                'Duplicate REST controller class %s found in %s (%s) and %s (%s). Rename the theme controller class instead of overriding plugin controllers.',
                $class['class'],
                $seen[$class['class']]['file'],
                $seen[$class['class']]['source'],
                $class['file'],
                $class['source']
            ));
        }
    }

    private function routeKey(array $route): string
    {
        return trim($route['namespace'], '/') . '/' . $this->normalizeRoute($route['route']);
    }

    private function normalizeRoute(string $route): string
    {
        return trim($route, '/');
    }

    private function debug(): bool
    {
        return defined('WP_DEBUG') && WP_DEBUG;
    }
}
