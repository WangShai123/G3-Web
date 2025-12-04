<?php
if (array_key_exists('g3_option_op_weiBo', $_POST) && $_POST['g3_option_op_weiBo']) {
    update_option('g3_option_op_weiBo', $_POST['g3_option_op_weiBo']);
    add_settings_error('opWechatMP', '1', __('Updated!', 'G3'), 'updated');
}
?>
<?php settings_errors('opWeiBo'); ?>
<form action="" method="post">
    <?php
    settings_fields('opWeiBo');
    do_settings_sections('open-platform&tab=weiBo');
    submit_button();
    ?>
</form>