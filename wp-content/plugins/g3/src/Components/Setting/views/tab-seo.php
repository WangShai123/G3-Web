<?php
use JEALER\G3\Services\SystemService;
$key = SystemService::SEO_OPTION_KEY;
if (array_key_exists($key, $_POST) && $_POST[$key]) {
    update_option($key, $_POST[$key]);
    add_settings_error('seo', 'setting_message', __('Updated!', 'G3'), 'updated');
}
settings_errors('seo');
?>
<form action="" method="POST">
    <?php
    settings_fields('seo');
    do_settings_sections('g3-settings&tab=seo');
    submit_button();
    ?>
</form>