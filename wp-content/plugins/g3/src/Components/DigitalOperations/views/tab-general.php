<?php
if (array_key_exists('g3_option_general', $_POST) && $_POST['g3_option_general']) {
    update_option('g3_option_general', $_POST['g3_option_general']);
    add_settings_error('general', 'setting_message', __('Updated!', 'G3'), 'updated');
}
settings_errors('general');
?>
<form action="" method="POST">
    <?php
    settings_fields('general');
    do_settings_sections('digital-operations');
    submit_button();
    ?>
</form>