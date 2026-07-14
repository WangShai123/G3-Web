<?php
use JEALER\G3\Utilities\Message;

$my = get_query_var('g3_var_my');
if ($my !== 'home') {
    wp_safe_redirect(home_url('/my/home'), 302, 'G3-Web');
    exit;
}
get_header();
$test = get_template_part('parts/header/index1');
if (false === $test) {
    echo Message::templateNotImplemented('my/home');
}
get_footer();
