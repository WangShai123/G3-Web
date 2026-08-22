<?php
use JEALER\G3\Services\MailerService;

$title    = sprintf(__('[%s] Password Changed'), $siteName);
$username = $user instanceof WP_User ? $user->user_login : '';
$content  = sprintf(
    '<p>%s</p><p><strong>%s</strong>: %s</p>',
    esc_html(sprintf(__('Password changed for user: %s'), $username)),
    esc_html(__('Email')),
    esc_html($user instanceof WP_User ? $user->user_email : '')
);

return [
    'subject' => $title,
    'message' => MailerService::messageHtml($title, $content, admin_url('user-edit.php?user_id=' . (int) ($user->ID ?? 0)), __('View User')),
];
