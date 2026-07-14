<?php
use JEALER\G3\Utilities\Element;

$message = sprintf(
    __('Before enabling related login functions, please ensure to complete the relevant <a href="%s">Open Platform</a> settings.', 'G3'),
    admin_url('admin.php?page=open-platform')
);
$message = '<div>' . $message . '</div>';
echo Element::tip(
    $message,
    '',
    'default',
    'mt-4'
);

$renderer->form($panel, $panelTab);
