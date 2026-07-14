<?php
use JEALER\G3\Utilities\Element;

echo Element::tip(
    __('This tab is for queue configuration and website performance advice.', 'G3'),
    '',
    'default',
    'mt-4'
);
$renderer->form($panel, $panelTab);
?>
