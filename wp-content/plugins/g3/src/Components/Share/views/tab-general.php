<?php
if (array_key_exists('g3_option_social_share', $_POST) && $_POST['g3_option_social_share']) {
    update_option('g3_option_social_share', $_POST['g3_option_social_share']);
    add_settings_error('general', '1', __('Updated!', 'G3'), 'updated');
}
settings_errors('general');
?>
<form action="" method="post">
    <?php
    settings_fields('general');
    do_settings_sections('share-setting&tab=general');
    submit_button();
    ?>
</form>