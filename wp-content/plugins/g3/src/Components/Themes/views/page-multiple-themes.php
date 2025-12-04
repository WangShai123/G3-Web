<?php
use JEALER\G3\Utilities\Frontend;
if (array_key_exists('g3_option_themes', $_POST) && $_POST['g3_option_themes']) {
    update_option('g3_option_themes', $_POST['g3_option_themes']);
    add_settings_error('themes', '1', __('Updated!', 'G3'), 'updated');
}
settings_errors('themes');
Frontend::loadStyle('jui');
?>
<div class="wrap">
    <h1><?php echo __('Multi-theme Mode', 'G3'); ?></h1>
    <div class="j-tip is-default mt-4">
        <div class="tip-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path fill="none" d="M0 0h24v24H0z"></path>
                <path
                    d="M12 2C17.5222 2 22 5.97778 22 10.8889C22 13.9556 19.5111 16.4444 16.4444 16.4444H14.4778C13.5556 16.4444 12.8111 17.1889 12.8111 18.1111C12.8111 18.5333 12.9778 18.9222 13.2333 19.2111C13.5 19.5111 13.6667 19.9 13.6667 20.3333C13.6667 21.2556 12.9 22 12 22C6.47778 22 2 17.5222 2 12C2 6.47778 6.47778 2 12 2ZM10.8111 18.1111C10.8111 16.0843 12.451 14.4444 14.4778 14.4444H16.4444C18.4065 14.4444 20 12.851 20 10.8889C20 7.1392 16.4677 4 12 4C7.58235 4 4 7.58235 4 12C4 16.19 7.2226 19.6285 11.324 19.9718C10.9948 19.4168 10.8111 18.7761 10.8111 18.1111ZM7.5 12C6.67157 12 6 11.3284 6 10.5C6 9.67157 6.67157 9 7.5 9C8.32843 9 9 9.67157 9 10.5C9 11.3284 8.32843 12 7.5 12ZM16.5 12C15.6716 12 15 11.3284 15 10.5C15 9.67157 15.6716 9 16.5 9C17.3284 9 18 9.67157 18 10.5C18 11.3284 17.3284 12 16.5 12ZM12 9C11.1716 9 10.5 8.32843 10.5 7.5C10.5 6.67157 11.1716 6 12 6C12.8284 6 13.5 6.67157 13.5 7.5C13.5 8.32843 12.8284 9 12 9Z">
                </path>
            </svg>
        </div>
        <div class="tip-title"><?php echo __('Tip', 'G3'); ?></div>
        <div class="tip-content">
            <?php echo __('You can configure different theme templates for different types of devices to improve user experience.', 'G3'); ?></br>
            <?php echo __('The theme you configure on the Appearance-Theme page is the main theme of the system. When the option corresponding to the multi-theme mode is empty, the main theme will be used first by default.', 'G3'); ?>
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