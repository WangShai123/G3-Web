<?php
use JEALER\G3\Utilities\Frontend;

if (array_key_exists('g3_option_dev_form', $_POST) && $_POST['g3_option_dev_form']) {
    update_option('g3_option_dev_form', $_POST['g3_option_dev_form']);
    add_settings_error('formFields', '1', __('Updated!', 'G3'), 'updated');
}
settings_errors('formFields');
Frontend::loadStyle('jui');
?>
<form action="" method="post">
    <div class="j-tip is-default mt-4">
        <div class="tip-title"><?php echo __('Tip', 'G3'); ?></div>
        <div class="tip-content">
            <?php _e('When passing title and description data in the form method, please do not forget to perform internationalization handling.', 'G3'); ?>
        </div>
    </div>
    <?php
    settings_fields('formFields');
    do_settings_sections('developer-mode&tab=form');
    submit_button();
    ?>
</form>