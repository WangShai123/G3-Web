<?php
if (array_key_exists('g3_option_wallet', $_POST) && $_POST['g3_option_wallet']) {
    update_option('g3_option_wallet', $_POST['g3_option_wallet']);
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