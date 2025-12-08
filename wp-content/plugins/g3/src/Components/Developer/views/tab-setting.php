<?php
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Services\SystemService;
$key = SystemService::SETTING_OPTION_KEY;
if (array_key_exists($key, $_POST) && $_POST[$key]) {
    update_option($key, $_POST[$key]);
    add_settings_error('setting', '1', __('Updated!', 'G3'), 'updated');
}
settings_errors('setting');
Frontend::loadStyle('jui');
?>
<form action="" method="post">
    <div class="j-tip is-default mt-4">
        <div class="tip-title"><?php echo __('Tip', 'G3'); ?></div>
        <div class="tip-content">
            <?php
            echo '<div>1. ' . __('If you want to hide the developer mode menu, define the constant in your theme <code>define("G3_HIDE_DEVELOPER_MODE", true);</code>', 'G3') . '</div>';
            echo '<div>2. ' . __('Disable some unused WordPress functions, make the admin panel clean, and avoid some mistakes.', 'G3') . '</div>';
            echo '<div>3. ' . __('Disable WordPress auto update related functions, can significantly improve the speed of the backend access.', 'G3') . '</div>';
            ?>
        </div>
    </div>
    <?php
    settings_fields('devSetting');
    do_settings_sections('developer-mode&tab=setting');
    submit_button();
    ?>
</form>