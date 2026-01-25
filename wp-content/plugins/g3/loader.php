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
 * Description: G3-Web helps you develop stronger wordpress theme. Dependencies: PHP 8.3+, WordPress 6.5+, Redis, fileinfo, %postname% permalink structure.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.3
 * Author: JEALER
 * Author URI: https://www.jealer.com/
 * Sponsor: https://www.jealer.com/sponsor/
 * Text Domain: G3
 * Domain Path: /public/languages
 * License: GPLv2 or later
 */

/**
 * @description Security Check
 */
if (!defined('ABSPATH')) exit;

/**
 * @description Plugin File
 */
if (!defined('G3_PLUGIN_FILE')) {
    define('G3_PLUGIN_FILE', __FILE__);
}

/**
 * @description Load Composer Autoloader
 */
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * @description Load Constants Configuration Files
 */
require_once __DIR__ . '/config/define.php';
if (file_exists(get_stylesheet_directory() . '/config/define.php')) {
    require_once get_stylesheet_directory() . '/config/define.php';
}

/**
 * @description Activate Plugin
 */
register_activation_hook(__FILE__, [JEALER\G3\Activator::class, 'activate']);

/**
 * @description Deactivate Plugin
 */
register_deactivation_hook(__FILE__, [JEALER\G3\Deactivator::class, 'deactivate']);

/**
 * @description Load Plugin
 */
$container = JEALER\G3\Container::run();
if (!$container->has('app')) {
    $container->setRawDefinition('app', JEALER\G3\Loader::class);
    $container->get('app');
}
