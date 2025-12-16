<?php
namespace JEALER\G3\Includes;
use WP_List_Table;
use JEALER\G3\Services\WechatOAService;

class WechatOAMenuListTable extends WP_List_Table {
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
            'name'   => __('Name'),
            'sort'   => __('Sort', 'G3'),
            'type'   => __('Type', 'G3'),
            'value'  => 'Key/URL',
            'action' => __('Action')
        ];
    }

    public function prepare_items(): void
    {
        $menus                 = WechatOAService::getMenus();
        $this->items           = WechatOAService::formatMenus($menus);
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
    }

    public function get_bulk_actions()
    {
        return [
        ];
    }

    public function display(): void
    {
        $this->prepare_items();

        echo '<div class="mt-3">';
        echo '<table class="wp-list-table widefat fixed striped wechat-oa-menus-table">';
        $this->display_table_header();
        $this->display_rows_or_placeholder();
        echo '</table>';
    }

    public function display_table_header()
    {
        echo '<thead><tr>';
        foreach ($this->get_columns() as $column_name => $column_display_name) {
            $class      = "class='$column_name column-$column_name'";
            $style      = '';
            $attributes = $class . $style;

            echo "<th $attributes>" . esc_html($column_display_name) . '</th>';
        }
        echo '</tr></thead>';
    }

    public function display_rows()
    {
        foreach ($this->items as $item) {
            echo '<tr>';
            foreach ($this->get_columns() as $column_name => $column_display_name) {
                $class      = "class='$column_name column-$column_name'";
                $style      = '';
                $attributes = $class . $style;

                if ($column_name == 'action') {
                    echo "<td $attributes>";
                    echo "<a href='admin.php?page=wechat-oa-menu-edit&id={$item['id']}''>" . __('Edit') . "</a> - ";
                    echo "<span class='action-delete cursor-pointer color-error' data-id='{$item['id']}'>" . __('Delete') . "</span>";
                    echo "</td>";
                } else {
                    echo "<td $attributes>" . esc_html($item[$column_name]) . "</td>";
                }
            }
            echo '</tr>';
        }
    }
    public function no_items(): void
    {
        _e('No data found.', 'G3');
    }

    public function extra_tablenav($which): void
    {
        if ($which == "top") {
            echo '<div class="alignleft actions"><a href="themes.php?page=swiper&t=new" class="button button-primary swiper-add">' . __('Add New', 'G3') . '</a></div>';
        }
    }

    public function get_sortable_columns(): array
    {
        return [];
    }
}