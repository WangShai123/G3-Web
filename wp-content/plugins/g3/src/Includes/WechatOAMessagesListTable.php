<?php
namespace JEALER\G3\Includes;

use WP_List_Table;
use JEALER\G3\Services\WechatOAService;

class WechatOAMessagesListTable extends WP_List_Table {
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
            'openid'   => __('OpenID', 'G3'),
            'nickname' => __('Nickname', 'G3'),
            'type'     => __('Type', 'G3'),
            'content'  => __('Content', 'G3'),
            'created'  => __('Created At', 'G3'),
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
                return substr($item->openid, 0, 20) . '...';
            case 'nickname':
                return !empty($item->nickname) ? substr($item->nickname, 0, 20) . '...' : '-';
            case 'type':
                return ucfirst($item->type);
            case 'content':
                $content = $item->content;
                if (strlen($content) > 50) {
                    $content = substr($content, 0, 50) . '...';
                }
                return esc_html($content);
            case 'created':
                return wp_date('Y-m-d H:i:s', strtotime($item->created));
            default:
                return isset($item->$column_name) ? $item->$column_name : '-';
        }
    }

    public function prepare_items()
    {
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
}