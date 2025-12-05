<?php
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Services\PaymentService;
$key = PaymentService::WALLET_OPTION_KEY;
if (array_key_exists($key, $_POST) && $_POST[$key]) {
    update_option($key, $_POST[$key]);
    add_settings_error('cryptocurrency', '1', __('Updated!', 'G3'), 'updated');
}
settings_errors('cryptocurrency');
Frontend::loadStyle('jui');
?>
<div class="j-tip mt-4 is-default">
    <div class="tip-title"><?php _e('Tip', 'G3'); ?></div>
    <div class="tip-content"><?php _e('Coming soon.', 'G3'); ?></div>
</div>
<form action="" method="post">
    <?php
    settings_fields('cryptoSettings');
    do_settings_sections('wallet-setting&tab=cryptocurrency');
    submit_button();
    ?>
</form>