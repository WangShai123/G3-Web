<?php

use JEALER\G3\Utilities\Container;
use JEALER\G3\Services\SystemService;

$key = SystemService::FORM_OPTION_KEY;
if (array_key_exists($key, $_POST) && $_POST[$key]) {
    update_option($key, $_POST[$key]);
    add_settings_error('formFields', '1', __('Updated', 'G3'), 'updated');
}
settings_errors('formFields');

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