<?php
use JEALER\G3\Utilities\Container;

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