<?php
use JEALER\G3\Services\MailerService;
use WP_User;

$title       = $siteName . ' ' . __('Reset Password');
$displayName = $user instanceof WP_User ? ($user->display_name ?: $user->user_login) : $userLogin;
$content     = sprintf(
    '<p>Hi!</p><p>%s</p><p>%s</p><p>%s</p><p>%s</p>',
    esc_html(sprintf(__('Someone has requested a password reset for the following account:' . ' %s'), $displayName)),
    esc_html(sprintf(__('The password reset link is valid for %d minutes.', 'G3'), (int) ($expirationMinutes ?? 60))),
    esc_html(__('If this was a mistake, ignore this email and nothing will happen.')),
    esc_html(__('To reset your password, visit the following address:'))
);

return [
    'subject' => $title,
    'message' => MailerService::messageHtml($title, $content, $resetUrl, __('Reset Password')),
];
