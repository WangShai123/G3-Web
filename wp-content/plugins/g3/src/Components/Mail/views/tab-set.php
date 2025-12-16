<?php
use JEALER\G3\Services\MailerService;
$key = MailerService::OPTION_KEY;
if (array_key_exists($key, $_POST) && $_POST[$key]) {
    update_option($key, $_POST[$key]);
    add_settings_error('notice', '1', __('Updated', 'G3'), 'updated');
}
settings_errors('notice');
?>
<form action="" method="post">
    <?php
    settings_fields('set');
    do_settings_sections('mail');
    submit_button();
    ?>
</form>