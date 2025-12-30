<?php
use JEALER\G3\Utilities\Container;

$msg = sprintf(
    __('Before activating the service, please ensure that you have completed <a href="%s">the open platform setup</a>.', 'G3'),
    admin_url('admin.php?page=open-platform')
);
$msg = "<div>{$msg}</div>";
echo Container::tip(
    $msg,
    'default',
    'mt-4'
);
echo '<form action="" method="post">';
settings_fields('wechatOA');
do_settings_sections('wechat-oa');
submit_button();
echo '</form>';