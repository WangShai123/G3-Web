<?php
namespace JEALER\G3\Core\Helper;
use JEALER\G3\Core\Router\Router;
use JEALER\G3\Core\Router\RouteSource;
use JEALER\G3\Core\Rewrite\RewriteRouter;
use JEALER\G3\Core\ComponentLoader;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Core\Container\ValueDefinition;
use JEALER\G3\Core\Container\FactoryDefinition;
use JEALER\G3\Services\SystemService;
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Utilities\System;
use JEALER\G3\Utilities\Type;
use WP_Error;
use DateTime;
use Exception;

final class Helper {
    public ?Container      $container = null;
    private ?RewriteRouter $rewrite   = null;
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
            $factory = new FactoryDefinition(RewriteRouter::class);
            $factory->singleton();
            $this->container->setRawDefinition('rewrite', $factory);
        }
        if (!$this->container->has('router')) {
            $factory = new FactoryDefinition(Router::class);
            $factory->constructor($this->routeSources())->singleton();
            $this->container->setRawDefinition('router', $factory);
        }
        if (!$this->container->has('componentsLoader')) {
            $factory = new FactoryDefinition(ComponentLoader::class);
            $factory->singleton();
            $this->container->setRawDefinition('componentsLoader', $factory);
        }
    }
    public function loader(): void
    {
        add_action('init', [Frontend::class, 'registerAssets'], 1);
        add_action('wp_enqueue_scripts', [Frontend::class, 'registerAssets'], 1);
        add_action('admin_enqueue_scripts', [Frontend::class, 'registerAssets'], 1);
        add_action('init', [$this, 'initRewriteRouter'], 20);
        add_action('rest_api_init', [$this, 'initRestRouter']);
        $this->container->get('componentsLoader')->load();
    }
    public function initRestRouter(): void
    {
        $router = $this->router();
        $router->registerRestRoutes();
    }
    public function initRewriteRouter(): void
    {
        if (!System::themeModeAvailable()) {
            return;
        }
        if ($this->rewrite === null) {
            $this->rewrite = $this->container->get('rewrite');
        }
        $this->rewrite->registerRewriteRules();
        add_filter('query_vars', [$this->rewrite, 'registerQueryVars'], 10);
        add_filter('template_include', [$this->rewrite, 'bindTemplateDispatch'], 99);
        if (System::debug()) {
            add_action('parse_request', [$this->rewrite, 'checkAndFixRewriteRules'], 1);
        }
    }
    public function router(): Router
    {
        static $router = null;
        if (!$router) {
            if (!$this->container->has(Container::class)) {
                $this->container->setRawDefinition(
                    Container::class,
                    new ValueDefinition($this->container)
                );
            }
            $router = $this->getRouter();
            $router->discover();
        }
        return $router;
    }
    public function gE(): bool|string
    {
        $d = $this->gT();
        if (isset($d['e'])) {
            $e      = is_string($d['e']) ? $d['e'] : '';
            $expire = $this->_d($e, $this->t());
            if (!is_string($expire) || !$this->vt($expire)) return false;
            $expire = wp_date("Y-m-d H:i:s", (int) $expire);
            return $expire;
        }
        return false;
    }
    public function a(): bool|string
    {
        $d = $this->gT();
        if (isset($d['a'])) {
            if (!is_numeric($d['a'])) return false;
            $a = (int) $d['a'];
            if (!$this->vt((string) $a)) return false;
            $at = $a;
            $at = wp_date("Y-m-d H:i:s", $at);
            return $at;
        }
        return false;
    }
    public function vY(string $s, string $e): bool|WP_Error
    {
        $r = $this->send($s, $e);
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
        return System::Z;
    }
    private function gT(): array
    {
        $data = get_transient(SystemService::K);
        return is_string($data) ? Type::jsonToArray($data) : [];
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
        $a = $d['a'] ?? false;
        if (!is_string($t) || !is_string($e) || !is_numeric($a)) return false;
        $u  = get_site_url();
        $_u = $this->_d($t, $this->t());
        if (!is_string($_u) || $this->nD($_u) !== $this->nD($u)) return false;
        $_t = $this->_d($e, $this->t());
        return is_string($_t) && $this->vt($_t) && (int) $_t > time() && $this->cA((int) $a, (int) $_t);
    }
    private function send(string $c, string $e): array|WP_Error
    {
        $p   = [
            "method"      => "POST",
            "headers"     => [
                "Content-Type" => "application/json; charset=utf-8"
            ],
            "body"        => wp_json_encode([
                "target" => SystemService::TARGET,
                'data'   => $this->bd($c, $e)
            ]),
            "data_format" => "body",
            "timeout"     => 30
        ];
        $res = wp_remote_post($this->u(), $p);
        return is_wp_error($res) ? new WP_Error(400, __('HTTPS request failed.')) : $res;
    }
    private function v(array $r): array|WP_Error
    {
        $rC = wp_remote_retrieve_response_code($r);
        $rB = wp_remote_retrieve_body($r);
        $f  = 'Failed';
        if ($rC !== 200 || empty($rB)) {
            $msg = Type::jsonToArray($rB)['message'] ?? $f;
            return new WP_Error(400, $msg);
        }
        $d = Type::jsonToArray($rB);
        return empty($d) ? new WP_Error(400, $f) : $d;
    }
    private function bd(string $c, string $e): array
    {
        return [
            'code'   => $c,
            'email'  => $e,
            "domain" => get_site_url()
        ];
    }
    private function process(array $d): bool|WP_Error
    {
        if (($d["code"] ?? null) !== 200 || ($d["ok"] ?? false) !== true) {
            return new WP_Error(400, $d['message'] ?? 'Invalid License');
        }

        $vD = isset($d["data"]) && is_string($d["data"]) ? $this->dD($d["data"]) : [];
        $e  = $vD["e"] ?? false;
        $t  = $vD["t"] ?? false;
        $a  = $vD["a"] ?? false;
        $c  = $vD["c"] ?? false;
        if (!is_string($e) || !is_string($t) || !is_numeric($a) || !is_array($c) || !$this->vC($c)) {
            return $this->iE();
        }

        $_u = $this->_d($t, $this->t());
        if (!is_string($_u) || $this->nD($_u) !== $this->nD(get_site_url())) {
            return $this->iE();
        }

        $eT = $this->_d($e, $this->t());
        if (!is_string($eT) || !$this->vt($eT) || (int) $eT <= time()) {
            return $this->iE();
        }

        if (!$this->cA((int) $a, (int) $eT)) {
            return $this->iE();
        }

        $rS = $this->gs((int) $eT);
        return $this->sG([
            't' => $t,
            'e' => $e,
            'a' => (int) $a,
        ], $c, $rS);
    }
    private function gs(int $t): int
    {
        $s = $t - time();
        return $s > 0 ? $s : 0;
    }
    private function _d(string $token, string $key): bool|string
    {
        $payload = base64_decode($token, true);
        return is_string($payload) ? openssl_decrypt($payload, "aes-256-cbc", $key, 0, str_pad($key, 16, '\0')) : false;
    }
    private function vt(string $t): bool
    {
        if ($t === '' || !preg_match('/^\d+$/', $t)) return false;

        try {
            $dateTime = new DateTime();
            $dateTime->setTimestamp((int) $t);
            return true;
        }
        catch (Exception $e) {
            return false;
        }
    }
    private function dD(string $data): array
    {
        $json = base64_decode($data, true);
        return is_string($json) ? Type::jsonToArray($json) : [];
    }
    private function vC(array $components): bool
    {
        if ($components === [] || !array_is_list($components)) return false;
        foreach ($components as $component) {
            if (!is_string($component) || trim($component) === '') return false;
        }
        return true;
    }
    private function cA(int $a, int $expire): bool
    {
        return $a > 0 && $this->vt((string) $a) && $a <= $expire;
    }
    private function nD(string $domain): string
    {
        return rtrim(trim($domain), '/');
    }
    private function c(): string
    {
        return SystemService::COMP;
    }
    private function sG(array $grant, array $components, int $ttl): bool
    {
        if ($ttl <= 0) return false;
        $components = array_values(array_unique(array_map(
            static fn(string $component): string => strtolower(trim($component)),
            $components
        )));
        if (!$this->vC($components)) return false;

        $grantSaved      = set_transient(SystemService::K, Type::arrayToJson($grant), $ttl);
        $componentsSaved = set_transient($this->c(), Type::arrayToJson($components), $ttl);
        return $grantSaved && $componentsSaved;
    }
    private function iE(): WP_Error
    {
        return new WP_Error(400, 'Invalid License');
    }
    public function component(string $componentName): bool
    {
        $data       = get_transient($this->c());
        $components = is_string($data) ? Type::jsonToArray($data) : [];
        if (!$this->vC($components)) return false;
        $componentName = strtolower(trim($componentName));
        $components    = array_map(static fn(string $component): string => strtolower(trim($component)), $components);
        return in_array($componentName, $components, true);
    }
    private function u(): string
    {
        // 线上请求地址
        // return $this->container->get(SystemService::class)->endPoint();

        // 本地测试请求地址
        // @todo: 待删除
        return 'http://127.0.0.1:3000/api/v1/requestVerify';
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

    public function getRewrite(): ?RewriteRouter
    {
        if ($this->rewrite === null && System::themeModeAvailable()) {
            if (!$this->container->has('rewrite')) {
                $this->container->setRawDefinition('rewrite', RewriteRouter::class);
            }
            $this->rewrite = $this->container->get('rewrite');
        }
        return $this->rewrite;
    }

    public function getRouter(string $type = 'main'): ?Router
    {
        $routerId = 'router';
        if (!$this->container->has($routerId)) {
            $factory = new FactoryDefinition(Router::class);
            $factory->constructor($this->routeSources())->singleton();
            $this->container->setRawDefinition($routerId, $factory);
        }
        return $this->container->get($routerId);
    }

    /**
     * @return RouteSource[]
     */
    public function routeSources(): array
    {
        return [
            new RouteSource(
                'plugin',
                WP_PLUGIN_DIR . '/G3-Web/src/Controllers',
                'JEALER\\G3\\Controllers'
            ),
            new RouteSource(
                'theme',
                get_stylesheet_directory() . '/src/Controllers',
                'JEALER\\G3\\Controllers'
            ),
        ];
    }
}
