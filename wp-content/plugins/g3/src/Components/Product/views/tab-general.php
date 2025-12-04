<?php
use JEALER\G3\Utilities\Frontend;
if (array_key_exists('g3_option_shop', $_POST) && $_POST['g3_option_shop']) {
    update_option('g3_option_shop', $_POST['g3_option_shop']);
    add_settings_error('general', '1', __('Updated!', 'G3'), 'updated');
}
settings_errors('general');
Frontend::loadStyle('jui');
?>

<form action="" method="post">
    <?php
    settings_fields('general');
    do_settings_sections('shop-setting');
    submit_button();
    ?>
</form>