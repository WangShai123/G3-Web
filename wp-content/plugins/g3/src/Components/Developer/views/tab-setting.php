<?php
use JEALER\G3\Utilities\Container;

$p1      = __('If you want to hide the developer mode menu, define the constant in your theme <code>define("G3_HIDE_DEVELOPER_MODE", true);</code>', 'G3');
$p2      = __('Disable some unused WordPress functions, make the admin panel clean, and avoid some mistakes.', 'G3');
$p3      = __('Disable WordPress auto update related functions, can significantly improve the speed of the backend access.', 'G3');
$message = <<<HTML
<div>1. $p1</div>
<div>2. $p2</div>
<div>3. $p3</div>
HTML;
echo '<form action="" method="post">';
echo Container::tip(
    $message,
    'default',
    'mt-4'
);
settings_fields('devSetting');
do_settings_sections('developer-mode&tab=setting');
submit_button();
echo '</form>';