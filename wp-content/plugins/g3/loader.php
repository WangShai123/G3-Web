<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 * 
 * @link              https://www.jealer.com/g3-system/
 * @since             1.0.0
 * @package           G3
 * 
 * @wordpress-plugin
 * 
 * Plugin Name: G3 Web
 * Plugin URI: https://www.jealer.com/g3-system/
 * Description: G3 Web helps you develop stronger wordpress theme. Dependencies: PHP 8.3+, WordPress 6.5+, Redis.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.3
 * Author: JEALER
 * Author URI: https://www.jealer.com/
 * Sponsor: https://www.jealer.com/sponsor/
 * License: GPLv2 or later
 */

/**
 * security check
 */
if (!defined('ABSPATH')) exit;

if (!defined('G3_PLUGIN_FILE')) {
    define('G3_PLUGIN_FILE', __FILE__);
}

/**
 * @description Load Composer autoloader
 */
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * @description Load constants configuration file
 */
require_once __DIR__ . '/config/define.php';
if (file_exists(get_stylesheet_directory() . '/config/define.php')) {
    require_once get_stylesheet_directory() . '/config/define.php';
}
require_once __DIR__ . '/src/Bases/loader.php';


/**
 * @description Register Plugin
 */
register_activation_hook(__FILE__, [JEALER\G3\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [JEALER\G3\Deactivator::class, 'deactivate']);

/**
 * @description Run Plugin
 */
JEALER\G3\Loader::run();
