<?php
namespace JEALER\G3\Components\Developer\Includes;
use WP_List_Table;
use Closure;

class CronListTable extends WP_List_Table {
    private int $perPage;
    public function __construct()
    {
        parent::__construct([
            'singular' => 'cron_job',
            'plural'   => 'cron_jobs',
            'ajax'     => false,
        ]);
        $this->perPage = 200;
    }

    public function get_columns()
    {
        return [
            'cb'       => '<input type="checkbox" />',
            'hook'     => __('Hook'),
            'next_run' => __('Next Run') . ' (' . wp_timezone_string() . ')',
            'schedule' => __('Schedule'),
            'action'   => __('Action'),
            'build_in' => __('Build In'),
        ];
    }

    public function get_bulk_actions()
    {
        return [
            'delete' => __('Delete'),
        ];
    }

    public function get_sortable_columns()
    {
        return [
            'hook'     => ['hook', false],
            'next_run' => ['next_run', false],
            'schedule' => ['schedule', false],
            'action'   => ['action', false],
            'build_in' => ['build_in', false],
        ];
    }

    public function prepare_items()
    {
        $this->process_bulk_action();

        $data = $this->get_cron_jobs();
        $data = $this->sort_items($data);

        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
        $this->items           = $data;

        // Use built-in tablenav pagination area to show total items, without slicing rows.
        $this->set_pagination_args([
            'total_items' => count($data),
            'per_page'    => $this->perPage,
            'total_pages' => 1,
        ]);
    }

    private function sort_items(array $items): array
    {
        $sortable = array_keys($this->get_sortable_columns());
        $orderby  = isset($_REQUEST['orderby']) ? sanitize_key((string) $_REQUEST['orderby']) : 'next_run';
        $order    = isset($_REQUEST['order']) ? strtolower((string) $_REQUEST['order']) : 'desc';

        if (!in_array($orderby, $sortable, true)) {
            $orderby = 'next_run';
        }

        if (!in_array($order, ['asc', 'desc'], true)) {
            $order = 'desc';
        }

        usort($items, function (array $a, array $b) use ($orderby, $order): int {
            $left  = $this->sort_value($a, $orderby);
            $right = $this->sort_value($b, $orderby);

            if ($left == $right) {
                return 0;
            }

            $result = ($left < $right) ? -1 : 1;
            return $order === 'asc' ? $result : -$result;
        });

        return $items;
    }

    private function sort_value(array $item, string $orderby)
    {
        return match ($orderby) {
            'next_run' => (int) ($item['timestamp'] ?? 0),
            'action'   => implode(',', is_array($item['action'] ?? null) ? $item['action'] : []),
            'build_in' => !empty($item['is_build_in']) ? 1 : 0,
            default    => strtolower((string) ($item[$orderby] ?? '')),
        };
    }

    public function column_cb($item)
    {
        if (!empty($item['is_build_in'])) {
            return '';
        }

        return sprintf(
            '<input type="checkbox" name="cron_jobs[]" value="%s" />',
            esc_attr($this->encodeDeletePayload($item))
        );
    }

    private function get_cron_jobs()
    {
        $cron_jobs = _get_cron_array();
        $jobs      = [];

        if (!is_array($cron_jobs)) {
            return $jobs;
        }

        foreach ($cron_jobs as $timestamp => $hooks) {
            foreach ($hooks as $hook => $events) {
                foreach ($events as $event) {
                    $args      = (isset($event['args']) && is_array($event['args'])) ? $event['args'] : [];
                    $isBuildIn = in_array($hook, $this->buildIn(), true);

                    $jobs[] = [
                        'timestamp'   => (int) $timestamp,
                        'hook'        => $hook,
                        'args'        => $args,
                        'next_run'    => wp_date('Y-m-d H:i:s', (int) $timestamp, wp_timezone()),
                        'schedule'    => isset($event['schedule']) ? $event['schedule'] : 'One-time',
                        'action'      => $this->resolveHookActions($hook),
                        'is_build_in' => $isBuildIn,
                        'build_in'    => $isBuildIn ? 'Yes' : 'No',
                    ];
                }
            }
        }

        return $jobs;
    }

    public function column_default($item, $column_name)
    {
        return match ($column_name) {
            'hook', 'next_run' => esc_html((string) ($item[$column_name] ?? '')),
            'action'           => $this->formatActionColumn($item),
            'build_in'         => $this->formatBuildInColumn($item),
            'schedule'         => $this->formatScheduleColumn($item),
            default            => '',
        };
    }

