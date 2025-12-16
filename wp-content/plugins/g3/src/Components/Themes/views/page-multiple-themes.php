<?php
use JEALER\G3\Utilities\Container;
use JEALER\G3\Services\SystemService;

$key = SystemService::THEME_OPTION_KEY;
if (array_key_exists($key, $_POST) && $_POST[$key]) {
    update_option($key, $_POST[$key]);
    add_settings_error('themes', '1', __('Updated', 'G3'), 'updated');
}
settings_errors('themes');
?>
<div class="wrap">
    <h1><?php echo __('Multi-theme Mode', 'G3'); ?></h1>
    <?php
    $p1      = __('You can configure different theme templates for different types of devices to improve user experience.', 'G3');
    $p2      = __('The theme you configure on the Appearance-Theme page is the main theme of the system. When the option corresponding to the multi-theme mode is empty, the main theme will be used first by default.', 'G3');
    $message = '<div>' . $p1 . '</div><div>' . $p2 . '</div>';
    echo Container::tip(
        $message,
        'default',
        'mt-4'
    );
    echo '<form action="" method="post">';
    settings_fields('section_themes');
    do_settings_sections('multiple-themes');
    submit_button();
    ?>
    </form>
</div>