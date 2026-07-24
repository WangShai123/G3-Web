<?php
use JEALER\G3\Services\MailerService;

$title   = sprintf(__('[%s] Email address changed', 'G3'), $siteName);
$content = sprintf(
    '<p>%s</p><p>%s</p><p><strong>%s</strong>: %s<br><strong>%s</strong>: %s</p><p>%s</p>',
    esc_html(sprintf(__('Hi %s,', 'G3'), $userLogin)),
    esc_html(sprintf(__('This notice confirms that your account email address on %s was changed.', 'G3'), $siteName)),
    esc_html(__('Old Email', 'G3')),
    esc_html($oldEmail),
    esc_html(__('New Email', 'G3')),
    esc_html($newEmail),
    esc_html(sprintf(__('If you did not make this change, please contact the site administrator at %s immediately.', 'G3'), $adminEmail))
);

return [
    'subject' => $title,
    'message' => MailerService::messageHtml($title, $content, home_url('/'), __('Visit Site', 'G3')),
];
