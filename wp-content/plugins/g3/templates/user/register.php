<?php
use JEALER\G3\Utilities\Frontend;

Frontend::css('jui');
Frontend::umd('jui');
Frontend::umd('axios');
Frontend::umd('axios.cache');

get_header();
echo 'register';
get_footer();