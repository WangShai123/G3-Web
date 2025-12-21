<?php
namespace JEALER\G3\Includes;

use WP_List_Table;
use JEALER\G3\Services\WechatOAService;
use JEALER\G3\Utilities\Common;

class WechatOAReplyListTable extends WP_List_Table {
    private int $perPage;
    private string $replyTable;
    private string $keywordTable;

    public function __construct($args = [])
    {
        parent::__construct([
            'singular' => 'swiper',
            'plural'   => 'swipers',
            'ajax'     => true
        ]);
        $this->init();
    }

    public function get_columns(): array
    {
        return [
            'cb'       => '<input type="checkbox" />',
            'keywords' => __('Keywords'),
            'content'  => __('Content'),
            'type'     => __('Type'),
            'status'   => __('Status'),
            'modified' => __('Last Modified'),
            'action'   => __('Action'),
        ];
    }

    public function prepare_items(): void
    {
        $columns               = $this->get_columns();
        $hidden                = [];
        $sortable              = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $currentPage = $this->get_pagenum();
        $offset      = ($currentPage - 1) * $this->perPage;

        $search     = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $totalItems = $this->getCount($search);

        $perPage     = $this->perPage;
        $this->items = $this->getData(compact('search', 'perPage', 'offset'));
        $this->set_pagination_args([
            'total_items' => $totalItems,
            'per_page'    => $this->perPage,
        ]);
    }

    public function column_default($item, $column_name)
    {
        return match ($column_name) {
            'content' => Common::truncateHtml($item['content'], 80),
            'keywords' => $item['keywords'] ?: '-',
            'status' => $item['status'],
            'action' => $this->renderAction($item),
            default => $item[$column_name] ?? '',
        };
    }

