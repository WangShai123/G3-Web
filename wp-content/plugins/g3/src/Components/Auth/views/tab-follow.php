<?php
use JEALER\G3\Utilities\Container;

$p1      = __('By letting users subscribe the WeChat official account to automatically log in, it is an extremely effective operation tool for driving WeChat users and binding user relationships.', 'G3');
$p2      = __('After enabling the Subscribe Login function, the system will automatically generate a WeChat official account QR code. Users can subscribe the WeChat official account to complete the login.', 'G3');
$p3      = __('After enabling the Subscribe Login function, in the Desktop mode, the system will automatically disable other login methods.', 'G3');
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
settings_fields('subscribe');
do_settings_sections('auth-settings&tab=subscribe');
submit_button();
echo '</form>';
