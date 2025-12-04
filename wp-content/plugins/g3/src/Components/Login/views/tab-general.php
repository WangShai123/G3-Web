<?php
use JEALER\G3\Utilities\Frontend;
if (array_key_exists('g3_option_general_login', $_POST) && $_POST['g3_option_general_login']) {
    update_option('g3_option_general_login', $_POST['g3_option_general_login']);
    add_settings_error('general', '1', __('Updated!', 'G3'), 'updated');
}
settings_errors('general');
Frontend::loadStyle('jui');
?>
<div class="j-tip is-default mt-4">
    <div class="tip-title"><?php echo __('Tip', 'G3'); ?></div>
    <div class="tip-content">
        <div>
            <?php
            //启用相关登录功能前，请确保完成相关<a href="/wp-admin/options-general.php?page=open-platform">开放平台</a>设置。
            echo sprintf(__('Before enabling related login functions, please ensure to complete the relevant <a href="%s">Open Platform</a> settings.', 'G3'), admin_url('options-general.php?page=open-platform'));
            ?>
        </div>
    </div>
</div>
<form action="" method="post">
    <?php
    settings_fields('general');
    do_settings_sections('login-setting');
    submit_button();
    ?>
</form>