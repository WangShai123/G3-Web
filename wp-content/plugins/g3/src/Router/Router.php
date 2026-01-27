<?php
namespace JEALER\G3\Router;

use ReflectionClass;
use JEALER\G3\Attributes\RestRouter;
use JEALER\G3\Attributes\Middleware;
use JEALER\G3\Attributes\Schema;
use JEALER\G3\Attributes\Inject;
use Psr\Container\ContainerInterface;
use JEALER\G3\Container\Container;
use JEALER\G3\Container\ContainerBuilder;
use JEALER\G3\Container\FactoryDefinition;
use JEALER\G3\Middleware\SchemaMiddleware;
use WP_REST_Request;
use RuntimeException;
use SplFileInfo;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

/**
 * REST API Router, Easy to build RESTful API by Controller Class
 * 
 * 重构 Rest API 路由书写方式，用户可使用 Controller 类，便捷定义 RESTful API
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class Router {
    // private ContainerInterface $container;
    private Container $container;
    private string $baseDir;
    private string $baseNamespace;

    /**
     * Each REST definition
     * 
     * 每个 REST 定义
     * 
     * @var array<int,array>
     */
    private array $restDefs = [];

    /**
     * Additional routers
     * 
     * 额外的路由器实例
     * 
     * @var Router[]
     */
    private array $additionalRouters = [];

    public function __construct(
        string $baseDir,
        string $baseNamespace,
        #[Inject] Container $container // 自动注入 JEALER\G3\Container
    ) {
        $this->baseDir       = rtrim($baseDir, '/');
        $this->baseNamespace = trim($baseNamespace, '\\');
        $this->container     = $container;
    }

    /**
     * Add additional router instance
     * 
     * 添加额外的路由器实例
     * 
     * @param Router $router 额外的路由器实例
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public function addRouter(Router $router): void
    {
        $this->additionalRouters[] = $router;
    }

    /**
     * Recursively require PHP files in the controller directory and reflect to collect Attributes definitions
     * 
     * 递归 require 控制器目录，并反射搜集 Attributes 定义
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public function discover(): void
    {
        // ensure middleware related classes are loaded
        $this->requireMiddlewareClasses();
        // ensure schema related classes are loaded
        $this->requireSchemaClasses();

        $this->requirePhpFiles($this->baseDir);

        // iterate over all declared classes to find those belonging to our namespace
        foreach (get_declared_classes() as $class) {
            if (!str_starts_with($class, $this->baseNamespace . '\\')) continue;
            $ref = new ReflectionClass($class);
            if ($ref->isAbstract()) continue;
            foreach ($ref->getMethods() as $m) {
                // REST
                foreach ($m->getAttributes(RestRouter::class) as $attr) {
                    /** @var RestRouter $cfg */
                    $cfg     = $attr->newInstance();
                    $methods = \is_array($cfg->methods) ? $cfg->methods : [$cfg->methods];

                    // get schema
                    $schemaAttr = $m->getAttributes(Schema::class);
                    $schema     = null;
                    if ($schemaAttr) {
                        $schema = $schemaAttr[0]->newInstance()->schema;
                    }

                    // get middlewares
                    $middlewares = [];
                    foreach ($m->getAttributes(Middleware::class) as $middlewareAttr) {
                        /** @var Middleware $middlewareCfg */
                        $middlewareCfg = $middlewareAttr->newInstance();
                        $middlewares[] = [
                            'class'  => $middlewareCfg->middleware,
                            'params' => $middlewareCfg->params
                        ];
                    }

                    // if method defined Schema, attach SchemaMiddleware automatically
                    if ($schema) {
                        $middlewares[] = [
                            'class'  => SchemaMiddleware::class,
                            'params' => [$schema]
                        ];
                    }

                    $this->restDefs[] = [
                        'class'       => $class,
                        'method'      => $m->getName(),
                        'namespace'   => $cfg->namespace,
                        'route'       => $cfg->route,
                        'methods'     => $methods,
                        // 'name'        => $cfg->name ?: ($class . '@' . $m->getName()),
                        'middlewares' => $middlewares,
                        'schema'      => $schema,
                    ];
                }
            }
        }

        // 对额外的路由器也执行discover
        foreach ($this->additionalRouters as $router) {
            $router->discover();
        }

    }

    /**
     * Register REST routes
     * 
     * 注册 REST 路由
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public function registerRestRoutes(): void
    {
        foreach ($this->restDefs as $def) {
            register_rest_route($def['namespace'], $def['route'], [
                'methods'             => $def['methods'],
                'callback'            => function (WP_REST_Request $req) use ($def) {
                    // execute middlewares
                    foreach ($def['middlewares'] as $middlewareDef) {
                        $middlewareClass = $middlewareDef['class'];
                        $params          = $middlewareDef['params'] ?? [];

                        try {
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
                        catch (\Exception $e) {
                            continue;
                        }
                    }
                    // $obj = new ($def['class'])();
    
                    // —————— 使用容器创建控制器 ——————
                    $controllerClass = $def['class'];

                    if (!$this->container->has($controllerClass)) {
                        $factory = new FactoryDefinition($controllerClass);
                        // 控制器每次请求新建, true
                        $factory->singleton(true);
                        $this->container->setRawDefinition($controllerClass, $factory);
                    }
                    $obj = $this->container->get($controllerClass);

                    return $obj->{$def['method']}($req);
                },
                'permission_callback' => '__return_true'
            ]);
        }

        // register REST routes for additional routers
        foreach ($this->additionalRouters as $router) {
            $router->registerRestRoutes();
        }
    }

    /**
     * Recursively require PHP files in the specified directory
     * 
     * 递归 require 指定目录下的 PHP 文件
     * 
     * @param string $dir Directory path 目录路径
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    private function requirePhpFiles(string $dir): void
    {
        if (!is_dir($dir)) return;

        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

        /**
         * @var \SplFileInfo $f
         */
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                require_once $f->getPathname();
            }
        }
    }

    /**
     * Ensure middleware related classes are loaded
     * 
     * 确保中间件相关类被加载，包括：中间件接口、注解类以及内置中间件类
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    private function requireMiddlewareClasses(): void
    {
        // Load middleware interface
        $middlewareInterface = __DIR__ . '/Middleware/MiddlewareInterface.php';
        if (file_exists($middlewareInterface)) {
            require_once $middlewareInterface;
        }

        // Load middleware attribute
        $middlewareAttribute = __DIR__ . '/Attributes/Middleware.php';
        if (file_exists($middlewareAttribute)) {
            require_once $middlewareAttribute;
        }

        // Load built-in middleware
        $middlewareDir = __DIR__ . '/Middleware';
        if (is_dir($middlewareDir)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($middlewareDir));

            /**
             * @var \SplFileInfo $f
             */
            foreach ($it as $f) {
                if ($f->isFile() && $f->getExtension() === 'php') {
                    require_once $f->getPathname();
                }
            }
        }
    }

    /**
     * Ensure schema related classes are loaded
     * 
     * 确保Schema相关类被加载，包括：Schema注解类以及Schema中间件类
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    private function requireSchemaClasses(): void
    {
        $schemaAttr = __DIR__ . '/Attributes/Schema.php';
        if (file_exists($schemaAttr)) {
            require_once $schemaAttr;
        }

        $schemaMiddleware = __DIR__ . '/Middleware/SchemaMiddleware.php';
        if (file_exists($schemaMiddleware)) {
            require_once $schemaMiddleware;
        }
    }

    /**
     * Get discovered REST route definitions (for debugging)
     * 
     * 获取已发现的 REST 路由定义（用于调试）
     * 
     * @return array
     * @since 1.0.0
     * @author Wang Shai
     */
    public function getRestDefs(): array
    {
        return $this->restDefs;
    }
}