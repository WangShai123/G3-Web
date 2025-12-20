<?php
namespace JEALER\G3\Includes;

use WP_List_Table;
use JEALER\G3\Services\WechatOAService;
use JEALER\G3\Utilities\Common;
use JEALER\G3\Utilities\Validator;

class WechatOAMessageListTable extends WP_List_Table {
    private $perPage = 20;

    public function __construct()
    {
        parent::__construct([
            'singular' => 'message',
            'plural'   => 'messages',
            'ajax'     => true
        ]);
    }

    public function get_columns()
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

    public function get_bulk_actions()
    {
        return [
            'delete' => __('Delete')
        ];
    }

    public function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="messages[]" value="%s" />',
            $item->id
        );
    }

    public function extra_tablenav($which): void
    {
        if ($which == "top") {
        }
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'openid':
                return Common::truncate($item->openid, 20);
            case 'nickname':
                return !empty($item->nickname) ? Common::truncate($item->nickname, 20) : '-';
            case 'type':
                return ucfirst($item->type);
            case 'content':
                $content = Common::truncate($item->content, 50);
                return esc_html($content);
            case 'created':
                return wp_date('Y-m-d H:i:s', strtotime($item->created));
            default:
                return isset($item->$column_name) ? $item->$column_name : '-';
        }
    }

    private function renderContent($content)
    {
        if (Validator::isImage($content)) {
            return '<img src="' . $content . '" alt="Image" width="160" height="80" />';
        }
        $content = Common::truncate($content, 50);
        return esc_html($content);
    }

    public function prepare_items()
    {
        // 处理批量操作
        $this->process_bulk_action();

        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [$columns, $hidden, $sortable];

        // 获取数据总数
        $total_items = $this->get_total_items();

        // 获取当前页码
        $current_page = $this->get_pagenum();

        // 设置分页参数
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $this->perPage,
            'total_pages' => ceil($total_items / $this->perPage)
        ]);

        // 获取当前页数据
        $this->items = $this->get_data($current_page, $this->perPage);
    }

    private function get_total_items()
    {
        return WechatOAService::getMessageCount();
    }

    private function get_data($current_page, $perPage)
    {
        // 计算偏移量
        $offset = ($current_page - 1) * $perPage;

        // 获取消息数据
        $messages = WechatOAService::getMessages($current_page, $perPage);

        // 将关联数组转换为对象数组，以匹配现有代码的使用方式
        $result = [];
        foreach ($messages as $message) {
            $result[] = (object) $message;
        }

        return $result;
    }

    public function no_items()
    {
        _e('No data found.', 'G3');
    }

    public function display()
    {
        $this->prepare_items();
        parent::display();
    }

    public function process_bulk_action()
    {
        // 处理删除操作（单个或多个）
        if ('delete' === $this->current_action()) {
            // 获取要删除的消息ID（单个或多个）
            $messages = [];

            // 检查 bulk actions 的参数
            if (isset($_REQUEST['messages']) && is_array($_REQUEST['messages'])) {
                $messages = $_REQUEST['messages'];
            } elseif (isset($_GET['messages'])) {
                // 单个消息删除
                $messages = [$_GET['messages']];
            }

            if (!empty($messages)) {
                // 验证nonce
                $nonce = wp_unslash($_REQUEST['_wpnonce'] ?? $_REQUEST['_wpnonce'] ?? '');
                if (!wp_verify_nonce($nonce, 'bulk-' . $this->_args['plural'])) {
                    wp_die('Security check failed');
                }

                $current_page = isset($_REQUEST['paged']) ? max(1, intval($_REQUEST['paged'])) : 1;

                // 删除消息
                $deleted = WechatOAService::deleteMessages($messages);

                if ($deleted !== false) {
                    // 设置成功消息
                    add_action('admin_notices', function () use ($deleted) {
                        echo '<div class="notice notice-success is-dismissible">';
                        echo '<p>Successfully deleted message(s).</p>';
                        echo '</div>';
                    });
                    $new_page = max(1, $current_page - 1);
                    //重定向当前页面，且$paged=$new_page
                    wp_redirect(add_query_arg('paged', $new_page, $_SERVER['REQUEST_URI']));
                    exit;
                } else {
                    // 设置错误消息
                    add_action('admin_notices', function () {
                        echo '<div class="notice notice-error is-dismissible">';
                        echo '<p>Failed to delete message(s)</p>';
                        echo '</div>';
                    });
                }
            }
        }
    }

    public function column_action($item)
    {
        $actions = [
            'view'   => sprintf(
                '<span id="view-message-%s" class="cursor-pointer color-link">' . __('View', 'G3') . '</span>',
                $item->id
            ),
            'delete' => sprintf(
                '<span id="delete-message-%s" class="cursor-pointer color-error">' . __('Delete') . '</span>',
                $item->id
            )
        ];

        return join(' | ', $actions);
    }
}