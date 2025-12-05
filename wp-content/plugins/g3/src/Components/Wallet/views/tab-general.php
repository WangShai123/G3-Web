<?php
use JEALER\G3\Services\PaymentService;
$key = PaymentService::WALLET_OPTION_KEY;
if (array_key_exists($key, $_POST) && $_POST[$key]) {
    update_option($key, $_POST[$key]);
    add_settings_error('general', '1', __('Updated!', 'G3'), 'updated');
}
settings_errors('general');
?>
<form action="" method="post">
    <?php
    settings_fields('generalSetting');
    do_settings_sections('wallet-setting&tab=general');
    submit_button();
    ?>
</form>