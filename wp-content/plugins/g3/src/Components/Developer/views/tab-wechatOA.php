<?php
use JEALER\G3\Services\SystemService;
$key = SystemService::OPEN_WECHAT_OA_KEY;
if (array_key_exists($key, $_POST) && $_POST[$key]) {
    update_option($key, $_POST[$key]);
    add_settings_error('opWechatOA', '1', __('Updated!', 'G3'), 'updated');
}
?>
<?php settings_errors('opWechatOA'); ?>
<form action="" method="post">
    <?php
    settings_fields('opWechatOA');
    do_settings_sections('open-platform&tab=mp');
    submit_button();
    ?>
</form>