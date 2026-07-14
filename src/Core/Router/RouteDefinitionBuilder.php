<?php
namespace JEALER\G3\Core\Router;
use JEALER\G3\Core\Attributes\Middleware;
use JEALER\G3\Core\Attributes\RestRouter;
use JEALER\G3\Core\Attributes\Schema;
use JEALER\G3\Middleware\SchemaMiddleware;
use ReflectionClass;

class RouteDefinitionBuilder {
    /**
     * @param array<int,array{class:string,file:string,source:string}> $classes
     * @return array<int,array>
     */
    public function build(array $classes): array
    {
        $routes = [];

        foreach ($classes as $classInfo) {
            require_once $classInfo['file'];

            if (!class_exists($classInfo['class'])) {
                continue;
            }

            $ref = new ReflectionClass($classInfo['class']);
            if ($ref->isAbstract()) {
                continue;
            }

            foreach ($ref->getMethods() as $method) {
                foreach ($method->getAttributes(RestRouter::class) as $attr) {
                    /** @var RestRouter $cfg */
                    $cfg         = $attr->newInstance();
                    $schema      = $this->schema($method->getAttributes(Schema::class));
                    $middlewares = $this->middlewares($method->getAttributes(Middleware::class));

                    if ($schema) {
                        $middlewares[] = [
                            'class'  => SchemaMiddleware::class,
                            'params' => [$schema],
                        ];
                    }

                    $routes[] = [
                        'source'      => $classInfo['source'],
                        'file'        => $classInfo['file'],
                        'class'       => $classInfo['class'],
                        'method'      => $method->getName(),
                        'namespace'   => $cfg->namespace,
                        'route'       => $cfg->route,
                        'methods'     => is_array($cfg->methods) ? array_values($cfg->methods) : [$cfg->methods],
                        'middlewares' => $middlewares,
                        'schema'      => $schema,
                    ];
                }
            }
        }

        return $routes;
    }

    private function schema(array $attributes): ?array
    {
        if (!$attributes) {
            return null;
        }
        return $attributes[0]->newInstance()->schema;
    }

    private function middlewares(array $attributes): array
    {
        $middlewares = [];
        foreach ($attributes as $attr) {
            /** @var Middleware $cfg */
            $cfg           = $attr->newInstance();
            $middlewares[] = [
                'class'  => $cfg->middleware,
                'params' => $cfg->params,
            ];
        }
        return $middlewares;
    }
}
