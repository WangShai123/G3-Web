<?php
use JEALER\G3\Utilities\Element;

$p1      = __('Disable some unused WordPress functions, make the admin panel clean, and avoid some mistakes.', 'G3');
$p2      = __('Disable WordPress auto update related functions, can significantly improve the speed of the backend access.', 'G3');
$message = <<<HTML
<ol>
<li>$p1</li>
<li>$p2</li>
</ol>
HTML;
echo Element::tip(
    $message,
    '',
    'default',
    'mt-4'
);
$renderer->form($panel, $panelTab);
