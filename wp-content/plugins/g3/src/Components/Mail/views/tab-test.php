<?php

use JEALER\G3\Jobs\EmailJob;
use JEALER\G3\Services\SystemService;
use JEALER\G3\Utilities\Context;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Services\MailerService;

echo Element::tip(
    __('<div>Test Email will be sent according to <a href="/wp-admin/admin.php?page=performance&tab=queue">Performance Settings</a>. If using the default synchronous mode, the email sending will take a long time, please wait patiently.</div>', 'G3'),
    '',
    'default',
    'mt-4'
);
if (array_key_exists('test', $_POST) && array_key_exists('mailTo', $_POST['test']) && $_POST['test']['mailTo']) {
    $mailTo   = $_POST['test']['mailTo'];
    $subject  = __('Testing Email from ' . get_bloginfo('name'), 'G3');
    $messages = __("Congratulations! If you receive this email, the email configuration is correct.", 'G3');

    $queue = Context::get(SystemService::QUEUE_OPTION_KEY)['email'] ?? '0';
    if ($queue === '1') {
        $res = EmailJob::send($mailTo, $subject, $messages);
        if ($res) {
            add_settings_error('notice', 'updated', sprintf(__('Test email push successfully! Queue ID: %s', 'G3'), $res), 'updated');
        } else {
            add_settings_error('notice', 'failed', __('Failed to push test email!', 'G3'), 'error');
        }
    } else {
        $res = MailerService::send($mailTo, $subject, $messages);
        if ($res) {
            add_settings_error('notice', 'updated', __('Test email sent successfully!', 'G3'), 'updated');
        } else {
            add_settings_error('notice', 'failed', __('Failed to send test email!', 'G3'), 'error');
        }
    }
    settings_errors('notice');
}

echo '<div class="wrap"><form action="" method="post">';
settings_fields('test');
do_settings_sections('mail&tab=test');
submit_button(__('Send Test Email', 'G3'));
echo '</form></div>';
