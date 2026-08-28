<?php
use JEALER\G3\Utilities\Element;

$msg = sprintf(
    __('Before enabling related features, please ensure to complete the <a href="%s">%s</a> settings.', 'G3'),
    admin_url('admin.php?page=open-platform'),
    __('Open Platform', 'G3')
);
$msg = "<div>{$msg}</div>";
echo Element::tip(
    $msg,
    '',
    'default',
    'mt-4'
);
$renderer->form($panel, $panelTab);
