<?php
use JEALER\G3\Utilities\Frontend;

Frontend::loadStyle('jui');

echo '<form action="" method="post">';
settings_fields('general');
do_settings_sections('shop-setting');
submit_button();
echo '</form>';