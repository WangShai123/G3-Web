<?php
use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Frontend;
// $renderer->form($panel, $panelTab);

// echo Element::tip(
//     __('Please follow the prompts.', 'G3'),
//     sprintf(__('%s %s', 'G3'), __('Posts'), __('Collection', 'G3'))
// );

Frontend::umd('g3.admin.collection');

echo '<div id="terminal-container"></div><div id="form-container"></div>';
?>

<style>
    #terminal-container,
    #form-container {
        margin-top: 16px;
    }

    #form-container {
        max-width: 480px;
    }
</style>