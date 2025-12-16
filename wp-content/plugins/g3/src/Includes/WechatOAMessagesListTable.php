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
                return substr($item->nickname, 0, 20) . '...';
            case 'type':
                return ucfirst($item->type);
            case 'content':
                $content = $item->content;
                if (strlen($content) > 50) {
                    $content = substr($content, 0, 50) . '...';
                }
                return esc_html($content);
            case 'created':
                return date('Y-m-d H:i:s', $item->created);
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
        // 这里需要根据实际情况实现获取总数据量的逻辑
        // 暂时返回一个默认值
        // return 0;

        // 模拟数据
        return 100;
    }

    private function get_data($current_page, $perPage)
    {
        // 这里需要根据实际情况实现获取数据的逻辑
        // 暂时返回一个空数组
        // return [];

        // 模拟数据
        return [
            (object) [
                'id'       => '1',
                'openid'   => 'o6_bmjrPTlm6_2sgVt7hMZOPfL2M',
                'nickname' => 'John Doe',
                'type'     => 'text',
                'content'  => 'Hello, World!',
                'created'  => 1633072800,
            ],
            (object) [
                'id'       => '2',
                'openid'   => 'o6_bmjrPTlm6_2sgVt7hMZOPfL2M',
                'nickname' => 'Jane Smith',
                'type'     => 'text',
                'content'  => 'Hello, World!',
                'created'  => 1633272800,
            ]
        ];
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