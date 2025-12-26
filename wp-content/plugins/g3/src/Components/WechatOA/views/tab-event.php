<?php
use JEALER\G3\Utilities\Container;
use JEALER\G3\Services\WechatOAService;

$key = WechatOAService::EVENT_OPTION_KEY;
if (array_key_exists($key, $_POST) && $_POST[$key]) {
    update_option($key, $_POST[$key]);
    add_settings_error('eventReply', '1', __('Updated', 'G3'), 'updated');
}
settings_errors('eventReply');
$message = sprintf(
    __('Most event replies are displayed as the news type message, You can configure the maximum number that will be returned in <a href="%s">General Settings</a> and custom the event Key below.', 'G3'),
    admin_url('admin.php?page=wechat-oa&tab=general')
);
$message = '<div>' . $message . '</div>';
echo Container::tip(
    $message,
    'default',
    'mt-4'
);
echo '<form action="" method="post">';
settings_fields('eventReply');
do_settings_sections('wechat-oa&tab=event');
submit_button();
echo '</form>';