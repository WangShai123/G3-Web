<?php
namespace JEALER\G3;

use JEALER\G3\Router;
use JEALER\G3\Rewrite;
use JEALER\G3\Components;
use JEALER\G3\ComponentLoader;
use JEALER\G3\Container;
use JEALER\G3\Container\ValueDefinition;
use JEALER\G3\Container\FactoryDefinition;
use JEALER\G3\Services\SystemService;
use JEALER\G3\Utilities\Common;
use JEALER\G3\Utilities\System;
use WP_Error;
use DateTime;
use Exception;

final class Helper {
    public ?Container $container = null;
    private ?Rewrite $rewrite = null;

    public function __construct()
    {
        if ($this->container === null) {
            $this->container = Container::run();
        }
        $this->registerServices();
    }

    private function registerServices()
    {
        if (!$this->container->has('loader')) {
            $this->container->setRawDefinition('loader', self::class);
        }
        if (!$this->container->has('rewrite')) {
            $factory = new FactoryDefinition(Rewrite::class);
            $factory->singleton();
            $this->container->setRawDefinition('rewrite', $factory);
        }
        if (!$this->container->has('router')) {
            $factory = new FactoryDefinition(Router::class);
            $factory->constructor(
                WP_PLUGIN_DIR . '/g3/src/Controllers',
                'JEALER\\G3\\Controllers'
            )->singleton();
            $this->container->setRawDefinition('router', $factory);
        }
        if (!$this->container->has('componentsLoader')) {
            $factory = new FactoryDefinition(ComponentLoader::class);
            $factory->singleton();
            $this->container->setRawDefinition('componentsLoader', $factory);
        }
    }


    /**
     * init Components Loader
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public function loader(): void
    {
        add_action('init', [$this, 'initRewriteRouter']);
        add_action('rest_api_init', [$this, 'initRestRouter']);

        Container::use(ComponentLoader::class)->load();
    }

    /**
     * Register REST API routes
     * 
     * @return void
     * @since 1.0.0
     */
    public function initRestRouter(): void
    {

        $router = $this->router();
        $router->registerRestRoutes();
    }

