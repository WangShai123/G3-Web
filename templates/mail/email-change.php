<?php
use JEALER\G3\Services\MailerService;

$title   = sprintf(__('[%s] Email Changed'), $siteName);
$content = sprintf(
    '<p>%s</p><p>%s</p><p><strong>%s</strong>: %s<br><strong>%s</strong>: %s</p><p>%s</p>',
    esc_html(sprintf(__('Hi %s,'), $userLogin)),
    esc_html(sprintf(__('This notice confirms that your account email address on %s was changed.'), $siteName)),
    esc_html(__('Old Email')),
    esc_html($oldEmail),
    esc_html(__('New Email')),
    esc_html($newEmail),
    esc_html(sprintf(__('If you did not make this change, please contact the site administrator at %s immediately.'), $adminEmail))
);

return [
    'subject' => $title,
    'message' => MailerService::messageHtml($title, $content, home_url('/'), __('Visit Site')),
];
