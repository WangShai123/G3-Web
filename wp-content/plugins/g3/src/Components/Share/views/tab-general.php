<?php
use JEALER\G3\Services\ShareService;
$key = ShareService::OPTION_KEY;
if (array_key_exists($key, $_POST) && $_POST[$key]) {
    update_option($key, $_POST[$key]);
    add_settings_error('general', '1', __('Updated!', 'G3'), 'updated');
}
settings_errors('general');
?>
<form action="" method="post">
    <?php
    settings_fields('general');
    do_settings_sections('share-setting&tab=general');
    submit_button();
    ?>
</form>