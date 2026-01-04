<?php
namespace JEALER\G3\Components;

use JEALER\G3\Components;
use JEALER\G3\Services\SidebarService;
use Override;

class Comment extends Components {
    #[Override]
    protected function init(): void
    {
    }
    #[Override]
    protected function admin(): void
    {
        $this->autoclosePingback();
        add_action('admin_footer-options-discussion.php', [$this, 'hideSettingsInDiscussionOptions']);
    }

    private function autoclosePingback(): void
    {
        update_option('default_pingback_flag', '');
        update_option('default_ping_status', 'closed');
    }
    public function hideSettingsInDiscussionOptions(): void
    {
        echo <<<HTML
<style>
h2.title,
h2.title + p,
h2.title + p + table,
label[for=default_pingback_flag],
label[for=default_pingback_flag] + br,
label[for=default_ping_status],
label[for=default_ping_status] + br
{display: none !important;}
</style>
HTML;
    }

    protected function widgets(): void
    {
        // Remove default recent comments widget
        unregister_widget('WP_Widget_Recent_Comments');

        // Register custom recent comments widget
        SidebarService::registerWidget('CommentWidget', __DIR__);
    }
    protected function dashboard(): void
    {
        remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
    }
}