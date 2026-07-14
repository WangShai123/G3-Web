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
    private string $adminCacheTTL       = '0';
    private bool   $adminCacheResolving = false;

    protected function start()
    {
        $this->adminCacheTTL = $this->loadAdminCacheTTL();
    }
    protected function hooks()
    {
        $this->filter([
            'redis_cache_expiration' => [[$this, 'setAdminCacheTTL'], 10, 3],
        ]);
    }
    private function optionDefaults(): array
    {
        return [
            'email'      => '0',
            'adminCache' => '0',
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
        $eg1 = '<p class="mt-1"><code>post-queries</code>, <code>term-queries</code>, <code>comment-queries</code>, <code>user-queries</code></p>';
        $eg2 = '<p class="mt-1"><code>*_relationships</code></p>';
        return [
            $this->panel('performance', __('Performance', 'G3'))
                ->tab('general', __('General'))
                ->option(SystemService::PERFORMANCE_OPTION_KEY, $this->optionDefaults())
                ->switch('email', __('Email Queue', 'G3'))
                ->rowClass('advanced')
                ->select('adminCache', __('Query', 'G3') . ' ' . __('Cache', 'G3'), [
                    '0'   => __('Disabled'),
                    '1'   => sprintf(__('%s hour'), 1),
                    '3'   => sprintf(__('%d hours'), 3),
                    '6'   => sprintf(__('%d hours'), 6),
                    '12'  => sprintf(__('%d hours'), 12),
                    '24'  => sprintf(__('%d hours'), 24),
                    '72'  => sprintf(__('%d days'), 3),
                    '168' => sprintf(__('%d days'), 7),
                ], __('Set cache expiration for some built-in functions in WordPress to avoid redundant junk data.', 'G3') . $eg1 . $eg2)
                ->rowClass('advanced')
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
    private function loadAdminCacheTTL(): string
    {
        if ($this->adminCacheResolving) {
            return '0';
        }

        $this->adminCacheResolving = true;
        $option                    = get_option(SystemService::PERFORMANCE_OPTION_KEY, $this->optionDefaults());
        $this->adminCacheResolving = false;

        return is_array($option) ? (string) ($option['adminCache'] ?? '0') : '0';
    }
    public function setAdminCacheTTL($ttl, $key, $group)
    {
        if ($this->adminCacheResolving) {
            return $ttl;
        }

        $hours = (int) $this->adminCacheTTL;
        if ($hours <= 0) {
            return $ttl;
        }

        $targetGroups = ['term-queries', 'post-queries', 'user-queries', 'comment-queries'];
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
