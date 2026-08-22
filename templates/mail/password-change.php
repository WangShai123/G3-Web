<?php
use JEALER\G3\Services\MailerService;

$title   = sprintf(__('[%s] Password Changed'), $siteName);
$content = sprintf(
    '<p>%s</p><p>%s</p><p>%s</p>',
    esc_html(sprintf(__('Hi %s,'), $userLogin)),
    esc_html(sprintf(__('This notice confirms that your password on %s was changed.'), $siteName)),
    esc_html(sprintf(__('If you did not make this change, please contact the site administrator at %s immediately.'), $adminEmail))
);

return [
    'subject' => $title,
    'message' => MailerService::messageHtml($title, $content, home_url('/'), __('Visit Site')),
];
