<?php
namespace JEALER\G3\Components\Auth\Includes;
use JEALER\G3\Container\Container;
use JEALER\G3\Utilities\Date;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Option;
use WP_List_Table;
use JEALER\G3\Services\AuthService;

class InvitationCodeListTable extends WP_List_Table {
    private string $table;
    private int $perPage;
    private $wpdb;
    private AuthService $service;
    public function __construct()
    {
        parent::__construct([
            'singular' => 'code',
            'plural'   => 'codes',
            'ajax'     => false
        ]);
        $this->init();
    }
    private function init()
    {
        global $wpdb;
        $this->wpdb    = $wpdb;
        $this->table   = $wpdb->prefix . AuthService::InvitationCodeTable;
        $this->service = Container::run()->use(AuthService::class);
        $this->perPage = 20;
    }
    public function get_columns(): array
    {
        return [
            'cb'         => '<input type="checkbox" />',
            'code'       => __('Invitation Code', 'G3'),
            'creator_id' => __('Creator', 'G3'),
            'created_at' => __('Created At', 'G3'),
            'source'     => __('Source'),
            'end_time'   => __('Expiration'),
            'status'     => __('Status'),
            'invitee_id' => __('Invitee', 'G3'),
            'used_at'    => __('Used At', 'G3'),
            'action'     => __('Action'),
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
            'code' => $item['status'] === '0' ? $item[$column_name] : '<del class="color-gray">' . $item[$column_name] . '</del>',
            'creator_id' => get_userdata($item['creator_id']) ? get_userdata($item['creator_id'])->display_name : __('Unknown'),
            'source' => AuthService::renderCodeSource($item['source']),
            'created_at' => Date::dateTime(strtotime($item['created_at'])),
            'end_time' => $this->renderEndTime($item),
            'status' => $item['status'] === '0' ? __('Unused', 'G3') : __('Used', 'G3'),
            'action' => $this->renderAction($item),
            default => $item[$column_name] ?? '-'
        };
    }
    public function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="ids[]" value="%s" />',
            $item['id']
        );
    }
    public function extra_tablenav($which): void
    {
        if ($which == "top") {
            echo '<div class="alignleft actions mb-2"><button type="button" class="button button-primary generate-code">' . __('Generate Invitation Code', 'G3') . '</button></div>';
        }
    }
    public function get_bulk_actions(): array
    {
        return [
            'delete' => __('Delete')
        ];
    }
    public function search_box($text, $input_id): void
    {
        ?>
        <p class="search-box">
            <label class="screen-reader-text" for="<?php echo esc_attr($input_id); ?>"><?php echo esc_html($text); ?>:</label>
            <input type="search" id="<?php echo esc_attr($input_id); ?>" name="s" value="<?php _admin_search_query(); ?>"
                placeholder="<?php echo esc_attr(__('Search by Invitation Code', 'G3')); ?>" />
            <?php submit_button($text, '', '', false, array('id' => 'search-submit')); ?>
        </p>
        <?php
    }
    public function display(): void
    {
        if (!$this->revitationValid()) {
            echo Element::tip(
                __('Invitation code feature is not available. Please set the invitation code as the registration code first.', 'G3'),
                '',
                'danger',
                'mt-4'
            );
            return;
        }

        $this->prepare_items();

        echo '<div class="wrap">
        <h3 class="float-left">' . __('All Invitation Codes', 'G3') . '</h3>
        <form id="list-form" method="post">';
        $this->search_box(__('Search'), 'reply');
        parent::display();
        echo '</form></div>';

        $this->process_bulk_action();
    }
    private function revitationValid()
    {
        $v = Option::get(AuthService::OPTION_KEY)['code'] ?? '';
        return $v === '1';
    }
    public function get_sortable_columns(): array
    {
        return [
            'created_at' => ['created_at', true],
            'status'     => ['status', false],
            'used_at'    => ['used_at', false],
            'end_time'   => ['end_time', false],
        ];
    }
    public function process_bulk_action(): void
    {
        $action = $this->current_action();
        $ids    = isset($_REQUEST['ids']) ? (array) $_REQUEST['ids'] : [];

        if (!$action || empty($ids)) return;

        $successCount = 0;
        $failCount    = 0;
        switch ($action) {
            case 'delete':
                foreach ($ids as $id) {
                    $id = intval($id);
                    if ($id <= 0) {
                        $failCount++;
                        continue;
                    }
                    $result = $this->service->deleteInviteCode($id);
                    if ($result !== false) {
                        $successCount++;
                    } else {
                        $failCount++;
                    }
                }
                break;
            default:
                break;
        }

        if ($successCount > 0) {
            $msg = __('Deleted', 'G3') . ': ' . $successCount;
            wp_add_inline_script('jui', 'jui.toast.success("' . $msg . '");setTimeout(()=>{location.reload()},800)');
        }
    }
    private function getData(array $params): array
    {
        extract($params);

        $orderby = isset($orderby) && !empty($orderby) ? $orderby : 'id';
        $order   = isset($order) && strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $sortableColumns = array_keys($this->get_sortable_columns());
        if (!in_array($orderby, $sortableColumns)) {
            $orderby = 'id';
        }

        $where        = '1=1';
        $placeholders = [];

        if (!empty($search)) {
            $where          .= ' AND code LIKE %s';
            $placeholders[]  = '%' . $this->wpdb->esc_like($search) . '%';
        }

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
        $where        = '1=1';
        $placeholders = [];

        if (!empty($search)) {
            $where          .= ' AND code LIKE %s';
            $placeholders[]  = '%' . $this->wpdb->esc_like($search) . '%';
        }

        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$where}";

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

        return sprintf(
            '<span class="copy-code color-link cursor-pointer" data-code="%s">%s</span> <span class="delete-code color-error cursor-pointer" data-id="%s">%s</span>',
            $item['code'],
            __('Copy'),
            $item['id'],
            __('Delete')
        );
    }
    private function renderEndTime($item): string
    {
        if (empty($item['end_time'])) {
            return '-';
        }
        $endTimeTimestamp = strtotime($item['end_time']);
        if ($endTimeTimestamp === false) {
            return esc_html($item['end_time']);
        }
        if ($endTimeTimestamp < time()) {
            return __('Expired', 'G3');
        }
        return Date::dateTime(strtotime($item['end_time']));
    }
}
