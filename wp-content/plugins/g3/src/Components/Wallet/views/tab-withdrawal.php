<?php
use JEALER\G3\Utilities\Element;

echo Element::tip(
    __('Coming soon.', 'G3'),
    'default',
    'mt-4'
);
echo '<form action="" method="post">';
settings_fields('withdrawalSettings');
do_settings_sections('wallet-settings&tab=withdrawal');
submit_button();
echo '</form>';
