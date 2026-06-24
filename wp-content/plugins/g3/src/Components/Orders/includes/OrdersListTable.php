<?php
namespace JEALER\G3\Components\Orders\Includes;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Services\OrdersService;
use JEALER\G3\Utilities\Date;
use OrderService;
use WP_Error;
use WP_List_Table;

class OrdersListTable extends WP_List_Table {
    private string        $table;
    private int           $perPage;
    private               $wpdb;
    private OrdersService $service;
    public function __construct()
    {
        parent::__construct([
            'singular' => 'order',
            'plural'   => 'orders',
            'ajax'     => false
        ]);
        $this->init();
    }
    private function init()
    {
        global $wpdb;
        $this->wpdb    = $wpdb;
        $this->table   = $this->wpdb->prefix . OrdersService::TABLE;
        $this->service = Container::run()->use(OrdersService::class);
        $this->perPage = 20;
    }

    public function get_columns(): array
    {
        return [
            'cb'           => '<input type="checkbox" />',
            'order_code'   => __('Order Code', 'G3'),
            'created_at'   => __('Created'),
            'buyer_id'     => __('User'),
            'final_amount' => __('Final Amount', 'G3'),
            'paid_amount'  => __('Paid Amount', 'G3'),
            'order_source' => __('Source'),
            'order_type'   => __('Type'),
            'order_status' => __('Status'),
            'action'       => __('Action')
        ];
    }
    public function prepare_items()
    {
        $columns               = $this->get_columns();
        $hidden                = [];
        $sortable              = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $currentPage = $this->get_pagenum();
        $offset      = ($currentPage - 1) * $this->perPage;

        $search     = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $orderby    = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'created_at';
        $order      = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'DESC';
        $totalItems = $this->getCount($search);

        $perPage     = $this->perPage;
        $this->items = $this->getData(compact('search', 'perPage', 'offset', 'orderby', 'order'));
        $this->set_pagination_args([
            'total_items' => $totalItems,
            'per_page'    => $this->perPage,
        ]);
    }
    public function column_default($item, $column_name)
    {
        return match ($column_name) {
            'created_at'   => Date::dateTime(strtotime($item['created_at'])),
            'buyer_id'     => get_userdata($item['buyer_id']) ? get_userdata($item['buyer_id'])->display_name : __('Unknown'),
            'order_source' => OrdersService::renderSource($item['order_source']),
            'order_type'   => OrdersService::renderType($item['order_type']),
            'order_status' => OrdersService::renderStatus($item['order_status']),
            'action'       => $this->renderAction($item),
            default        => $item[$column_name] ?? ''
        };
    }
    public function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="order_ids[]" value="%s" />',
            $item['id']
        );
    }
    public function extra_tablenav($which): void
    {
        if ($which == "top") {
            echo '<div class="alignleft actions mb-2"><button type="button" id="export-orders" class="button" disabled>' . __('Export') . '</button></div>';
        }
    }
    public function get_bulk_actions(): array
    {
        return ['delete' => __('Delete')];
    }
    public function search_box($text, $input_id): void
    {
        ?>
        <p class="search-box">
            <label class="screen-reader-text" for="<?php echo esc_attr($input_id); ?>"><?php echo esc_html($text); ?>:</label>
            <input type="search" id="<?php echo esc_attr($input_id); ?>" name="s" value="<?php _admin_search_query(); ?>"
                placeholder="<?php echo esc_attr(__('Search by Order Code', 'G3')); ?>" />
            <?php submit_button($text, '', '', false, array('id' => 'search-submit')); ?>
        </p>
        <?php
    }
    public function display(): void
    {
        $this->prepare_items();

        echo '<div class="wrap">';
        echo '<h1 class="float-left">' . __('All Orders', 'G3') . '</h1>';
        echo '<form id="list-form" method="post">';
        $this->search_box(__('Search'), 'reply');
        parent::display();
        echo '</form></div>';

        $this->process_bulk_action();
    }
    public function get_sortable_columns(): array
    {
        return [
            'order_code'   => ['order_code', false],
            'created_at'   => ['created_at', true],
            'order_source' => ['order_source', false],
            'order_type'   => ['order_type', false],
            'order_status' => ['order_status', false],
        ];
    }
    public function process_bulk_action(): void
    {
        $action = $this->current_action();
        $ids    = isset($_REQUEST['order_ids']) ? (array) $_REQUEST['order_ids'] : [];

        if (!$action || empty($ids)) return;

        switch ($action) {
            case 'delete':
                $result = $this->deleteOrders($ids);
                break;
            default:
                break;
        }
        if (!is_wp_error($result)) {
            $msg = __('Deleted', 'G3');
            wp_add_inline_script('jui', 'jui.Toast.success("' . $msg . '");setTimeout(()=>{location.reload()},800)');
        }
    }
    private function deleteOrders(array $ids)
    {
        foreach ($ids as $id) {
            $id     = (int) $id;
            $result = $this->service->deleteOrderById($id);
            if ($result === false) {
                return new WP_Error(
                    500,
                    __('Failed to delete orders', 'G3')
                );
            }
        }
    }
    private function getData(array $params): array
    {
        extract($params);

        // set default orderby and order
        $orderby = isset($orderby) && !empty($orderby) ? $orderby : 'created_at';
        $order   = isset($order) && strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        // check orderby column is allowed
        $sortableColumns = array_keys($this->get_sortable_columns());
        if (!in_array($orderby, $sortableColumns)) {
            $orderby = 'created_at';
        }

        $where        = '1=1 AND order_status != 0';
        $placeholders = [];

        // search by order code
        if (!empty($search)) {
            $where          .= ' AND order_code LIKE %s';
            $placeholders[]  = '%' . $this->wpdb->esc_like($search) . '%';
        }

        // build query
        $sql            = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $placeholders[] = $perPage;
        $placeholders[] = $offset;

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $placeholders),
            ARRAY_A
        );

        return $results ?: [];
    }
    private function getCount(string $search = ''): int
    {
        $where        = '1=1 AND order_status != 0';
        $placeholders = [];

        // search by order code
        if (!empty($search)) {
            $where          .= ' AND order_code LIKE %s';
            $placeholders[]  = '%' . $this->wpdb->esc_like($search) . '%';
        }

        // build query SQL
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$where}";

        // execute query - 根据是否有占位符参数决定是否使用 prepare
        if (!empty($placeholders)) {
            $count = $this->wpdb->get_var(
                $this->wpdb->prepare($sql, $placeholders)
            );
        } else {
            $count = $this->wpdb->get_var($sql);
        }

        return (int) $count;
    }
    private function renderAction($item): string
    {
        $action = $this->_actions($item);

        return sprintf(
            '<span class="view-order color-link cursor-pointer" data-id="%s">%s</span> %s',
            // $item['order_code'],
            $item['id'],
            __('View'),
            $action
        );
    }
    private function _actions($item)
    {
        // $id = $item['order_code'];
        $id = $item['id'];
        // @todo get delivery code
        $deliveryId = $item['delivery_id'];
        $code       = '11231';
        return match ($item['order_status']) {
            '1'     => '<span class="close-order cursor-pointer" data-id="' . $id . '">' . __('Close') . '</span>',
            '2'     => '<span class="ship-order color-purple cursor-pointer" data-id="' . $id . '">' . __('Deliver', 'G3') . '</span>',
            '3'     => '<a class="track-order color-success cursor-pointer" href="' . $this->oneTrack($code) . '" target="_blank">' . __('Track', 'G3') . '</a>',
            default => '<span class="delete-order color-error cursor-pointer" data-id="' . $id . '">' . __('Delete') . '</span>',
        };
    }
    private function ifInChina()
    {
        return strpos(get_locale(), 'zh_') !== false;
    }
    private function trackLink($code)
    {
        if (!$this->ifInChina()) {
            return "https://m.kuaidi100.com/result.jsp?nu={$code}";
        } else {
            return "https://www.17track.net/en/track?nums={$code}";
        }
    }
    private function oneTrack($code)
    {
        return "https://www.17track.net/en/track?nums={$code}";
    }
}
