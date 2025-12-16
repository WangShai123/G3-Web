<?php
use JEALER\G3\Utilities\Container;
use JEALER\G3\Services\AuthService;

$key = AuthService::FOLLOW_OPTION_KEY;
if (array_key_exists($key, $_POST) && $_POST[$key]) {
    update_option($key, $_POST[$key]);
    add_settings_error('follow', '1', __('Updated', 'G3'), 'updated');
}
settings_errors('follow');
$p1      = __('By letting users follow the WeChat official account to automatically log in, it is an extremely effective operation tool for driving WeChat users and binding user relationships.', 'G3');
$p2      = __('After enabling the follow login function, the system will automatically generate a WeChat official account QR code. Users can follow the WeChat official account to complete the login.', 'G3');
$p3      = __('After enabling the follow login function, the system will automatically disable other login methods.', 'G3');
$message = <<<HTML
<div>$p1</div>
<div>$p2</div>
<div>$p3</div>
HTML;
echo Container::tip(
    $message,
    'default',
    'mt-4'
);
echo '<form action="" method="post">';
settings_fields('follow');
do_settings_sections('login-setting&tab=follow');
submit_button();
echo '</form>';
