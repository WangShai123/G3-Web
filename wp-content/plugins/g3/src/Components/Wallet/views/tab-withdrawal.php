<?php
use JEALER\G3\Utilities\Container;

echo Container::tip(
    __('Coming soon.', 'G3'),
    'default',
    'mt-4'
);
echo '<form action="" method="post">';
settings_fields('withdrawalSettings');
do_settings_sections('wallet-setting&tab=withdrawal');
submit_button();
echo '</form>';
