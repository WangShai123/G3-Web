<?php
use JEALER\G3\Services\MailerService;

$title   = sprintf(__('[%s] Payment successful', 'G3'), $siteName);
$content = sprintf(
    '<p>%s</p><p>%s</p>',
    sprintf(__('[%s] Payment successful', 'G3'), $siteName),
    __('You can sign in to your account to view the order details.', 'G3')
);

return [
    'subject' => $title,
    'message' => MailerService::messageHtml($title, $content, home_url('/my/order'), sprintf(__('View %s', 'G3'), __('Orders', 'G3'))),
];
