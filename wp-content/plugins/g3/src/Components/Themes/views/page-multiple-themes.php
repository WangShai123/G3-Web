<?php
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Utilities\Image;
use JEALER\G3\Services\SystemService;
$key = SystemService::THEME_OPTION_KEY;
if (array_key_exists($key, $_POST) && $_POST[$key]) {
    update_option($key, $_POST[$key]);
    add_settings_error('themes', '1', __('Updated!', 'G3'), 'updated');
}
settings_errors('themes');
Frontend::loadStyle('jui'); ?>
<div class="wrap">
    <h1><?php echo __('Multi-theme Mode', 'G3'); ?></h1>
    <div class="j-tip is-default mt-4">
        <div class="tip-icon">
            <?php echo Image::icon('palette'); ?>
        </div>
        <div class="tip-title"><?php echo __('Tip', 'G3'); ?></div>
        <div class="tip-content">
            <div>
                <?php echo __('You can configure different theme templates for different types of devices to improve user experience.', 'G3'); ?>
            </div>
            <div>
                <?php echo __('The theme you configure on the Appearance-Theme page is the main theme of the system. When the option corresponding to the multi-theme mode is empty, the main theme will be used first by default.', 'G3'); ?>
            </div>
        </div>
    </div>
    <form action="" method="post">
        <?php
        settings_fields('section_themes');
        do_settings_sections('multiple-themes');
        submit_button();
        ?>
    </form>
</div>