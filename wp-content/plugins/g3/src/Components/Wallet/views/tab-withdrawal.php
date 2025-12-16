<?php
use JEALER\G3\Utilities\Container;
use JEALER\G3\Services\PaymentService;

$key = PaymentService::WALLET_OPTION_KEY;
if (array_key_exists($key, $_POST) && $_POST[$key]) {
    update_option($key, $_POST[$key]);
    add_settings_error('withdrawal', '1', __('Updated', 'G3'), 'updated');
}
settings_errors('withdrawal');
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
