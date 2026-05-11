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
Frontend::loadStyle('jui');
Frontend::loadModule('jui');
Frontend::loadModule('g3.login.oa');
get_header();
?>

<div class="j-background-grid"></div>
<div class="oa-container flex flex-col items-center justify-center w-full">
    <h1 class="text-center -translate-y-24px"><?php echo $name; ?></h1>
</div>

<style>
    .oa-container {
        height: calc(100vh - 100px);
        min-height: fit-content;
    }

    .oa-container section {
        display: flex;
        justify-content: center;
    }

    .-translate-y-24px {
        transform: translateY(-24px);
    }

    .e {
        border-color: var(--red-9) !important;
    }
</style>

<?php
get_footer();
