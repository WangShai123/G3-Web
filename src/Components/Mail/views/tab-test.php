<?php
use JEALER\G3\Jobs\EmailJob;
use JEALER\G3\Services\SystemService;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Services\MailerService;
use JEALER\G3\Utilities\Message;

echo Element::tip(
    __('<div>Test Email will be sent according to <a href="/wp-admin/admin.php?page=performance">Performance Settings</a>. If using the default synchronous mode, the email sending will take a long time, please wait patiently.</div>', 'G3'),
    '',
    'default',
    'mt-4'
);
if (
    isset($_POST['g3_mail_test']['mailTo'])
    && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'mail-test-options')
    && current_user_can('manage_options')
) {
    $mailTo   = sanitize_email(wp_unslash($_POST['g3_mail_test']['mailTo']));
    $subject  = 'Testing Email from ' . get_bloginfo('name');
    $messages = "Bingo! Email testing complete.";

    $queue = get_option(SystemService::PERFORMANCE_OPTION_KEY, ['email' => '0'])['email'] ?? '0';
    if ($queue === '1') {
        $res = EmailJob::send($mailTo, $subject, $messages);
        if ($res === true) {
            wp_admin_notice(Message::pushed() . ' ' . __('Queue', 'G3') . sprintf(' ID: %s', $res), [
                'type'        => 'success',
                'dismissible' => true,
            ]);
        } else {
            wp_admin_notice(Message::failed() . ': ' . $res, [
                'type'        => 'error',
                'dismissible' => true,
            ]);
        }
    } else {
        $res = MailerService::send($mailTo, $subject, $messages);
        error_log('Test email send result: ' . print_r($res, true));
        if ($res === true) {
            wp_admin_notice(Message::sent(), [
                'type'        => 'success',
                'dismissible' => true,
            ]);
        } else {
            wp_admin_notice(Message::failed() . ': ' . $res, [
                'type'        => 'error',
                'dismissible' => true,
            ]);
        }
    }
}

echo '<form action="" method="post">';
wp_nonce_field('mail-test-options');
echo '<p><label for="mailTo">' . esc_html__('Test Email Address', 'G3') . '</label></p>';
echo '<input type="email" class="regular-text" id="mailTo" name="g3_mail_test[mailTo]" required>';
submit_button(__('Send Test Email', 'G3'));
echo '</form>';
