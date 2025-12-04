<?php
if (array_key_exists('g3_option_securities', $_POST) && $_POST['g3_option_securities']) {
    update_option('g3_option_securities', $_POST['g3_option_securities']);
    add_settings_error('notice', '1', __('Updated!', 'G3'), 'updated');
}
settings_errors('notice');
?>
<form action="" method="POST">
    <?php
    settings_fields('securitySection');
    do_settings_sections('security');
    submit_button();
    ?>
</form>