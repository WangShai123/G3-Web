<?php

namespace JEALER\G3\Components\Swiper\Includes;

use WP_List_Table;
use JEALER\G3\Services\SwiperService;
use JEALER\G3\Utilities\Common;
use JEALER\G3\Utilities\Validator;
use JEALER\G3\Utilities\Date;

/**
 * Swiper List Table
 *
 * 轮播图列表表格
 *
 * @since 1.0.0
 * @author Wang Shai
 */
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
            'cb'         => '<input type="checkbox" />',
            // 'id' => 'ID',
            'title'      => __('Title', 'G3'),
            'media'      => __('Media', 'G3'),
            'link'       => __('Link', 'G3'),
            'target'     => __('Open Method', 'G3'),
            'location'   => __('Location', 'G3'),
            'sort'       => __('Sort', 'G3'),
            'status'     => __('Status', 'G3'),
            'updated_at' => __('Update Time', 'G3')
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
        $this->items = $this->getData($this->perPage, $currentPage);
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'status':
                return SwiperService::renderStatus($item->{$column_name});
            case 'media':
                return $this->renderMedia($item, $column_name);
            case 'target':
                return SwiperService::renderTarget($item->{$column_name});
            case 'link':
                return $this->renderLink($item, $column_name);
            case 'location':
                return $this->renderLocation($item, $column_name);
            case 'updated_at':
                return $this->renderUpdated($item, $column_name);
            default:
                return $item->{$column_name} ?? '-';
        }
    }

    private function getData($per_page, $current_page): array|object|null
    {
        global $wpdb;

        $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'id';
        $order   = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'desc';

        $offset = ($current_page - 1) * $per_page;

        $table = $wpdb->prefix . SwiperService::TABLE;

        // fix SQL syntax error, correctly escape column names and sorting direction
        $allowed_orderby = ['id', 'title', 'link', 'target', 'location', 'sort', 'status', 'updated_at'];
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

    public function get_sortable_columns(): array
    {
        return [
            'target'     => ['target', false],
            'status'     => ['status', false],
            'location'   => ['location', false],
            'sort'       => ['sort', false],
            'updated_at' => ['updated', false],
        ];
    }

    public function column_title($item): string
    {
        $actions = [
            'edit' => sprintf('<a href="themes.php?page=swiper&t=edit&id=%s" class="swiper-edit">%s</a>', $item->{"id"}, __('Edit', 'G3')),
        ];

        return sprintf('%1$s %2$s', $item->{"title"}, $this->row_actions($actions));
    }

    public function get_bulk_actions(): array
    {
        return [
            'delete'  => __('Delete'),
            'enable'  => __('Enable'),
            'disable' => __('Disable'),
        ];
    }

    public function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="swiper[]" value="%s" />',
            $item->{"id"}
        );
    }

    public function extra_tablenav($which): void
    {
        if ($which == "top") {
            echo '<div class="alignleft actions mb-2"><a href="themes.php?page=swiper&t=new" class="button button-primary swiper-add">' . __('Add New', 'G3') . '</a></div>';
        }
    }

    public function process_bulk_actions(): void
    {
        $action = $this->current_action();
        $ids    = isset($_REQUEST['swiper']) ? (array) $_REQUEST['swiper'] : [];

        if (!$action || empty($ids)) {
            return;
        }

        $msg = __('Updated', 'G3');
        switch ($action) {
            case 'delete':
                $msg = __('Deleted', 'G3');
                SwiperService::deleteSwipers($ids);
                break;

            case 'disable':
                SwiperService::updateStatus($ids, 0);
                break;

            case 'enable':
                SwiperService::updateStatus($ids, 1);
                break;
        }
        wp_add_inline_script('jui', 'jui.Toast.success("' . $msg . '",1000);setTimeout(()=>{location.reload()},800)');
    }

    private function renderMedia($item, $columnName): string
    {
        // if (Validator::isImage($item->{$columnName})) {
        return '<img class="object-cover cursor-pointer swiperPreview" style="width:100px;height:60px" src="' . $item->{$columnName} . '" draggable="false"></img>';
        // }
        // return '-';
    }

    private function renderLink($item, $columnName): string
    {
        $link = $item->{$columnName};
        return Validator::isURL($link)
            ? '<a href="' . $link . '" target="_blank">' . Common::truncateHtml($link, 50) . '</a>'
            : '-';
    }

    private function renderLocation($item, $columnName): string
    {
        $option = maybe_unserialize(get_option(SwiperService::LOCATION_OPTION_KEY));
        if (!$option) {
            return '-';
        }

        $location     = explode(',', $item->{$columnName});
        $location_str = '';
        $found        = false;

        foreach ($location as $value) {
            foreach ($option as $key => $label) {
                if ($key === $value) {
                    if ($location_str !== '') {
                        $location_str .= ',';
                    }
                    $location_str .= $label;
                    $found         = true;
                    break;
                }
            }
        }

        return $found ? $location_str : '-';
    }

    private function renderUpdated($item, $columnName): string
    {
        return Date::dateTime(strtotime($item->{$columnName}));
    }
}
