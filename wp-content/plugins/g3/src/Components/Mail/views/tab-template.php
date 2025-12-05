<?php
use JEALER\G3\Services\MailerService;
$key = MailerService::TEMPLATE_OPTION_KEY;
if (array_key_exists($key, $_POST) && $_POST[$key]) {
    update_option($key, $_POST[$key]);
    add_settings_error('template', 'setting_message', __('Updated!', 'G3'), 'updated');
}
settings_errors('template');
?>
<form action="" method="post">
    <?php
    settings_fields('template');
    do_settings_sections('mail&tab=template');
    submit_button();
    ?>
</form>

<script>
    var $ = jQuery;
    var enable = $('tr.template_enable');
    var select = enable.find($('select'));

    select.val() == 1 ? enable.siblings('tr').show() : enable.siblings('tr').hide();

    select.on('change', function () {
        $(this).val() == 1 ? enable.siblings('tr').show() : enable.siblings('tr').hide();
    });
</script>