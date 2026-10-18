<?php
use JEALER\G3\Utilities\Element;

echo Element::tip(
    sprintf(__('This tab is for %s advice.', 'G3'), 'wp-config'),
    '',
    'default',
    'mt-4'
);

$currentTTL = defined('G3_WP_BUILTIN_CACHE_TTL') ? sprintf(__('%d hours'), (int) G3_WP_BUILTIN_CACHE_TTL) : __('None');
$msg        = sprintf(__('Set cache expiration for some built-in functions in WordPress to avoid redundant junk data. Default: 24 hours. Current value: %s.', 'G3'), $currentTTL);

?>

<div class="g3-content">
    <pre class="code-block">
# <?php echo sprintf(__('%s %s', 'G3'), __('Enable'), __('Cache', 'G3')); ?>

define('WP_CACHE', true);

# <?php echo $msg; ?>

define("G3_WP_BUILTIN_CACHE_TTL", 24);

# <?php echo __('Hide Developer Mode Menu', 'G3'); ?>

define("G3_HIDE_DEVELOPER_MODE", true);

# <?php echo __('Disable') . ' ' . __('WordPress Auto Update', 'G3'); ?>

define('AUTOMATIC_UPDATER_DISABLED', true);
define('WP_AUTO_UPDATE_CORE', false);

# <?php echo __('Disable'); ?> WordPress Cron
define('DISABLE_WP_CRON', true);

# <?php echo __('Test', 'G3') . ': ' . __('Automatically adapt to access Host address.', 'G3'); ?>

if (isset($_SERVER['HTTP_HOST'])) {
  define('WP_HOME', 'http://' . $_SERVER['HTTP_HOST']);
  define('WP_SITEURL', 'http://' . $_SERVER['HTTP_HOST']);
}

# <?php echo __('Test', 'G3'); ?>

define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('SAVEQUERIES', true);
</pre>
</div>