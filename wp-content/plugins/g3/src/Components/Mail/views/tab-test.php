<?php
use JEALER\G3\Services\MailerService;
if (array_key_exists('test', $_POST) && array_key_exists('mailTo', $_POST['test']) && $_POST['test']['mailTo']) {
    $mailTo = $_POST['test']['mailTo'];
    // $headers = ['Content-Type: text/html; charset=UTF-8'];
    // $res     = wp_mail(
    //     $mailTo,
    //     __('Testing Email from ' . get_bloginfo('name'), 'G3'),
    //     __("Congratulations! If you receive this email, the email configuration is correct.", 'G3'),
    //     $headers
    // );
    $subject = __('Testing Email from ' . get_bloginfo('name'), 'G3');
    $body    = __("Congratulations! If you receive this email, the email configuration is correct.", 'G3');
    $res     = MailerService::send($mailTo, $subject, $body);

    if ($res) {
        add_settings_error('test', 'setting_message', __('Test email sent successfully!', 'G3'), 'updated');
    } else {
        add_settings_error('test', 'setting_message', __('Failed to send test email!', 'G3'), 'error');
    }
}
?>
<div class="wrap">
    <?php settings_errors(setting: 'test'); ?>
    <form action="" method="post">
        <?php
        settings_fields('test');
        do_settings_sections('mail&tab=test');
        submit_button(__('Send Test Email', 'G3'));
        ?>
    </form>
</div>