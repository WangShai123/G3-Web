<?php
namespace JEALER\G3;

use WP_Error;
use JEALER\G3\Router;
use JEALER\G3\Rewrite;
use JEALER\G3\Utilities\Common;
use JEALER\G3\Services\SystemService;
use DateTime;
use Exception;

class JL {
    public static $i = null;
    public function __construct()
    {
    }
    public static function run(): JL
    {
        if (!isset(self::$i)) {
            self::$i = new self();
        }
        return self::$i;
    }
    public static function admin(): bool
    {
        return self::run()->i();
    }
    public static function x(): bool
    {
        return !self::run()->i();
    }
    public static function y(): bool
    {
        return self::run()->i();
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
        return get_transient('wPxK91qZ') ?: '';
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
                "target" => "g3Verify",
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

        set_transient('wPxK91qZ', $z);

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
        return Common::singleton(SystemService::class)->endPoint();
    }

    /**
     * Register REST API Routes
     * @return Router
     * @since 1.0.0
     * @author Wang Shai
     */
    // public static function router(): Router
    // {
    //     static $router = null;
    //     if (!$router) {
    //         // Create Main Router (Plugin Controller)
    //         $router = new Router(
    //             baseDir: WP_PLUGIN_DIR . "/g3/src/Controllers",
    //             baseNamespace: "JEALER\\G3\\Controllers"
    //         );

    //         // Reflectively scan plugin controllers
    //         $router->discover();

    //         // Check and scan theme controllers directory
    //         $themeControllersDir =
    //             get_stylesheet_directory() . "/src/Controllers";
    //         if (file_exists($themeControllersDir)) {
    //             // Create additional router for theme controllers
    //             $themeRouter = new Router(
    //                 baseDir: $themeControllersDir,
    //                 baseNamespace: "G3\\Controllers"
    //             );
    //             // Reflectively scan theme controllers
    //             $themeRouter->discover();

    //             // Add theme router to main router
    //             $router->addRouter($themeRouter);
    //         }
    //     }
    //     return $router;
    // }

    /**
     * Register Rewrite Rules
     * @return Rewrite
     * @since 1.0.0
     * @author Wang Shai
     */
    // public static function rewrite(): Rewrite
    // {
    //     return Rewrite::getInstance();
    // }

    /**
     * Load Plugin Core Files
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    // public static function loader(): void
    // {
    //     // load base functions
    //     require_once WP_PLUGIN_DIR . "/g3/src/Bases/loader.php";

    //     // initialize Components system
    //     Common::singleton("JEALER\G3\Components");

    //     // load all components
    //     self::loadComponents();
    // }


    /**
     * Load All Components
     * @return 
     * @since 1.0.0
     * @author Wang Shai
     */
    // private static function loadComponents()
    // {
    //     /**
    //      * @var array Default component mapping configuration
    //      */
    //     $defaultMap = require_once WP_PLUGIN_DIR . "/g3/config/components.php";

    //     /**
    //      * @var array User component mapping configuration
    //      */
    //     $userMap = file_exists(G3_THEME_CONFIG_DIR . "/components.php")
    //         ? require_once G3_THEME_CONFIG_DIR . "/components.php"
    //         : [];

    //     $componentsMap = array_merge($defaultMap, $userMap);

    //     self::components($componentsMap);
    // }

    /**
     * Load Component files and create instances
     *
     * @param array $componentsMap format as [
     *     'component_name' => true,
     * ]
     *
     * @return array Loaded component instances
     * @since 1.0.0
     * @author Wang Shai
     */
    // public static function components(array $componentsMap): array
    // {
    //     $loadedComponents = [];

    //     foreach ($componentsMap as $componentName => $shouldLoad) {
    //         // only load component when value is true
    //         if ($shouldLoad !== true) {
    //             continue;
    //         }
    //         // check component className
    //         $className     = ucfirst($componentName);
    //         $componentFile = WP_PLUGIN_DIR . "/g3/src/Components/{$componentName}/{$className}.php";

    //         if (file_exists($componentFile)) {
    //             require_once $componentFile;

    //             $fullClassName = "JEALER\G3\Components\\{$className}";

    //             if (class_exists($fullClassName)) {
    //                 /**
    //                  * modify: Components::create only accept one parameter
    //                  * Component configuration no longer pass parameters in configuration
    //                  */
    //                 $loadedComponents[$componentName] = Components::create($className);
    //             } else {
    //                 wp_die("G3 Error: Something Wrong with Components Configuration: {$componentName}");
    //             }
    //         } else {
    //             wp_die("G3 Error: Something Wrong with Components Configuration: {$componentName}");
    //         }
    //     }

    //     return $loadedComponents;
    // }
}
