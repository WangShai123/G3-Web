<?php
namespace JEALER\G3\Includes;
use WP_List_Table;
use JEALER\G3\Services\SwiperService;

class SwiperListTable extends WP_List_Table {
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
            // 'id' => 'ID',
            'title'    => __('Title', 'G3'),
            'media'    => __('Media', 'G3'),
            'link'     => __('Link', 'G3'),
            'target'   => __('Open Method', 'G3'),
            'location' => __('Location', 'G3'),
            'sort'     => __('Sort', 'G3'),
            'status'   => __('Status', 'G3'),
            'updated'  => __('Update Time', 'G3')
        ];
    }

    public function prepare_items(): void
    {
        $currentPage = $this->get_pagenum();
        $totalItems  = SwiperService::count();

        $this->columns         = $this->get_columns();
        $hidden                = [];
        $sortable              = $this->get_sortable_columns();
        $this->_column_headers = array($this->columns, $hidden, $sortable);
        $this->set_pagination_args([
            'total_items' => $totalItems,
            'per_page'    => $this->perPage,
            'total_pages' => ceil($totalItems / $this->perPage)
        ]);
        $this->items = $this->getCurrentData($this->perPage, $currentPage);
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'status':
                return $item->{$column_name} == 1 ? __('Online', 'G3') : __('Offline', 'G3');
            case 'media':
                return '<img class="object-cover cursor-pointer swiperPreview" style="width:100px;height:60px" src="' . $item->{$column_name} . '" draggable="false"></img>';
            case 'target':
                return $item->{$column_name} == 0 ? __('Current Tab', 'G3') : __('New Tab', 'G3');
            case 'link':
                $link = $item->{$column_name};
                $text = mb_substr($link, 0, 50) > 50 ? mb_substr($link, 0, 50) . '...' : mb_substr($link, 0, 50);
                return $link == '' ? '-' : '<a href="' . $link . '" target="_blank">' . $text . '</a>';
            case 'location':
                $option = maybe_unserialize(get_option(SwiperService::LOCATION_OPTION_KEY));
                if ($option) {
                    $location     = explode(',', $item->{$column_name});
                    $location_str = '';
                    foreach ($location as $key => $value) {
                        foreach ($option as $k => $v) {
                            if ($k == $value) {
                                $location_str .= $v . ',';
                            }
                        }
                    }
                    return rtrim($location_str, ',');
                }
                return '-';
            case 'updated':
                if (strtotime($item->{$column_name}) == false) {
                    return '-';
                }
                return wp_date('Y-m-d H:i:s', strtotime($item->{$column_name}));
            default:
                return isset($item->{$column_name}) ? $item->{$column_name} : '-';
        }
    }

    private function getCurrentData($per_page, $current_page): array|object|null
    {
        global $wpdb;

        $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'id';
        $order   = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'desc';

        $offset = ($current_page - 1) * $per_page;

        $table = $wpdb->prefix . SwiperService::TABLE;

        // fix SQL syntax error, correctly escape column names and sorting direction
        $allowed_orderby = ['id', 'title', 'link', 'target', 'location', 'sort', 'status', 'updated'];
        $allowed_order   = ['asc', 'desc'];

        // validate orderby and order parameters
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'id';
        }

        if (!in_array(strtolower($order), $allowed_order)) {
            $order = 'desc';
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM `$table` ORDER BY `$orderby` $order LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );

        return $wpdb->get_results($sql);
    }

    public function get_sortable_columns()
    {
        return [
            'target'   => ['target', false],
            'status'   => ['status', false],
            'location' => ['location', false],
            'sort'     => ['sort', false],
            'updated'  => ['updated', false],
        ];
    }

    public function column_title($item)
    {
        $actions = [
            'edit' => sprintf('<a href="themes.php?page=swiper&t=edit&id=%s" class="swiper-edit">%s</a>', $item->{"id"}, __('Edit', 'G3')),
        ];

        return sprintf('%1$s %2$s', $item->{"title"}, $this->row_actions($actions));
    }

    public function get_bulk_actions()
    {
        return [
            'delete'  => __('Delete'),
            'online'  => __('Online', 'G3'),
            'offline' => __('Offline', 'G3'),
        ];
    }

    public function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="swiper[]" value="%s" />',
            $item->{"id"}
        );
    }

    public function extra_tablenav($which): void
    {
        if ($which == "top") {
            echo '<div class="alignleft actions"><a href="themes.php?page=swiper&t=new" class="button button-primary swiper-add">' . __('Add New', 'G3') . '</a></div>';
        }
    }

    public function process_bulk_actions()
    {
        $doaction = $this->current_action();

        $ids = isset($_REQUEST['swiper']) ? (array) $_REQUEST['swiper'] : [];

        if (!$doaction || empty($ids)) {
            return;
        }

        switch ($doaction) {
            case 'delete':
                $deleted = 0;
                foreach ($ids as $id) {
                    if ($this->deleteRow($id)) {
                        $deleted++;
                    }
                }
                break;

            case 'offline':
                $this->updateStatus($ids, 0);
                break;

            case 'online':
                $this->updateStatus($ids, 1);
                break;
        }
        $msg = __('Updated', 'G3');
        wp_add_inline_script('jui', 'JUI.Toast.success("' . $msg . '",1000);setTimeout(()=>{location.reload()},1000)');

        // $sendback = wp_get_referer() ?: admin_url('themes.php');
        // wp_safe_redirect(remove_query_arg(['action', 'action2', 'swiper'], $sendback));
        // exit;
    }

    /**
     * delete row
     * 
     * 删除数据
     *
     * @param int $id
     * @return bool|int
     */
    private function deleteRow(int $id): bool|int
    {
        if (!$id) {
            return false;
        }

        global $wpdb;
        $table  = $wpdb->prefix . SwiperService::TABLE;
        $result = $wpdb->delete($table, ['id' => $id]);

        wp_cache_delete($id, SwiperService::CACHE_GROUP);

        // flush swipers query cache
        SwiperService::clearQueryCache();

        return $result;
    }

    private function updateStatus(array $ids, int $status): bool|int
    {
        if (!\is_array($ids) || !\count($ids)) {
            return false;
        }

        global $wpdb;
        $table  = $wpdb->prefix . SwiperService::TABLE;
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table SET `status` = %d WHERE `id` IN (" . implode(',', array_map('intval', $ids)) . ")",
                $status
            )
        );
        foreach ($ids as $id) {
            wp_cache_delete($id, SwiperService::CACHE_GROUP);
        }
        return $result;
    }
    public function no_items()
    {
        _e('No data found.', 'G3');
    }
}