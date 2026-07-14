<?php
namespace JEALER\G3\Components\Form\Includes;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Services\FormService;
use JEALER\G3\Utilities\Common;
use WP_List_Table;
use wpdb;

class FormListTable extends WP_List_Table {
    private array       $columns;
    private int         $perPage;
    private wpdb        $wpdb;
    private string      $table;
    private Container   $container;
    private FormService $service;
    public function __construct($args = [])
    {
        parent::__construct([
            'singular' => 'form',
            'plural'   => 'form',
            'ajax'     => true
        ]);
        global $wpdb;
        $this->wpdb      = $wpdb;
        $this->table     = $wpdb->prefix . FormService::TABLE;
        $this->container = Container::run();
        $this->service   = $this->container->get(FormService::class);
        $option          = get_option(FormService::FORM_OPTION_KEY, []);
        $this->perPage   = (int) (is_array($option) ? ($option['perPage'] ?? 20) : 20);
    }
    public function get_columns(): array
    {
        return [
            'cb'         => '<input type="checkbox" />',
            'title'      => __('Title'),
            'content'    => __('Content'),
            'ext'        => __('Extra Data', 'G3'),
            'email'      => __('Email'),
            'ip'         => 'IP',
            'status'     => __('Status', 'G3'),
            'created_at' => __('Created At', 'G3'),
            'action'     => __('Action'),
        ];
    }
    public function prepare_items(): void
    {
        $currentPage = $this->get_pagenum();
        $totalItems  = $this->getCount();

        $this->columns         = $this->get_columns();
        $hidden                = [];
        $sortable              = $this->get_sortable_columns();
        $this->_column_headers = array($this->columns, $hidden, $sortable);
        $this->set_pagination_args([
            'total_items' => $totalItems,
            'per_page'    => $this->perPage,
            'total_pages' => ceil($totalItems / $this->perPage)
        ]);
        $this->items = $this->getData($this->perPage, $currentPage);
    }
    public function column_default($item, $column_name)
    {
        return match ($column_name) {
            'content' => Common::truncateHtml($item->{$column_name}, 50),
            'ext'     => $this->renderExt($item, $column_name),
            'status'  => $this->renderStatus($item),
            'action'  => $this->renderAction($item),
            default   => $item->{$column_name} ?? '-',
        };
    }
    public function get_sortable_columns(): array
    {
        return [
            'status'     => ['status', false],
            'created_at' => ['created_at', false],
        ];
    }
    private function getData($per_page, $current_page): array|object|null
    {
        $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'id';
        $order   = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'desc';

        $queryKey = md5($per_page . '_' . $current_page . '_' . $orderby . '_' . $order);
        $result   = wp_cache_get($queryKey, FormService::QUERY_CACHE_GROUP);
        if (false !== $result) {
            return $result;
        }

        $offset = ($current_page - 1) * $per_page;

        $allowed_orderby = ['id', 'status', 'created_at'];
        $allowed_order   = ['asc', 'desc'];

        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'id';
        }

        if (!in_array(strtolower($order), $allowed_order)) {
            $order = 'desc';
        }

        $sql = $this->wpdb->prepare(
            "SELECT * FROM `{$this->table}` ORDER BY `$orderby` $order LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );

        $result = $this->wpdb->get_results($sql);

        if (!$result) {
            return [];
        }

        wp_cache_set($queryKey, $result, FormService::QUERY_CACHE_GROUP, ADMIN_CACHE_TTL);
        return $result;
    }
    private function getCount()
    {
        $sql = "SELECT COUNT(*) FROM `{$this->table}`";
        return $this->wpdb->get_var($sql);
    }
    public function get_bulk_actions(): array
    {
        return [
            'delete' => __('Delete'),
        ];
    }
    public function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="form[]" value="%s" />',
            $item->{"id"}
        );
    }
    public function column_title($item): string
    {
        $actions = [
            'view' => sprintf('<span data-content="%s" class="view-content color-link cursor-pointer">%s</span>', $item->{"content"}, __('View')),
        ];

        return sprintf('%1$s %2$s', $item->{"title"}, $this->row_actions($actions));
    }
    public function process_bulk_actions()
    {
        $action = $this->current_action();
        if ($action !== 'delete') return;

        $ids    = $_REQUEST['form'];
        $result = $this->service->delete($ids);
        if ($result) {
            $msg = __('Deleted', 'G3');
            wp_add_inline_script('jui', 'jui.Toast.success("' . $msg . '",1000);setTimeout(()=>{location.reload()},800)');
        }
    }
    public function display(): void
    {
        $this->prepare_items();
        echo '<div class="wrap"><h1 class="wp-heading-inline">
' . __('Form', 'G3') . '</h1><hr class="wp-header-end"><form id="form-filter" method="post">';
        parent::display();
        echo '</form></div>';
        $this->process_bulk_actions();
    }
    private function renderExt($item, $column_name): string
    {
        $ext = maybe_unserialize($item->{$column_name});
        if (is_array($ext)) {
            $output = '';
            foreach ($ext as $key => $value) {
                $output .= sprintf('<strong>%s:</strong> %s<br>', esc_html($key), esc_html($value));
            }
            return $output;
        }
        return $item->{$column_name} ?? '-';
    }
    private function renderStatus($item): string
    {
        return match ($item->status) {
            '0'     => '<span class="color-error">' . __('Pending', 'G3') . '</span>',
            '1'     => '<span>' . __('Processed', 'G3') . '</span>',
            default => '<span>' . __('Unknown', 'G3') . '</span>',
        };
    }
    private function renderAction($item): string
    {
        return '<span data-id="' . $item->id . '" data-status="' . $item->status . '" class="change-field-status color-link cursor-pointer">' . __('Change') . '</span> <span data-id="' . $item->id . '" class="delete-field color-error cursor-pointer">' . __('Delete') . '</span>';
    }
}
