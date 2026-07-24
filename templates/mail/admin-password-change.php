<?php
use JEALER\G3\Services\MailerService;

$title   = sprintf(__('[%s] User password changed', 'G3'), $siteName);
$content = sprintf(
    '<p>%s</p><p><strong>%s</strong>: %s<br><strong>%s</strong>: %s</p>',
    esc_html(sprintf(__('A user password was changed on %s.', 'G3'), $siteName)),
    esc_html(__('Username', 'G3')),
    esc_html($user instanceof WP_User ? $user->user_login : ''),
    esc_html(__('Email')),
    esc_html($user instanceof WP_User ? $user->user_email : '')
);

return [
    'subject' => $title,
    'message' => MailerService::messageHtml($title, $content, admin_url('user-edit.php?user_id=' . (int) ($user->ID ?? 0)), __('View User', 'G3')),
];
