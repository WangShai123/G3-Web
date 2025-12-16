<?php
use JEALER\G3\Services\SystemService;
$key = SystemService::SECURITY_OPTION_KEY;
if (array_key_exists($key, $_POST) && $_POST[$key]) {
    update_option($key, $_POST[$key]);
    add_settings_error('notice', '1', __('Updated', 'G3'), 'updated');
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