<?php
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Services\PageService;

$name = get_bloginfo('name');
if (is_user_logged_in()) {
    if (current_user_can('manage_options')) {
        wp_safe_redirect(esc_url(admin_url()), 302, $name);
        return;
    }
    if (!PageService::isAdminLogin()) {
        wp_safe_redirect(esc_url(home_url()), 302, $name);
        return;
    }
}
Frontend::css('jui');
Frontend::esm('vanilla-create-storage');
Frontend::esm('g3.login.oa');
get_header();
echo '<div id="app"></div>';
get_footer();