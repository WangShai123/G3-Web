<?php
use JEALER\G3\Utilities\Element;

echo Element::tip(
    __('The actions below will flush the Rewrite / Options / Cache data, please make sure you have a backup before performing the action.', 'G3'),
    '',
    'default',
    'mt-4'
);
$renderer->form($panel, $panelTab);
?>

<style>
    #submit {
        display: none
    }
</style>
