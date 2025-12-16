<?php
use JEALER\G3\Services\SystemService;
$key = SystemService::OPTION_KEY;
if (array_key_exists($key, $_POST) && $_POST[$key]) {
    update_option($key, $_POST[$key]);
    add_settings_error('general', 'setting_message', __('Updated', 'G3'), 'updated');
}
settings_errors('general');
?>
<form action="" method="POST">
    <?php
    settings_fields('general');
    do_settings_sections('g3-settings');
    submit_button();
    ?>
</form>