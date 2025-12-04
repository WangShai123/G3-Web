<?php
use JEALER\G3\Utilities\Frontend;
if (array_key_exists('g3_option_wechatMP', $_POST) && $_POST['g3_option_wechatMP']) {
    update_option('g3_option_wechatMP', $_POST['g3_option_wechatMP']);
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
    settings_fields('wechatMP');
    do_settings_sections('wechat-mp');
    submit_button();
    ?>
</form>