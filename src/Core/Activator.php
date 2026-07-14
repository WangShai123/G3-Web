<?php
namespace JEALER\G3\Core;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Services\SystemService;
use JEALER\G3\Services\DBService;
use JEALER\G3\Utilities\System;
use JEALER\G3\Core\Rewrite\RewriteRouter;
use Redis;
use Throwable;

class Activator {
    public static Activator $instance;
    private DBService       $DBManager;
    private Container       $container;
    public function __construct()
    {
        $this->container = Container::run();
        $this->DBManager = $this->container->get(DBService::class);
        $this->init();
    }
    public static function activate(): Activator
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    private function init(): void
    {
        self::checkDependencies();
        $this->checkPHP();
        $this->checkRedis();
        $this->checkWordPress();

        $this->initTables();
        $this->registerRewrites();

        $this->param();
    }
    private function registerRewrites(): void
    {
        RewriteRouter::flushRewriteRules();
    }
    /**
     * @deprecated check PHP version by composer instead.
     */
    private function checkPHP(): void
    {
        if (version_compare(phpversion(), '8.3', '<')) {
            deactivate_plugins(plugin_basename(G3_PLUGIN_FILE));
            wp_die(
                __('G3-Web requires PHP 8.3+. Please upgrade your PHP version.', 'G3'),
                __('Failed to active G3-Web plugin!', 'G3'),
                ['back_link' => true]
            );
        }
    }
    private function checkRedis(): void
    {
        try {
            $redis = $this->container->get(Redis::class);
            $redis->connect('127.0.0.1', 6379);
        }
        catch (Throwable $th) {
            deactivate_plugins(plugin_basename(G3_PLUGIN_FILE));
            wp_die(
                __('G3-Web requires Redis server. Please make sure Redis is installed and running.', 'G3'),
                __('Failed to active G3-Web plugin!', 'G3'),
                ['back_link' => true]
            );
        }
    }
    private function checkWordPress(): void
    {
        global $wp_version;
        if (version_compare($wp_version, '6.5', '<')) {
            deactivate_plugins(plugin_basename(G3_PLUGIN_FILE));
            wp_die(
                __('G3-Web requires WordPress 6.5+. Please upgrade your WordPress version.', 'G3'),
                __('Failed to active G3-Web plugin!', 'G3'),
                ['back_link' => true]
            );
        }
    }

    /**
     * dependencies check:
     * - PHP cURL
     * - PHP OpenSSL
     * - PHP SimpleXML
     * - PHP fileinfo
     * - PHP Jealer [暂时移除 jealer 扩展，降低授权限制]
     * 
     * @return void
     */
    public static function checkDependencies(): void
    {
        $dependencies = [
            'curl'      => 'PHP cURL extension',
            'openssl'   => 'PHP OpenSSL extension',
            'simplexml' => 'PHP SimpleXML extension',
            'fileinfo'  => 'PHP fileinfo extension',
            'redis'     => 'PHP Redis extension',
            // 'jealer'    => 'JEALER PHP extension',
        ];

        $phpVersion = phpversion();
        $phpVersion = substr($phpVersion, 0, 3);
        $phpVersion = str_replace('.', '', $phpVersion);

        // $os   = System::osName();
        // $path = rtrim(WP_PLUGIN_DIR, '/') . '/G3-Web/extension/' . $os . '/' . $phpVersion . '/jealer.so';

        $missingDependencies = [];
        foreach ($dependencies as $key => $value) {
            if (!extension_loaded($key)) {
                $missingDependencies[$key] = $value;
            }
        }
        if (!empty($missingDependencies)) {
            deactivate_plugins(plugin_basename(G3_PLUGIN_FILE));
            foreach ($missingDependencies as $key => $value) {
                wp_die(
                    sprintf(
                        __('<h3>Failed to active G3-Web plugin!</h3>G3-Web requires <b>%s</b>.<br>Please install it, then config it in your <strong>php.ini</strong> file and restart PHP server.', 'G3'),
                        $value
                    ),
                    __('Failed to active G3-Web plugin!', 'G3'),
                    ['back_link' => true]
                );
                // if ($key === 'jealer') {
                //     wp_die(
                //         sprintf(
                //             __('<h3>Failed to active G3-Web plugin!</h3>G3-Web requires JEALER PHP extension.<br>Please add the config below in your <b>php.ini</b> file and restart PHP server:<br><b>extension = %s</b>', 'G3'),
                //             $path
                //         ),
                //         __('Failed to active G3-Web plugin!', 'G3'),
                //         ['back_link' => true]
                //     );
                // } else {
                //     wp_die(
                //         sprintf(
                //             __('<h3>Failed to active G3-Web plugin!</h3>G3-Web requires <b>%s</b>.<br>Please install it, then config it in your <strong>php.ini</strong> file and restart PHP server.', 'G3'),
                //             $value
                //         ),
                //         __('Failed to active G3-Web plugin!', 'G3'),
                //         ['back_link' => true]
                //     );
                // }
            }
        }
    }
    // private function initCli(): void
    // {
    //     SystemService::initCli();
    // }
    // private function initObjectCache(): void
    // {
    //     SystemService::initObjectCache();
    // }
    private function initTables(): void
    {
        $this->DBManager->initTables();
    }
    private function param(): void
    {
        add_filter('wp_redirect', function ($location) {
            return add_query_arg('g3-activated', '1', $location);
        });
    }
}
