<?php
if (array_key_exists('g3_option_mail', $_POST) && $_POST['g3_option_mail']) {
    update_option('g3_option_mail', $_POST['g3_option_mail']);
    add_settings_error('notice', '1', __('Updated!', 'G3'), 'updated');
}
settings_errors('notice');
?>
<form action="" method="post">
    <?php
    settings_fields('set');
    do_settings_sections('mail');
    submit_button();
    ?>
</form>