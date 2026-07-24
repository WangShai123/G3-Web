<?php
use JEALER\G3\Services\MailerService;

$title   = sprintf(__('[%s] Password changed', 'G3'), $siteName);
$content = sprintf(
    '<p>%s</p><p>%s</p><p>%s</p>',
    esc_html(sprintf(__('Hi %s,', 'G3'), $userLogin)),
    esc_html(sprintf(__('This notice confirms that your password on %s was changed.', 'G3'), $siteName)),
    esc_html(sprintf(__('If you did not make this change, please contact the site administrator at %s immediately.', 'G3'), $adminEmail))
);

return [
    'subject' => $title,
    'message' => MailerService::messageHtml($title, $content, home_url('/'), __('Visit Site', 'G3')),
];
