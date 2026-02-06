<?php

echo '<form action="" method="post">';
settings_fields('general');
do_settings_sections('shop-settings');
submit_button();
echo '</form>';