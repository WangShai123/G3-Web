<?php
use JEALER\G3\Utilities\Frontend;

Frontend::loadStyle('jui');
Frontend::loadScript('jui');
Frontend::loadScript('axios');
Frontend::loadScript('axios.cache');

get_header();
echo 'register';
get_footer();