    private function formatScheduleColumn(array $item): string
    {
        $v = (string) ($item['schedule'] ?? '');
        return match ($v) {
            'hourly'     => __('Once Hourly'),
            'daily'      => __('Once Daily'),
            'twicedaily' => __('Twice Daily'),
            'weekly'     => __('Once Weekly'),
            default      => esc_html($v),
        };
    }

    private function formatBuildInColumn(array $item): string
    {
        if (!empty($item['is_build_in'])) {
            return '<span class="j-badge is-sm">Yes</span>';
        }

        return '<span class="j-badge is-sm is-danger">No</span>';
    }

    private function formatActionColumn(array $item): string
    {
        $actions = $item['action'] ?? [];

        if (!is_array($actions) || empty($actions)) {
            return '<span class="description">None</span>';
        }

        return implode('<br>', array_map(static function ($action) {
            return esc_html((string) $action);
        }, $actions));
    }

    private function resolveHookActions(string $hook): array
    {
        global $wp_filter;

        if (!isset($wp_filter[$hook]) || !($wp_filter[$hook] instanceof \WP_Hook)) {
            return [];
        }

        $actions = [];
        foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $name      = $this->formatCallable($callback['function'] ?? null);
                $actions[] = $name . ' @' . (string) $priority;
            }
        }

        return array_values(array_unique($actions));
    }

    private function formatCallable($callable): string
    {
        if (is_string($callable)) {
            return $callable;
        }

        if (is_array($callable) && isset($callable[0], $callable[1])) {
            $classOrObject = $callable[0];
            $method        = (string) $callable[1];
            $className     = is_object($classOrObject) ? get_class($classOrObject) : (string) $classOrObject;

            return $className . '::' . $method;
        }

        if ($callable instanceof Closure) {
            return 'Closure';
        }

        if (is_object($callable) && method_exists($callable, '__invoke')) {
            return get_class($callable) . '::__invoke';
        }

        return 'Unknown Callback';
    }

    private function encodeDeletePayload(array $item): string
    {
        $payload = [
            'timestamp' => (int) ($item['timestamp'] ?? 0),
            'hook'      => (string) ($item['hook'] ?? ''),
            'args'      => (isset($item['args']) && is_array($item['args'])) ? $item['args'] : [],
        ];

        return base64_encode(serialize($payload));
    }

    private function decodeDeletePayload(string $payload): ?array
    {
        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return null;
        }

        $data = @unserialize($decoded, ['allowed_classes' => false]);
        if (!is_array($data)) {
            return null;
        }

        $timestamp = isset($data['timestamp']) ? (int) $data['timestamp'] : 0;
        $hook      = isset($data['hook']) ? (string) $data['hook'] : '';
        $args      = (isset($data['args']) && is_array($data['args'])) ? $data['args'] : [];

        if ($timestamp <= 0 || $hook === '') {
            return null;
        }

        return [
            'timestamp' => $timestamp,
            'hook'      => $hook,
            'args'      => $args,
        ];
    }

    private function process_bulk_action(): void
    {
        if ($this->current_action() !== 'delete') {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('bulk-' . $this->_args['plural']);

        $selected = $_POST['cron_jobs'] ?? [];
        if (!is_array($selected)) {
            return;
        }

        $deleted = 0;
        foreach ($selected as $payload) {
            $item = $this->decodeDeletePayload((string) $payload);
            if (!$item) {
                continue;
            }

            if (in_array($item['hook'], $this->buildIn(), true)) {
                continue;
            }

            if (wp_unschedule_event($item['timestamp'], $item['hook'], $item['args'])) {
                $deleted++;
            }
        }

        if ($deleted > 0) {
            wp_admin_notice(sprintf(__('Deleted', 'G3'), $deleted), [
                'type'        => 'success',
                'dismissible' => true,
            ]);
        }
    }

    private function buildIn()
    {
        return [
            'wp_privacy_delete_old_export_files',
            'recovery_mode_clean_expired_keys',
            'wp_scheduled_delete',
            'delete_expired_transients',
            'wp_update_user_counts',
            'wp_scheduled_auto_draft_delete',
            'wp_site_health_scheduled_check',
            'wp_delete_temp_updater_backups'
        ];
    }

    public function display()
    {
        $this->prepare_items();
        parent::display();
    }
}
