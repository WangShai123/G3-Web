<?php
namespace JEALER\G3;

use JEALER\G3\Services\SystemService;
use JEALER\G3\Utilities\System;

class Activator {
    public static $instance = null;
    public function __construct()
    {
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
        $this->checkWordPress();

        $this->initTables();
        $this->registerRewrites();

        $this->initCli();
        $this->initObjectCache();

        $this->param();
    }

    private function registerRewrites(): void
    {
        Rewrite::flushRewriteRules();
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
     * - PHP Jealer
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function checkDependencies(): void
    {
        $dependencies = [
            'curl'      => 'PHP cURL extension',
            'openssl'   => 'PHP OpenSSL extension',
            'simplexml' => 'PHP SimpleXML extension',
            'fileinfo'  => 'PHP fileinfo extension',
            // 'jealer'    => 'JEALER PHP extension',
            'redis'     => 'PHP Redis extension'
        ];

        $phpVersion = phpversion();
        $phpVersion = substr($phpVersion, 0, 3);
        $phpVersion = str_replace('.', '', $phpVersion);

        $os   = System::osName();
        $path = rtrim(WP_PLUGIN_DIR, '/') . '/g3/extension/' . $os . '/' . $phpVersion . '/jealer.so';

        $missingDependencies = [];
        foreach ($dependencies as $key => $value) {
            if (!extension_loaded($key)) {
                $missingDependencies[$key] = $value;
            }
        }
        if (!empty($missingDependencies)) {
            deactivate_plugins(plugin_basename(G3_PLUGIN_FILE));
            foreach ($missingDependencies as $key => $value) {
                if ($key === 'jealer') {
                    wp_die(
                        sprintf(
                            __('<h3>Failed to active G3-Web plugin!</h3>G3-Web requires JEALER PHP extension.<br>Please add the config below in your <b>php.ini</b> file and restart PHP server:<br><b>extension = %s</b>', 'G3'),
                            $path
                        ),
                        __('Failed to active G3-Web plugin!', 'G3'),
                        ['back_link' => true]
                    );
                } else {
                    wp_die(
                        sprintf(
                            __('<h3>Failed to active G3-Web plugin!</h3>G3-Web requires <b>%s</b>.<br>Please install it, then config it in your <strong>php.ini</strong> file and restart PHP server.', 'G3'),
                            $value
                        ),
                        __('Failed to active G3-Web plugin!', 'G3'),
                        ['back_link' => true]
                    );
                }
            }
        }
    }

    private function initCli(): void
    {
        SystemService::initCli();
    }

    private function initObjectCache(): void
    {
        SystemService::initObjectCache();
    }

    private function initTables(): void
    {
        SystemService::initTables();
    }

    private function param(): void
    {
        add_filter('wp_redirect', function ($location) {
            return add_query_arg('g3-activated', '1', $location);
        });
    }
}