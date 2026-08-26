<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 * 
 * @link              https://www.jealer.com/g3-web
 * @since             1.0.0
 * @package           G3
 * 
 * @wordpress-plugin
 * 
 * Plugin Name:       G3-Web
 * Plugin URI:        https://www.jealer.com/g3-web
 * Description:       G3-Web helps you for rapid application development. Requires PHP 8.3+, WordPress 6.5+, Redis, fileinfo extension, and %postname% permalink structure.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.3
 * Author:            JEALER
 * Author URI:        https://www.jealer.com/
 * Text Domain:       G3
 * Domain Path:       /public/languages
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Sponsor:           https://www.jealer.com/sponsor/
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

if (file_exists(__DIR__ . '/src/helpers.php')) {
    require_once __DIR__ . '/src/helpers.php';
}

/**
 * @description Activate Plugin
 */
register_activation_hook(__FILE__, [JEALER\G3\Core\Activator::class, 'activate']);

/**
 * @description Deactivate Plugin
 */
register_deactivation_hook(__FILE__, [JEALER\G3\Core\Deactivator::class, 'deactivate']);

/**
 * @description Load Plugin
 */
$container = JEALER\G3\Core\Container\Container::run();
if (!$container->has(Psr\Log\LoggerInterface::class)) {
    $logger = new JEALER\G3\Core\Container\FactoryDefinition(JEALER\G3\Services\LogService::class);
    $logger->singleton();
    $container->setRawDefinition(Psr\Log\LoggerInterface::class, $logger);
}
if (!$container->has('app')) {
    $container->setRawDefinition('app', JEALER\G3\Core\Loader::class);
}
$container->get('app');
