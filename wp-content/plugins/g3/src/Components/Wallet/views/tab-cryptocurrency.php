<?php
use JEALER\G3\Utilities\Container;

echo Container::tip(
    __('Coming soon.', 'G3'),
    'default',
    'mt-4'
);
echo '<form action="" method="post">';
settings_fields('cryptoSettings');
do_settings_sections('wallet-setting&tab=cryptocurrency');
submit_button();
echo '</form>';
