<?php
use JEALER\G3\Services\MailerService;

$title   = sprintf(__('[%s] Site admin email changed', 'G3'), $siteName);
$content = sprintf(
    '<p>%s</p><p><strong>%s</strong>: %s<br><strong>%s</strong>: %s</p>',
    esc_html(sprintf(__('The site administrator email address on %s was changed.', 'G3'), $siteName)),
    esc_html(__('Old Email', 'G3')),
    esc_html($oldEmail),
    esc_html(__('New Email', 'G3')),
    esc_html($newEmail)
);

return [
    'subject' => $title,
    'message' => MailerService::messageHtml($title, $content, admin_url('options-general.php'), __('Open Settings', 'G3')),
];
