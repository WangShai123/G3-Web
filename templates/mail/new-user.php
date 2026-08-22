<?php
use JEALER\G3\Services\MailerService;

$title       = sprintf(__('Welcome to %s'), $siteName);
$displayName = $user instanceof WP_User ? ($user->display_name ?: $user->user_login) : $userLogin;
$content     = sprintf(
    '<p>%s</p><p>%s</p><p>%s</p><p>%s</p>',
    esc_html(sprintf(__('Hi %s,'), $displayName)),
    esc_html(sprintf(__('Your account on %s has been created.'), $siteName)),
    esc_html(sprintf(__('The password setup link is valid for %d minutes.'), (int) ($expirationMinutes ?? 60))),
    esc_html(__('Please set your password before signing in for the first time.'))
);

return [
    'subject' => $title,
    'message' => MailerService::messageHtml($title, $content, $resetUrl, __('Set Password')),
];
