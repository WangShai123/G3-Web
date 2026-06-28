<?php
use JEALER\G3\Utilities\Element;

$p1  = __('You can configure different theme templates for different types of devices to improve user experience.', 'G3');
$p2  = __('The theme you configure on the Appearance-Theme page is the main theme of the system. When the option corresponding to the multi-theme mode is empty, the main theme will be used first by default.', 'G3');
$msg = "<div>{$p1}</div><div>{$p2}</div>";
echo Element::tip(
    $msg,
    '',
    'default',
    'mt-4'
);

$renderer->form($panel, $panelPage);
