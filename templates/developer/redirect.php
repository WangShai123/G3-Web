<?php
// use JEALER\G3\Utilities\Validator;

$encoded = get_query_var('g3_var_redirect_url');
if (!$encoded) {
    wp_safe_redirect(get_bloginfo('url'), 302, 'G3-Web');
    exit;
}

// $url = rawurldecode($encoded);

// if (!Validator::safeRedirectUrl($url)) {
//     wp_die(
//         __('The URL you visited is illegal.', 'G3'),
//         __('Warning', 'G3') . ' - ' . get_bloginfo('name'),
//         [
//             'response'  => 400,
//             'charset'   => 'utf-8',
//             'back_link' => true,
//             'exit'      => true
//         ]
//     );
// }

get_header();
?>

<div class="container" id="g3-redirect-container"></div>
<!-- @todo: ad container. -->
<div class="ad" id="g3-redirect-ad"></div>
<?php
get_footer();