    /**
     * Initialize rewrite rules
     * @return void
     * @since 1.0.0
     */
    public function initRewriteRouter(): void
    {
        if (!Common::themeModeAvailable()) {
            return;
        }

        if ($this->rewrite === null) {
            $this->rewrite = $this->container->get('rewrite');
        }

        $this->rewrite->registerRewriteRules();

        add_filter('query_vars', [$this->rewrite, 'registerQueryVars'], 10);
        add_filter('template_include', [$this->rewrite, 'bindTemplateDispatch'], 99);

        // Auto check and fix rewrite rules in development environment
        if (System::debug()) {
            add_action('parse_request', [$this->rewrite, 'checkAndFixRewriteRules'], 1);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[G3 Helper] Debug mode: Auto check and fix enabled');
            }
        }
    }

    /**
     * Register REST API Routes
     * 
     * @return Router
     * @since 1.0.0
     * @author Wang Shai
     */
    public function router(): Router
    {
        static $router = null;
        if (!$router) {

            // 绑定容器自身为具体类（供 Inject 使用）
            if (!$this->container->has(Container::class)) {
                $this->container->setRawDefinition(
                    Container::class,
                    new ValueDefinition($this->container)
                );
            }

            // 注册主 Router 到容器（显式传参）
            $router = $this->getRouter('main');
            $router->discover();

            // 主题路由器
            $router = $this->getRouter('user');
            if ($router !== null) {
                $router->discover();
            }
        }
        return $router;
    }

    public function gE(): bool|string
    {
        $d = $this->gT();
        if (isset($d['e'])) {
            $e      = $d['e'];
            $expire = $this->_d($e, $this->t());
            $expire = wp_date("Y-m-d H:i:s", $expire);
            return $expire;
        }
        return false;
    }
    public function a(): bool|string
    {
        $d = $this->gT();
        if (isset($d['a'])) {
            $a  = $d['a'];
            $at = $this->_d($a, $this->t());
            $at = wp_date("Y-m-d H:i:s", $at);
            return $at;
        }
        return false;
    }
    public function vY(string $s): bool|WP_Error
    {
        $r = $this->send($s);
        if (is_wp_error($r)) {
            return $r;
        }
        $vR = $this->v($r);
        if (is_wp_error($vR)) {
            return $vR;
        }
        return $this->process($vR);
    }

    private function t(): string
    {
        return get_transient(SystemService::K) ?: '';
    }
    private function gT(): array
    {
        return get_transient($this->t()) ?: [];
    }
    private function i(): bool
    {
        return $this->cV() ?? false;
    }
    private function cV(): bool
    {
        $d = $this->gT();
        $t = $d['t'] ?? false;
        $e = $d['e'] ?? false;
        $z = $d['z'] ?? false;
        if (!$z || !$e || !$t) return false;

        $u  = get_site_url();
        $_u = $this->_d($t, $z);
        if ($_u != $u) return false;

        $_t = $this->_d($e, $z);
        return $this->vt($_t) && $_t >= time();
    }
    private function send(string $code): array|WP_Error
    {
        $params   = [
            "method"      => "POST",
            "headers"     => [
                "Content-Type" => "application/json; charset=utf-8"
            ],
            "body"        => wp_json_encode([
                "target" => SystemService::TARGET,
                "code"   => $code,
                "domain" => get_site_url()
            ]),
            "data_format" => "body",
            "timeout"     => 30
        ];
        $response = wp_remote_post($this->u(), $params);

        $message = json_decode($response['body'], true);

        return is_wp_error($response) ? new WP_Error(400, $message) : $response;
    }
    private function v(array $r): array|WP_Error
    {
        $rC = wp_remote_retrieve_response_code($r);
        $rB = wp_remote_retrieve_body($r);
        if ($rC !== 200 || empty($rB)) {
            $msg = json_decode($rB, true)['message'] ?? 'Failed';
            return new WP_Error(400, $msg);
        }
        $d = json_decode($rB, true);
        return (json_last_error() !== JSON_ERROR_NONE) ? new WP_Error(400) : $d;
    }
    private function process(array $d): bool|WP_Error
    {
        if (!isset($d["code"]) || $d["code"] !== 200) {
            return new WP_Error(400);
        }

        $vD = $d["data"] ?? [];
        $e  = $vD["e"] ?? false;
        $t  = $vD["t"] ?? false;
        $z  = $vD["z"] ?? false;
        if (!$e || !$t || !$z) return new WP_Error(400);

        set_transient(SystemService::K, $z);

        $eT = $this->_d($e, $z);
        if (!$this->vt($eT) || $eT <= time()) {
            return new WP_Error(400);
        }

        $rS = $this->gs($eT);
        return set_transient($z, $vD, $rS);
    }
    private function gs(int $t): int
    {
        $s = $t - time();
        return $s > 0 ? $s : 0;
    }
    private function _d(string $token, string $key): bool|string
    {
        return openssl_decrypt(
            base64_decode($token),
            "aes-256-cbc",
            $key,
            0,
            str_pad($key, 16, '\0')
        );
    }
    private function vt(string $t): bool
    {
        try {
            $dateTime = new DateTime();
            $dateTime->setTimestamp((int) $t);
            return true;
        }
        catch (Exception $e) {
            return false;
        }
    }
    private function u(): string
    {
        return Container::use(SystemService::class)->endPoint();
    }
    public function admin(): bool
    {
        return $this->i();
    }
    public function x(): bool
    {
        return !$this->i();
    }
    public function y(): bool
    {
        return $this->i();
    }

    /**
     * 加载原有组件系统
     * 
     * @return void
     */
    private static function loadLegacyComponentSystem(): void
    {
        // initialize Components system (原有逻辑)
        // Container::use(Components::class);

        // load all components (原有逻辑)
        self::loadComponents();

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[G3 Helper] Legacy component system loaded');
        }
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
        $mainConfig = G3_PlUGIN_DIR . "/config/components.php";
        $mainMap    = require_once $mainConfig;
        if (!is_array($mainMap)) {
            new WP_Error(
                "Invalid G3",
                "Invalid component mapping configuration."
            );
        }

        /**
         * @var array User component mapping configuration
         */
        $userMap    = [];
        $userConfig = get_stylesheet_directory() . "/config/components.php";
        if (file_exists($userConfig)) {
            $tempMap = require_once $userConfig;
            $userMap = is_array($tempMap) ? $tempMap : [];
        }

        $componentsMap = array_merge($mainMap, $userMap);

        self::components($componentsMap);
    }

    /**
     * Load Component files and create instances
     *
     * @param array $map format as [
     *     'component_name' => true,
     * ]
     *
     * @return array Loaded component instances
     * @since 1.0.0
     * @author Wang Shai
     */
    private static function components(array $map): array
    {
        $loaded = [];

        foreach ($map as $name => $shouldLoad) {
            // only load component when value is true
            if ($shouldLoad !== true) {
                continue;
            }
            // check component className
            $className           = ucfirst($name);
            $pluginComponentFile = G3_PlUGIN_DIR . "/src/Components/{$className}/{$className}.php";
            $userComponentFile   = get_stylesheet_directory() . "/src/components/{$className}/{$className}.php";
            $fullClassName       = "JEALER\G3\Components\\{$className}";

            $componentFile = file_exists($userComponentFile) ? $userComponentFile : $pluginComponentFile;
            require_once $componentFile;

            if (!class_exists($fullClassName)) {
                wp_die(
                    "[G3 Error] Class of '{$fullClassName}' does not exist.",
                    "G3 Error",
                    [
                        'back_link' => true,
                    ]
                );
            }

            $loaded[$name] = Components::make($className);
        }

        return $loaded;
    }

    public function getRewrite(): ?Rewrite
    {
        if ($this->rewrite === null && Common::themeModeAvailable()) {
            if (!$this->container->has('rewrite')) {
                $this->container->setRawDefinition('rewrite', Rewrite::class);
            }
            $this->rewrite = $this->container->get('rewrite');
        }
        return $this->rewrite;
    }

    public function getRouter(string $type = 'main'): ?Router
    {
        $userPath = get_stylesheet_directory() . '/src/Controllers';
        if (!file_exists($userPath) && $type === 'user') {
            return null;
        }

        $routerId = match ($type) {
            'main' => 'router.main',
            'user' => 'router.user',
        };

        $path = match ($type) {
            'main' => WP_PLUGIN_DIR . '/g3/src/Controllers',
            'user' => $userPath
        };

        if (!$this->container->has($routerId)) {
            $factory = new FactoryDefinition(Router::class);
            $factory->constructor(
                $path,
                'JEALER\\G3\\Controllers'
            )->singleton();
            $this->container->setRawDefinition($routerId, $factory);
        }
        return $this->container->get($routerId);
    }

    public function getComponentsLoader(): ?ComponentLoader
    {
        if (!$this->container->has('componentsLoader')) {
            $this->container->setRawDefinition('componentsLoader', ComponentLoader::class);
        }
        return $this->container->get('componentsLoader');
    }
}
