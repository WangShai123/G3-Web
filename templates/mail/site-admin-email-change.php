<?php
use JEALER\G3\Services\MailerService;

$title   = sprintf(__('[%s] Admin Email Changed'), $siteName);
$content = sprintf(
    '<p>%s</p><p><strong>%s</strong>: %s</p><p><strong>%s</strong>: %s</p>',
    sprintf(__('[%s] Admin Email Changed'), $siteName),
    sprintf(__('%s %s', 'G3'), __('Old', 'G3'), __('Email')),
    $oldEmail,
    sprintf(__('%s %s', 'G3'), __('New', 'G3'), __('Email')),
    $newEmail
);

return [
    'subject' => $title,
    'message' => MailerService::messageHtml($title, $content, admin_url('options-general.php'), sprintf(__('Visit %s&#8217;s website'), $siteName)),
];
