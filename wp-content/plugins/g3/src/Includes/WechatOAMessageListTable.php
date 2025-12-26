<?php
namespace JEALER\G3\Includes;

use WP_List_Table;
use JEALER\G3\Services\WechatOAService;
use JEALER\G3\Utilities\Common;
use JEALER\G3\Utilities\Validator;

class WechatOAMessageListTable extends WP_List_Table {
    private int $perPage;
    private string $table;

    public function __construct()
    {
        parent::__construct([
            'singular' => 'message',
            'plural'   => 'messages',
            'ajax'     => true
        ]);
        $this->init();
    }

    private function init(): void
    {
        global $wpdb;
        $this->table   = $wpdb->prefix . WechatOAService::MESSAGES_TABLE;
        $this->perPage = 20;
    }

    public function get_columns(): array
    {
        return [
            'cb'       => '<input type="checkbox" />',
            'openid'   => 'OpenID',
            'nickname' => __('Nickname'),
            'type'     => __('Type'),
            'content'  => __('Content'),
            'created'  => __('Time'),
            'action'   => __('Action')
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

    public function column_default($item, $column_name): mixed
    {
        return match ($column_name) {
            'openid' => Common::truncate($item->openid, 20),
            'nickname' => !empty($item->nickname) ? Common::truncate($item->nickname, 20) : '-',
            'type' => $item->type,
            // 'content' => $this->renderContent($item->content),
            'content' => Common::truncateHtml($item->content, 50),
            'created' => wp_date('Y-m-d H:i:s', strtotime($item->created)),
            default => isset($item->$column_name) ? $item->$column_name : '-',
        };
    }

    public function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="messages[]" value="%s" />',
            $item->id
        );
    }

    public function extra_tablenav($which): void
    {
        if ($which == "top") {
            echo '<button type="button" class="button button-error" id="flush-wechat-oa-messages">'
                . __('Delete old messages', 'G3')
                . '</button>';
        }
    }

    public function get_bulk_actions(): array
    {
        return [
            'delete' => __('Delete')
        ];
    }

    public function process_bulk_action(): void
    {
        if ('delete' === $this->current_action()) {
            $messages = [];

            // Check if the request contains message IDs
            if (isset($_REQUEST['messages']) && is_array($_REQUEST['messages'])) {
                $messages = $_REQUEST['messages'];
            } elseif (isset($_GET['messages'])) {
                $messages = [$_GET['messages']];
            }

            if (!empty($messages)) {
                // validate nonce
                $nonce = wp_unslash($_REQUEST['_wpnonce'] ?? $_REQUEST['_wpnonce'] ?? '');
                if (!wp_verify_nonce($nonce, 'bulk-' . $this->_args['plural'])) {
                    wp_die('Security check failed');
                }
                $deleted = WechatOAService::deleteMessages($messages);
                if ($deleted !== false) {
                    echo '<script>jQuery(document).ready(function () {
                        JUI.Toast.success("' . __('Deleted', 'G3') . '");
                        setTimeout(function() {
                            window.location.href="' . admin_url('admin.php?page=wechat-oa&tab=message') . '";
                        }, 1000);
                    })</script>';
                }
            }
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

        echo '<h3 class="float-left">' . __('Messages', 'G3') . '</h3>';
        echo '<form id="list-form" method="post">';
        $this->search_box(__('Search'), 'message');
        parent::display();
        echo '</form>';

        $this->process_bulk_action();
    }

    public function no_items(): void
    {
        _e('No data found.', 'G3');
    }

    public function column_action($item): string
    {
        $actions = [
            'view'   => sprintf(
                '<span data-id="%s" class="view-message cursor-pointer color-link">' . __('View') . '</span>',
                $item->id
            ),
            'delete' => sprintf(
                '<span data-id="%s" class="delete-message cursor-pointer color-error">' . __('Delete') . '</span>',
                $item->id
            )
        ];

        return join(' | ', $actions);
    }


    private function renderContent($content): string
    {
        if (Validator::isImage($content)) {
            return '<img src="' . $content . '" alt="Image" width="160" height="80" />';
        }
        $content = Common::truncateHtml($content, 50);
        return $content;
    }

    private function getData($args): array
    {
        global $wpdb;

        $search  = $args['search'] ?? '';
        $perPage = $args['perPage'] ?? $this->perPage;
        $offset  = $args['offset'] ?? 0;

        if ($search) {
            $sql    = "SELECT * FROM $this->table WHERE content LIKE %s OR nickname LIKE %s ORDER BY id DESC LIMIT %d OFFSET %d";
            $like   = '%' . $wpdb->esc_like($search) . '%';
            $result = $wpdb->get_results($wpdb->prepare($sql, $like, $like, $perPage, $offset));
        } else {
            $sql    = "SELECT * FROM $this->table ORDER BY id DESC LIMIT %d OFFSET %d";
            $result = $wpdb->get_results($wpdb->prepare($sql, $perPage, $offset));
        }
        return $result;
    }

    private function getCount($search = ''): int
    {
        global $wpdb;
        if ($search) {
            $sql   = "SELECT COUNT(*) FROM $this->table WHERE content LIKE %s OR nickname LIKE %s";
            $like  = '%' . $wpdb->esc_like($search) . '%';
            $query = $wpdb->get_var($wpdb->prepare($sql, $like, $like));
        } else {
            $query = $wpdb->get_var("SELECT COUNT(*) FROM $this->table");
        }
        return (int) $query;
    }
}