<?php
use JEALER\G3\Utilities\Container;

$message = sprintf(
    __('Before enabling related login functions, please ensure to complete the relevant <a href="%s">Open Platform</a> settings.', 'G3'),
    admin_url('admin.php?page=open-platform')
);
$message = '<div>' . $message . '</div>';
echo Container::tip(
    $message,
    'default',
    'mt-4'
);
echo '<form action="" method="post">';
settings_fields('general');
do_settings_sections('auth-settings');
submit_button();
echo '</form>';