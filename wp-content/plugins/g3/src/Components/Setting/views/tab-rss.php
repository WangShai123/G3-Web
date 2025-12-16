<?php
use JEALER\G3\Utilities\Container;
use JEALER\G3\Services\SystemService;

$key = SystemService::RSS_OPTION_KEY;
if (array_key_exists($key, $_POST) && $_POST[$key]) {
    update_option($key, $_POST[$key]);
    add_settings_error('rss', '1', __('Updated', 'G3'), 'updated');
}
settings_errors('rss');
echo Container::tip(
    __('You can provide the RSS feed address to partners or users for subscribing to receive content from your platform.', 'G3'),
    'default',
    'mt-4'
);
echo '<form action="" method="POST">';
settings_fields('rss');
do_settings_sections('g3-settings&tab=rss');
submit_button();
echo '</form>';