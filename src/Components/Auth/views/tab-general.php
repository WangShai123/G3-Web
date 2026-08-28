<?php
use JEALER\G3\Utilities\Element;

$message = sprintf(
    __('Before enabling related features, please ensure to complete the <a href="%s">%s</a> settings.', 'G3'),
    admin_url('admin.php?page=open-platform'),
    __('Open Platform', 'G3')
);
$message = '<div>' . $message . '</div>';
echo Element::tip(
    $message,
    '',
    'default',
    'mt-4'
);

$renderer->form($panel, $panelTab);
