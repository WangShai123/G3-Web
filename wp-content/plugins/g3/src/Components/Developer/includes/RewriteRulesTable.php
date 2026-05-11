<?php

namespace JEALER\G3\Components\Developer\Includes;

use WP_List_Table;

/**
 * Rewrite Rules Table
 * 
 * 重写规则表格
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class RewriteRulesTable extends WP_List_Table {

    public function __construct()
    {
        parent::__construct(
            [
                'singular' => __('Rewrite Rule', 'G3'),
                'plural'   => __('Rewrite Rules', 'G3'),
                'ajax'     => false,
            ]
        );
    }

    /**
     * Get columns.
     *
     * @return array
     */
    public function get_columns()
    {
        return [
            'id'      => 'ID',
            'rule'    => __('Rule', 'G3'),
            'rewrite' => __('Rewrite', 'G3'),
        ];
    }

    /**
     * Get sortable columns.
     *
     * @return array
     */
    public function get_sortable_columns()
    {
        return [
            'id'      => ['id', true],
            'rule'    => ['rule', true],
            'rewrite' => ['rewrite', true],
        ];
    }

    /**
     * Get bulk actions.
     *
     * @return array
     */
    public function get_bulk_actions()
    {
        return [];
    }

    /**
     * Prepare items.
     */
    public function prepare_items()
    {
        // 直接从数据库获取rewrite规则，确保显示所有规则
        $rules = get_option('rewrite_rules');

        $data = [];
        $id   = 1;
        if (is_array($rules)) {
            foreach ($rules as $rule => $rewrite_rule_value) {
                $data[] = [
                    'id'      => $id++,
                    'rule'    => $rule,
                    'rewrite' => $rewrite_rule_value,
                ];
            }
        }

        // 不分页，显示所有数据
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
        $this->items           = $data;
    }

    /**
     * Display the table.
     */
    public function display()
    {
        $this->prepare_items();

        echo '<div class="wrap">';
        echo '<table class="wp-list-table widefat fixed striped test-rewrite-rules-table">';
        // no pagination
        $this->display_table_header();
        $this->display_rows_or_placeholder();
        // no pagination
        echo '</table></div>';
        echo '<style>
    .column-id {width:44px;}
    .column-rule {width:30%;}
    .column-rewrite {width:auto;}
    .test-rewrite-rules-table {table-layout:fixed;margin-top:16px;}
</style>';
    }

    /**
     * Display the table header.
     */
    public function display_table_header()
    {
        echo '<thead><tr>';
        foreach ($this->get_columns() as $column_name => $column_display_name) {
            $class      = "class='$column_name column-$column_name'";
            $style      = '';
            $attributes = $class . $style;

            switch ($column_name) {
                case 'id':
                case 'rule':
                case 'rewrite':
                case 'source':
                    echo "<th $attributes>" . esc_html($column_display_name) . '</th>';
                    break;
                default:
                    echo "<th $attributes></th>";
                    break;
            }
        }
        echo '</tr></thead>';
    }

    /**
     * Display the rows.
     */
    public function display_rows()
    {
        foreach ($this->items as $item) {
            echo '<tr>';
            foreach ($this->get_columns() as $column_name => $column_display_name) {
                $class      = "class='$column_name column-$column_name'";
                $style      = '';
                $attributes = $class . $style;

                switch ($column_name) {
                    case 'id':
                    case 'rule':
                    case 'rewrite':
                    case 'source':
                        echo "<td $attributes>" . esc_html($item[$column_name]) . '</td>';
                        break;
                    default:
                        echo "<td $attributes></td>";
                        break;
                }
            }
            echo '</tr>';
        }
    }
}
