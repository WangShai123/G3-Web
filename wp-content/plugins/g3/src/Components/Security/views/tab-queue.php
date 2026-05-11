<?php

use JEALER\G3\Utilities\Element;

echo Element::tip(
    __('This tab is for queue configuration and website performance advice.', 'G3'),
    '',
    'default',
    'mt-4'
);
?>
<form action="" method="POST">
    <?php
    settings_fields('queue');
    do_settings_sections('performance');
    submit_button();
    ?>
</form>