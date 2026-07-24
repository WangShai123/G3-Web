<?php
use JEALER\G3\Services\MailerService;

$title       = sprintf(__('Welcome to %s', 'G3'), $siteName);
$displayName = $user instanceof WP_User ? ($user->display_name ?: $user->user_login) : $userLogin;
$content     = sprintf(
    '<p>%s</p><p>%s</p><p>%s</p><p>%s</p>',
    esc_html(sprintf(__('Hi %s,', 'G3'), $displayName)),
    esc_html(sprintf(__('Your account on %s has been created.', 'G3'), $siteName)),
    esc_html(sprintf(__('The password setup link is valid for %d minutes.', 'G3'), (int) ($expirationMinutes ?? 60))),
    esc_html(__('Please set your password before signing in for the first time.', 'G3'))
);

return [
    'subject' => $title,
    'message' => MailerService::messageHtml($title, $content, $resetUrl, __('Set Password', 'G3')),
];
