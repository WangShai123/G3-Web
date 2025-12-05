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
    private string $t;
    public function __construct()
    {
        $this->t = SystemService::KEY;
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
        return self::run()->isLicensed();
    }
    public static function x(): bool
    {
        return !self::run()->isLicensed();
    }
    public static function y(): bool
    {
        return self::run()->isLicensed();
    }

    public function getExpire(): bool|string
    {
        $data = get_transient($this->t);
        if (isset($data['e'])) {
            $e      = $data['e'];
            $expire = $this->_decrypt($e, $this->t);
            $expire = wp_date("Y-m-d H:i:s", $expire);
            return $expire;
        }
        return false;
    }

    public function getActivatedAt(): bool|string
    {
        $data = get_transient($this->t);
        if (isset($data['a'])) {
            $a            = $data['a'];
            $activated_at = $this->_decrypt($a, $this->t);
            $activated_at = wp_date("Y-m-d H:i:s", $activated_at);
            return $activated_at;
        }
        return false;
    }

    /**
     * Verify License
     * @param string $code License Code
     * @return bool|WP_Error Returns true on success, or WP_Error object on failure
     * @since 1.0.0
     * @author Wang Shai
     */
    public function verify(string $code): bool|WP_Error
    {
        $response = $this->send($code);

        if (is_wp_error($response)) {
            return $response;
        }

        $validationResult = $this->validate($response);

        if (is_wp_error($validationResult)) {
            return $validationResult;
        }

        return $this->process($validationResult);
    }

    private function isLicensed(): bool
    {
        return $this->checkVerification() ?? false;
    }

    /**
     * Check verification
     * @return bool
     * @since 1.0.0
     * @author Wang Shai
     */
    private function checkVerification(): bool
    {
        $data = get_transient($this->t);
        if (!is_array($data) || !isset($data["t"]) || !isset($data["e"])) {
            return false;
        }
        $site_url = get_site_url();
        $token    = $this->_decrypt($data["t"], $this->t);
        if ($token != $site_url) {
            return false;
        }
        $time = $this->_decrypt($data["e"], $this->t);
        if (!$this->validateTimestamp($time) || $time < time()) {
            return false;
        }
        return true;
    }

    /**
     * Send Verification Request
     * @param string $code License Code
     * @return array|WP_Error HTTP Response Array or WP_Error Object
     */
    private function send(string $code): array|WP_Error
    {
        $params = [
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
        $uri    = "https://api.jealer.com/api/v1/requestVerify";

        $response = wp_remote_post($uri, $params);

        return is_wp_error($response) ? new WP_Error(400) : $response;
    }

    /**
     * Validate HTTP Response
     * @param array $response HTTP Response Array
     * @return array|WP_Error Validated Data Array or WP_Error Object
     */
    private function validate(array $response): array|WP_Error
    {
        $responseCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);

        // Check HTTP Status Code and Response Body
        if ($responseCode !== 200 || empty($responseBody)) {
            return new WP_Error(400);
        }

        // Parse JSON Response Body
        $data = json_decode($responseBody, true);

        return (json_last_error() !== JSON_ERROR_NONE) ? new WP_Error(400) : $data;
    }

    /**
     * Process Verification Data
     * @param array $data Validated Verification Data Array
     * @return bool|WP_Error Processing Result Boolean or WP_Error Object
     */
    private function process(array $data): bool|WP_Error
    {
        if (!isset($data["code"]) || $data["code"] !== 200) {
            return new WP_Error(400);
        }

        // Extract Verification Data
        $verificationData = $data["data"] ?? [];

        if (!isset($verificationData["t"], $verificationData["e"])) {
            return new WP_Error(400);
        }

        // Validate Expiration Time
        $expirationTime = $this->_decrypt($verificationData["e"], $this->t);

        if (
            !$this->validateTimestamp($expirationTime) ||
            $expirationTime <= time()
        ) {
            return new WP_Error(400);
        }

        $remainingSeconds = $this->gs($expirationTime);

        return set_transient($this->t, $verificationData, $remainingSeconds);
    }

    /**
     * Calculate Remaining Seconds
     * @param int $timestamp Expiration Timestamp
     * @return int Remaining Seconds
     * @since 1.0.0
     * @author Wang Shai
     */
    private function gs(int $timestamp): int
    {
        $remaining_seconds = $timestamp - time();
        return $remaining_seconds > 0 ? $remaining_seconds : 0;
    }

    /**
     * Decrypt Token
     * @param string $token Encrypted Token
     * @param string $key Decryption Key
     * @return string|bool Decrypted Token or False on Failure
     * @since 1.0.0
     * @author Wang Shai
     */
    private function _decrypt(string $token, string $key): bool|string
    {
        return openssl_decrypt(
            base64_decode($token),
            "aes-256-cbc",
            $key,
            0,
            str_pad($key, 16, '\0')
        );
    }

    /**
     * Validate Timestamp
     * @param string $timestamp Timestamp String
     * @return bool True if Valid, False otherwise
     * @since 1.0.0
     * @author Wang Shai
     */
    private function validateTimestamp(string $timestamp): bool
    {
        try {
            $dateTime = new DateTime();
            $dateTime->setTimestamp((int) $timestamp);
            return true;
        }
        catch (Exception $e) {
            return false;
        }
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
