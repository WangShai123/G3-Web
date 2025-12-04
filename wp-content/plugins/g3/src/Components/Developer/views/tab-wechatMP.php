<?php
if (array_key_exists('g3_option_op_wechatMP', $_POST) && $_POST['g3_option_op_wechatMP']) {
    update_option('g3_option_op_wechatMP', $_POST['g3_option_op_wechatMP']);
    add_settings_error('opWechatMP', '1', __('Updated!', 'G3'), 'updated');
}
?>
<?php settings_errors('opWechatMP'); ?>
<form action="" method="post">
    <?php
    settings_fields('opWechatMP');
    do_settings_sections('open-platform&tab=mp');
    submit_button();
    ?>
</form>