<?php
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Services\WechatOAService;
$key = WechatOAService::OPTION_KEY;
if (array_key_exists($key, $_POST) && $_POST[$key]) {
    update_option($key, $_POST[$key]);
    add_settings_error('general', '1', __('Updated!', 'G3'), 'updated');
}
settings_errors('general');
Frontend::loadStyle('jui');
?>
<div class="j-tip is-default mt-4">
    <div class="tip-title"><?php _e('Tip', 'G3'); ?></div>
    <div class="tip-content">
        <span>
            <?php
            // 启用服务之前，请确保已经完成开放平台设置。
            echo sprintf(__('Before activating the service, please ensure that you have completed <a href="%s">the open platform setup</a>.', 'G3'), admin_url('options-general.php?page=open-platform'));
            ?>
        </span>
    </div>
</div>
<form action="" method="post">
    <?php
    settings_fields('wechatOA');
    do_settings_sections('wechat-oa');
    submit_button();
    ?>
</form>