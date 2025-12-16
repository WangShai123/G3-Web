<?php
use JEALER\G3\Utilities\Container;
use JEALER\G3\Services\AuthService;

$key = AuthService::OPTION_KEY;
if (array_key_exists($key, $_POST) && $_POST[$key]) {
    update_option($key, $_POST[$key]);
    add_settings_error('general', '1', __('Updated', 'G3'), 'updated');
}
settings_errors('general');
$message = sprintf(
    __('Before enabling related login functions, please ensure to complete the relevant <a href="%s">Open Platform</a> settings.', 'G3'),
    admin_url('options-general.php?page=open-platform')
);
$message = '<div>' . $message . '</div>';
echo Container::tip(
    $message,
    'default',
    'mt-4'
);
echo '<form action="" method="post">';
settings_fields('general');
do_settings_sections('login-setting');
submit_button();
echo '</form>';