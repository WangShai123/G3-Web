<?php
use JEALER\G3\Utilities\Container;
use JEALER\G3\Services\PaymentService;

$key = PaymentService::WALLET_OPTION_KEY;
if (array_key_exists($key, $_POST) && $_POST[$key]) {
    update_option($key, $_POST[$key]);
    add_settings_error('cryptocurrency', '1', __('Updated', 'G3'), 'updated');
}
settings_errors('cryptocurrency');
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
