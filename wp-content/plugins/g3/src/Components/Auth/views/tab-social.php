<?php
use JEALER\G3\Utilities\Element;

$message = __('By letting users subscribe the WeChat official account to automatically log in, it is an extremely effective operation tool for driving WeChat users and binding user relationships.', 'G3');
echo Element::tip(
    $message,
    '',
    'default',
    'mt-4'
);

$renderer->form($panel, $panelTab);
