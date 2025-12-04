<?php
if (array_key_exists('g3_option_seo', $_POST) && $_POST['g3_option_seo']) {
    update_option('g3_option_seo', $_POST['g3_option_seo']);
    add_settings_error('seo', 'setting_message', __('Updated!', 'G3'), 'updated');
}
settings_errors('seo');
?>
<form action="" method="POST">
    <?php
    settings_fields('seo');
    do_settings_sections('digital-operations&tab=seo');
    submit_button();
    ?>
</form>