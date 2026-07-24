<?php
use JEALER\G3\Services\MailerService;

$title   = sprintf(__('[%s] Payment successful', 'G3'), $siteName);
$content = sprintf(
    '<p>%s</p><p>%s</p>',
    esc_html(__('Your payment has been received successfully.', 'G3')),
    esc_html(__('You can sign in to your account to view the order details.', 'G3'))
);

return [
    'subject' => $title,
    'message' => MailerService::messageHtml($title, $content, home_url('/my/order'), __('View Orders', 'G3')),
];
