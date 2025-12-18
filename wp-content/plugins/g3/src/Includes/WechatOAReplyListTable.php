<?php
namespace JEALER\G3\Includes;

use WP_List_Table;
use JEALER\G3\Services\WechatOAService;
use JEALER\G3\Utilities\Common;

class WechatOAReplyListTable extends WP_List_Table {
    private array $columns;
    private int $perPage = 15;

    public function __construct($args = [])
    {
        parent::__construct([
            'singular' => 'swiper',
            'plural'   => 'swipers',
            'ajax'     => true
        ]);
    }
    public function get_columns(): array
    {
        return [
            'cb'       => '<input type="checkbox" />',
            'content'  => __('Content'),
            'keywords' => __('Keywords'),
            'type'     => __('Type'),
            'status'   => __('Status'),
            'modified' => __('Modified'),
            'action'   => __('Action'),
        ];
    }
    public function prepare_items(): void
    {
        $currentPage = $this->get_pagenum();
        $offset      = ($currentPage - 1) * $this->perPage;
        $search      = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $totalItems  = WechatOAService::replyListCount();
        // $totalItems = $this->getTotalReplies($search);

        $this->columns         = $this->get_columns();
        $hidden                = [];
        $sortable              = $this->get_sortable_columns();
        $this->_column_headers = [$this->columns, $hidden, $sortable];
        // $this->items           = $this->getData($this->perPage, $currentPage);
        $perPage     = $this->perPage;
        $this->items = $this->getData(compact('search', 'perPage', 'offset'));
        $this->set_pagination_args([
            'total_items' => $totalItems,
            'per_page'    => $this->perPage,
            // 'total_pages' => ceil($totalItems / $this->perPage)
        ]);
    }
    private function getTotalReplies($search = '')
    {
        global $wpdb;
        $reply_table = $wpdb->prefix . 'g3_wechat_oa_reply';
        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            return $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$reply_table} 
                 WHERE content LIKE %s",
                $like
            ));
        }
        return $wpdb->get_var("SELECT COUNT(*) FROM {$reply_table}");
    }
    public function display(): void
    {
        $this->prepare_items();
        parent::display();
    }

    private function getData($args): array
    {
        // 测试数据
        return [
            [
                'id'       => 1,
                'keywords' => 'keyword1',
                'content'  => 'content1',
                'type'     => 'type1',
                'status'   => 'status1',
                'modified' => 'modified1',
                'action'   => 'action1',
            ],
            [
                'id'       => 2,
                'keywords' => 'keyword2',
                'content'  => 'content2',
                'type'     => 'type2',
                'status'   => 'status2',
                'modified' => 'modified2',
                'action'   => 'action2',
            ],
        ];
    }
    // private function getData(int $perPage, int $currentPage): array
    // {
    //     // 测试数据
    //     return [
    //         [
    //             'id'       => 1,
    //             'keywords' => 'keyword1',
    //             'content'  => 'content1',
    //             'type'     => 'type1',
    //             'status'   => 'status1',
    //             'modified' => 'modified1',
    //             'action'   => 'action1',
    //         ],
    //         [
    //             'id'       => 2,
    //             'keywords' => 'keyword2',
    //             'content'  => 'content2',
    //             'type'     => 'type2',
    //             'status'   => 'status2',
    //             'modified' => 'modified2',
    //             'action'   => 'action2',
    //         ],
    //     ];
    // }
    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'content':
                return Common::truncate($item['content'], 80);
            case 'keywords':
                return $item['keywords'] ?: '<i>无关键词</i>';
            case 'status':
                return $item['status'] ? '启用' : '禁用';
            case 'action':
                return $this->renderAction($item);
            default:
                return $item[$column_name] ?? '';
        }
    }
    public function column_cb($item)
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

    private function renderAction($item)
    {
        return '<span id="edit-reply" data-id="' . $item['id'] . '">' . __('Edit') . '</span>
        |
        <span id="delete-reply" data-id="' . $item['id'] . '">' . __('Delete') . '</span>';
    }
}