<?php
use JEALER\G3\Services\MailerService;

$title       = sprintf(__('[%s] Automatic update report', 'G3'), $siteName);
$statusText  = $failures > 0 ? __('Some updates failed. Please review the report below.', 'G3') : __('Automatic updates completed. Review the report below for details.', 'G3');
$report      = trim((string) $originalBody);
$content     = sprintf(
    '<p>%s</p><pre style="white-space:pre-wrap;background:#f3f4f6;border:1px solid #e5e7eb;padding:14px;overflow:auto;">%s</pre>',
    esc_html($statusText),
    esc_html($report)
);

return [
    'subject' => $title,
    'message' => MailerService::messageHtml($title, $content, admin_url('update-core.php'), __('Open Updates', 'G3')),
];
