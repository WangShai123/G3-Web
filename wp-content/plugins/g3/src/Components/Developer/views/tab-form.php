<?php
use JEALER\G3\Utilities\Container;

echo '<form action="" method="post">';
echo Container::tip(
    __('When passing title and description data in the form method, please do not forget to perform internationalization handling.', 'G3'),
    'default',
    'mt-4'
);
settings_fields('formFields');
do_settings_sections('developer-mode&tab=form');
submit_button();
echo '</form>';