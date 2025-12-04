<?php
if (array_key_exists('g3_option_reading', $_POST) && $_POST['g3_option_reading']) {
    update_option('g3_option_reading', $_POST['g3_option_reading']);
    add_settings_error('general', '1', __('Updated!', 'G3'), 'updated');
}
settings_errors('general');
?>
<form action="" method="post">
    <?php
    settings_fields('reading');
    do_settings_sections('post-reading');
    submit_button();
    ?>
</form>