    public function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="reply_ids[]" value="%s" />',
            $item['id']
        );
    }

    public function extra_tablenav($which): void
    {
        if ($which == "top") {
            echo '<div class="alignleft actions"><button type="button" id="add-reply" class="button button-primary">' . __('Add New', 'G3') . '</button></div>';
        }
    }

    public function get_bulk_actions(): array
    {
        return [
            'delete'  => __('Delete'),
            'enable'  => __('Enable'),
            'disable' => __('Disable'),
        ];
    }

    public function process_bulk_action(): void
    {
        // 防止非 POST 请求或无操作
        if (!isset($_POST['action']) && !isset($_POST['action2'])) {
            return;
        }

        $action = $this->current_action();
        if (!$action || $action === '-1') {
            return;
        }

        // 获取选中的 reply_ids
        $replyIds = $_POST['reply_ids'] ?? [];
        if (empty($replyIds) || !is_array($replyIds)) {
            $this->add_admin_notice(__('No items selected.', 'G3'), 'error');
            return;
        }

        // 确保是整数数组
        $replyIds = array_map('intval', array_filter($replyIds, fn($id) => $id > 0));
        if (empty($replyIds)) {
            $this->add_admin_notice(__('Invalid IDs selected.', 'G3'), 'error');
            return;
        }

        // 执行对应操作
        switch ($action) {
            case 'enable':
                $result = WechatOAService::enableReply(['ids' => $replyIds]);
                if (is_wp_error($result)) {
                    $this->errorNotice($result->get_error_message());
                } else {
                    $this->successNotice(__('Updated'));
                }
                break;

            case 'disable':
                $result = WechatOAService::disableReply(['ids' => $replyIds]);
                if (is_wp_error($result)) {
                    $this->errorNotice($result->get_error_message());
                } else {
                    $this->successNotice(__('Updated'));
                }
                break;

            case 'delete':
                $result = WechatOAService::deleteReply(['ids' => $replyIds]);
                if (is_wp_error($result)) {
                    $this->errorNotice($result->get_error_message());
                } else {
                    $this->successNotice(__('Deleted', 'G3'));
                }
                break;

            default:
                break;
        }

    }

    public function search_box($text, $input_id): void
    {
        if (empty($_REQUEST['s']) && !$this->has_items()) {
            return;
        }

        $input_id = $input_id . '-search-input';

        if (!empty($_REQUEST['orderby'])) {
            echo '<input type="hidden" name="orderby" value="' . esc_attr($_REQUEST['orderby']) . '" />';
        }
        if (!empty($_REQUEST['order'])) {
            echo '<input type="hidden" name="order" value="' . esc_attr($_REQUEST['order']) . '" />';
        }
        if (!empty($_REQUEST['post_mime_type'])) {
            echo '<input type="hidden" name="post_mime_type" value="' . esc_attr($_REQUEST['post_mime_type']) . '" />';
        }
        if (!empty($_REQUEST['detached'])) {
            echo '<input type="hidden" name="detached" value="' . esc_attr($_REQUEST['detached']) . '" />';
        }
        ?>
        <p class="search-box">
            <label class="screen-reader-text" for="<?php echo esc_attr($input_id); ?>"><?php echo esc_html($text); ?>:</label>
            <input type="search" id="<?php echo esc_attr($input_id); ?>" name="s" value="<?php _admin_search_query(); ?>" />
            <?php submit_button($text, '', '', false, array('id' => 'search-submit')); ?>
        </p>
        <?php
    }

    public function display(): void
    {
        $this->prepare_items();

        echo '<h3 class="float-left">' . __('Custom Reply', 'G3') . '</h3>';
        echo '<form id="list-form" method="post">';
        $this->search_box(__('Search'), 'reply');
        parent::display();
        echo '</form>';

        $this->process_bulk_action();
    }

    private function init(): void
    {
        global $wpdb;
        $this->replyTable   = $wpdb->prefix . WechatOAService::REPLY_TABLE;
        $this->keywordTable = $wpdb->prefix . WechatOAService::KEYWORD_TABLE;
        $this->perPage      = 20;
    }

    private function renderAction($item): string
    {
        $encodedContent  = json_encode($item['content'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $encodedKeywords = json_encode($item['keywords'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return sprintf(
            '<span class="edit-reply color-link cursor-pointer" 
              data-id="%d" 
              data-keywords=\'%s\' 
              data-content=\'%s\' 
              data-status="%d">%s</span>
        |
        <span class="delete-reply color-error cursor-pointer" data-id="%d">%s</span>',
            (int) $item['id'],
            esc_attr($encodedKeywords),
            esc_attr($encodedContent),
            (int) ($item['status_raw'] ?? 1),
            esc_html__('Edit'),
            (int) $item['id'],
            esc_html__('Delete')
        );
    }

    private function getData($args): array
    {
        global $wpdb;

        $search  = $args['search'] ?? '';
        $perPage = $args['perPage'] ?? $this->perPage;
        $offset  = $args['offset'] ?? 0;

        // Build the query
        if ($search) {
            $like  = '%' . $wpdb->esc_like($search) . '%';
            $query = $wpdb->prepare(
                "SELECT r.*, GROUP_CONCAT(k.keyword SEPARATOR ', ') as keywords 
                 FROM {$this->replyTable} r
                 LEFT JOIN {$this->keywordTable} k ON r.id = k.reply_id
                 WHERE r.content LIKE %s OR k.keyword LIKE %s
                 GROUP BY r.id
                 ORDER BY r.updated DESC
                 LIMIT %d OFFSET %d",
                $like,
                $like,
                $perPage,
                $offset
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT r.*, GROUP_CONCAT(k.keyword SEPARATOR ', ') as keywords 
                 FROM {$this->replyTable} r
                 LEFT JOIN {$this->keywordTable} k ON r.id = k.reply_id
                 GROUP BY r.id
                 ORDER BY r.updated DESC
                 LIMIT %d OFFSET %d",
                $perPage,
                $offset
            );
        }

        $results = $wpdb->get_results($query, ARRAY_A);

        // Handle data formatting
        foreach ($results as &$row) {
            $row['type'] = match ($row['type']) {
                'text' => __('Text'),
                'news' => __('News'),
                default => __(ucfirst($row['type'])),
            };

            $row['status']   = $row['status'] ? __('Enabled') : __('Disabled');
            $row['modified'] = Common::dateTime(strtotime($row['updated']));
        }

        return $results ?: [];
    }

    private function getCount($search = ''): int
    {
        global $wpdb;

        if ($search) {
            $like  = '%' . $wpdb->esc_like($search) . '%';
            $query = $wpdb->prepare(
                "SELECT COUNT(DISTINCT r.id) 
                 FROM {$this->replyTable} r
                 LEFT JOIN {$this->keywordTable} k ON r.id = k.reply_id
                 WHERE r.content LIKE %s OR k.keyword LIKE %s",
                $like,
                $like
            );
        } else {
            $query = "SELECT COUNT(*) FROM {$this->replyTable}";
        }

        return (int) $wpdb->get_var($query);
    }

    private function add_admin_notice(string $message, string $type = 'updated'): void
    {
        add_action('admin_notices', function () use ($message, $type) {
            printf('<div class="%s"><p>%s</p></div>', esc_attr($type), esc_html($message));
        });
    }

    private function errorNotice(string $message): void
    {
        echo <<<HTML
<script>
jQuery(document).ready(function () {
    JUI.Toast.error('$message');
    setTimeout(function () {
        window.location.reload();
    }, 1000);
})
</script>
HTML;
    }
    private function successNotice(string $message): void
    {
        echo <<<HTML
<script>
jQuery(document).ready(function () {
    JUI.Toast.success('$message');
    setTimeout(function () {
        window.location.reload();
    }, 1000);
})
</script>
HTML;
    }
}
