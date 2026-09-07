<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use JEALER\G3\Core\Admin\Panel;
use JEALER\G3\Services\SidebarService;
use JEALER\G3\Services\SystemService;
use JEALER\G3\Utilities\Response;
use Override;
use WP_Query;

class Performance extends Components {

    protected function hooks()
    {
        $this->filter([
            'redis_cache_expiration' => [[$this, 'setAdminCacheTTL'], 10, 3],
        ]);
    }
    private function optionDefaults(): array
    {
        return [
            'email' => '0',
        ];
    }
    #[Override]
    protected function adminMenu(): void
    {
        add_submenu_page(
            'g3-settings',
            __('Performance', 'G3'),
            __('Performance', 'G3'),
            'manage_options',
            'performance',
            [$this, 'render'],
            19
        );
    }
    protected function adminPanels(): array
    {
        $code       = '<code>define("G3_WP_BUILTIN_CACHE_TTL", 24);</code>';
        $currentTTL = defined('G3_WP_BUILTIN_CACHE_TTL') ? sprintf(__('%d hours'), (int) G3_WP_BUILTIN_CACHE_TTL) : __('None');
        $msg        = $this->description(sprintf(__('Configure the constant %s in <code>wp-config</code> to set cache expiration for some built-in functions in WordPress to avoid redundant junk data. Default: 24 hours. Current value: %s.', 'G3'), $code, $currentTTL));

        $wpConfig = <<<HTML
<code>define('AUTOMATIC_UPDATER_DISABLED', true);</code><br>
<code>define('WP_AUTO_UPDATE_CORE', false);</code><br>
<code>define('DISABLE_WP_CRON', true);</code>
HTML;

        return [
            $this->panel('performance', __('Performance', 'G3'))
                ->tab('general', __('General'))
                ->option(SystemService::PERFORMANCE_OPTION_KEY, $this->optionDefaults())
                ->switch('email', __('Email Queue', 'G3'), __('Before enabling this feature, please ensure that the relevant queue consumption function is already enabled. This is because, after this feature is enabled, SMTP emails will not be sent directly; instead, sending tasks will be pushed to a service queue, where systemd or supervisor will handle the consumption and execution. This will improve the system\'s concurrency and asynchronous processing capabilities.', 'G3'))
                ->rowClass('advanced')
                ->html('adminCache', sprintf(__('%s %s', 'G3'), __('Query', 'G3'), __('Cache', 'G3')), $msg)
                ->rowClass('advanced')
                ->html('wpConfig', 'wp-config', $this->description($wpConfig))
                ->html('queue', __('Queue', 'G3'), $this->description(__('If you want to use scheduled tasks, please use the Queue and Job services provided by G3-Web, which combine systemd or supervisor for high-performance and highly maintainable system-level features.', 'G3')))
                ->tab('cleaner', __('Junk Cleaner', 'G3'))
                ->callback('draft', __('Draft'), fn() => $this->renderActions('draft'))
                ->callback('auto-draft', __('Auto Draft'), fn() => $this->renderActions('auto-draft'))
                ->callback('trash', __('Trash', 'G3'), fn() => $this->renderActions('trash'))
                ->callback('revision', __('Revision'), fn() => $this->renderActions('revision'))
                ->tab('fastcgi', 'FastCGI')
                ->tab('redis', 'Redis')
                ->tab('theme', __('Theme'))
                ->tab('queue', __('Queue', 'G3'))
                ->tab('consumer', __('Consumer', 'G3'))
        ];
    }
    private function description(string $description): string
    {
        return '<p class="description">' . $description . '</p>';
    }
    private function renderActions(string $action)
    {
        $clear = __('Clear');
        echo "<button class='button cleaner-action-button is-hidden' type='button' data-action='{$action}' data-count=''>{$clear}</button>";
    }
    protected function adminPanelPage(): string
    {
        return 'performance';
    }
    public function render(): void
    {
        $this->createPanel();
    }
    protected function widgets()
    {
        SidebarService::registerWidget('MonitorWidget', __DIR__);
    }

    public function setAdminCacheTTL($ttl, $key, $group)
    {
        $hours = (int) (defined('G3_WP_BUILTIN_CACHE_TTL') ? G3_WP_BUILTIN_CACHE_TTL : 48);

        if ($hours <= 0) {
            return $ttl;
        }

        $targetGroups = [
            'bookmark',
            'category_relationships',
            'comment',
            'comment_meta',
            'comment-queries',
            // 'default',
            'nav_menu_relationships',
            'post_format_relationships',
            'post_meta',
            'post_tag_relationships',
            'posts',
            'term_meta',
            'post-queries',
            'term-queries',
            'terms',
            'user_meta',
            'user-queries',
            'useremail',
            'userlogins',
            'users',
            'userslugs',
        ];

        if (in_array($group, $targetGroups, true)) {
            return HOUR_IN_SECONDS * $hours;
        }

        return $ttl;
    }
    private function addJunkCleanerAjax(string $action, string $status, string $postType = 'any')
    {
        add_action('wp_ajax_' . $action, function () use ($status, $postType) {
            if (!current_user_can('manage_options') || !is_admin()) {
                Response::ajaxIllegal();
            }
            $result = $this->deletePostsByStatus($status, $postType);
            if ($result) {
                Response::ajaxDeleted();
            } else {
                Response::ajaxFailed();
            }
        });
    }
    private function deletePostsByStatus(string $status, string $postType = 'any'): bool
    {
        $query = new WP_Query([
            'post_type'      => $postType,
            'post_status'    => $status,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        while ($query->have_posts()) {
            $query->the_post();
            wp_delete_post(get_the_ID(), true);
        }
        wp_reset_postdata();
        return $query->found_posts > 0;
    }
    protected function ajax(): void
    {
        $this->addJunkCleanerAjax('g3_clear_draft', 'draft');
        $this->addJunkCleanerAjax('g3_clear_auto_draft', 'auto-draft');
        $this->addJunkCleanerAjax('g3_clear_trash', 'trash');
        $this->addJunkCleanerAjax('g3_clear_revision', 'inherit', 'revision');
        add_action('wp_ajax_g3_scan_trash', function () {
            if (!current_user_can('manage_options') || !is_admin()) {
                Response::ajaxIllegal();
            }
            $result = $this->scanTrash();
            wp_send_json_success($result, 200);
        });
    }
    private function scanTrash(): array
    {
        $postTypes = get_post_types([
            'public'  => true,
            'show_ui' => true,
        ]);
        unset($postTypes['attachment']);
        $postTypes = array_values($postTypes);

        $draft     = 0;
        $autoDraft = 0;
        $trash     = 0;

        foreach ($postTypes as $postType) {
            $postCount  = wp_count_posts($postType);
            $draft     += $postCount->draft ?? 0;
            $autoDraft += $postCount->{'auto-draft'} ?? 0;
            $trash     += $postCount->trash ?? 0;
        }

        $revisionQuery = new WP_Query([
            'post_type'      => 'revision',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'fields'         => 'ids'
        ]);
        $revision      = $revisionQuery->found_posts;

        return [
            'draft'      => $draft,
            'auto-draft' => $autoDraft,
            'trash'      => $trash,
            'revision'   => $revision
        ];
    }
}
