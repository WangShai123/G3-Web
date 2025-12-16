<?php
use JEALER\G3\Utilities\Container;
use JEALER\G3\Services\WechatOAService;

$key = WechatOAService::OPTION_KEY;
if (array_key_exists($key, $_POST) && $_POST[$key]) {
    update_option($key, $_POST[$key]);
    add_settings_error('general', '1', __('Updated', 'G3'), 'updated');
}
settings_errors('general');
$message = __('Before activating the service, please ensure that you have completed <a href="%s">the open platform setup</a>.', 'G3');
$message = '<div>' . $message . '</div>';
echo Container::tip(
    $message,
    'default',
    'mt-4'
);
echo '<form action="" method="post">';
settings_fields('wechatOA');
do_settings_sections('wechat-oa');
submit_button();
echo '</form>';