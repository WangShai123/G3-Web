<?php
use JEALER\G3\Utilities\Container;

$msg = sprintf(
    __('Most event replies are displayed as the news type message, You can configure the maximum number that will be returned in <a href="%s">General Settings</a> and custom the event Key below.', 'G3'),
    admin_url('admin.php?page=wechat-oa&tab=general')
);
$msg = "<div>{$msg}</div>";
echo Container::tip(
    $msg,
    'default',
    'mt-4'
);
echo '<form action="" method="post">';
settings_fields('eventReply');
do_settings_sections('wechat-oa&tab=event');
submit_button();
echo '</form>';