<?php
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Services\AuthService;
$key = AuthService::FOLLOW_OPTION_KEY;
if (array_key_exists($key, $_POST) && $_POST[$key]) {
    update_option($key, $_POST[$key]);
    add_settings_error('follow', '1', __('Updated!', 'G3'), 'updated');
}
settings_errors('follow');
Frontend::loadStyle('jui');
?>
<div class="j-tip is-default mt-4">
    <div class="tip-title"><?php echo __('Tip', 'G3'); ?></div>
    <div class="tip-content">
        <div>
            <?php
            //通过让用户关注微信公众号来自动登录，是对微信公众号引流和用户关系绑定非常有效的运营工具。
            echo __('By letting users follow the WeChat official account to automatically log in, it is an extremely effective operation tool for driving WeChat users and binding user relationships.', 'G3');
            ?>
        </div>
        <div>
            <?php
            //启用关注登录功能后，系统将自动生成公众号二维码，用户关注公众号即可完成登录。
            echo __('After enabling the follow login function, the system will automatically generate a WeChat official account QR code. Users can follow the WeChat official account to complete the login.', 'G3');
            ?>
        </div>
        <div>
            <?php
            //启用关注登录功能后，系统将自动禁用其他登录方式。
            echo __('After enabling the follow login function, the system will automatically disable other login methods.', 'G3');
            ?>
        </div>
    </div>
</div>
<form action="" method="post">
    <?php
    settings_fields('follow');
    do_settings_sections('login-setting&tab=follow');
    submit_button();
    ?>
</form>