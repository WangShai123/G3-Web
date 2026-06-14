<?php

namespace JEALER\G3\Components\Product\Includes;

use JEALER\G3\Core\Container\Container;
use JEALER\G3\Services\ProductService;
use WP_List_Table;

/**
 * Sku List Table
 * 
 * SKU列表表格
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class SkuListTable extends WP_List_Table {

    private string $table;

    private int $perPage;

    private int $count;

    private $wpdb;

    private ProductService $service;

    public function __construct()
    {
        parent::__construct([
            'singular' => 'spec',
            'plural'   => 'specs',
            'ajax'     => false
        ]);
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->prepare_items();
    }

    public function get_columns(): array
    {
        return [
            'cb'            => '<input type="checkbox" />',
            'product_id'    => __('Product', 'G3'),
            'sku_code'      => __('SKU Code', 'G3'),
            'regular_price' => __('Regular Price', 'G3'),
            'price'         => __('Price', 'G3'),
            'type'          => __('Type'),
            'stock'         => __('Stock', 'G3'),
            'sold'          => __('Sold', 'G3'),
            'status'        => __('Status', 'G3'),
            'action'        => __('Action')
        ];
    }

    public function prepare_items(): void
    {
        $this->service = Container::run()->get(ProductService::class);
        $this->table   = $this->wpdb->prefix . ProductService::SKU_TABLE;
        $this->perPage = 20;

        $columns               = $this->get_columns();
        $hidden                = [];
        $sortable              = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $currentPage = $this->get_pagenum();
        $offset      = ($currentPage - 1) * $this->perPage;
        $search      = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $this->count = $this->getCount($search);

        $perPage     = $this->perPage;
        $this->items = $this->getData(compact('search', 'perPage', 'offset'));
        $this->set_pagination_args([
            'total_items' => $this->count,
            'per_page'    => $this->perPage,
        ]);
    }

    public function display(): void
    {
        echo '
        <ul class="subsubsub">
            <li class="all"><a href="/wp-admin/edit.php?post_type=product&page=product_sku" class="current" aria-current="page">' . __('All') . '<span class="count">（' . $this->count . '）</span></a></li>
        </ul>';
        echo '<form id="list-form" method="post">';
        $this->search_box(__('Search'), 'sku');
        parent::display();
        echo '</form>';
        // $this->process_bulk_action();
    }

    public function column_default($item, $column_name)
    {
        return match ($column_name) {
            'product_id'    => $this->renderProductInfo($item['product_id']),
            'sku_code'      => $item['sku_code'],
            'regular_price' => $item['regular_price'],
            'price'         => $item['price'],
            'type'          => ProductService::renderSkuType($item['type']),
            'stock'         => $item['stock'],
            'sold'          => $item['sold'],
            'status'        => $item['status'] ? __('Enable') : __('Disable'),
            'action'        => $this->renderAction($item),
            default         => $item[$column_name] ?? '',
        };
    }

    public function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="sku_ids[]" value="%s" />',
            $item['id']
        );
    }

    public function extra_tablenav($which): void
    {
        if ($which == "top") {
            echo
                '<div class="alignleft actions mb-2">
            <button type="button" id="import-sku" class="button button-primary" onClick="jui.toast.error(`@todo feature.`)">' . __('Import') . '</button>
            </div>';
        }
    }

    public function get_bulk_actions(): array
    {
        return [
            // 'delete'  => __('Delete'),
        ];
    }

    public function getData($args): array
    {
        $search  = $args['search'] ?? '';
        $perPage = $args['perPage'] ?? $this->perPage;
        $offset  = $args['offset'] ?? 0;
        if ($search) {
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$this->table} 
                 WHERE `product_id` LIKE %s OR `sku_code` LIKE %s OR `price` LIKE %s
                 ORDER BY id DESC 
                 LIMIT %d OFFSET %d",
                '%' . $this->wpdb->esc_like($search) . '%',
                '%' . $this->wpdb->esc_like($search) . '%',
                '%' . $this->wpdb->esc_like($search) . '%',
                $perPage,
                $offset
            );
        } else {
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$this->table} 
                 ORDER BY id DESC 
                 LIMIT %d OFFSET %d",
                $perPage,
                $offset
            );
        }
        $results = $this->wpdb->get_results($query, ARRAY_A);
        return $results ?: [];
    }

    public function getCount($search): int
    {
        if ($search) {
            $query = $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} 
                 WHERE `product_id` LIKE %s",
                "%{$search}%"
            );
        } else {
            $query = "SELECT COUNT(*) FROM {$this->table}";
        }
        return (int) $this->wpdb->get_var($query);
    }

    public function process_bulk_action(): void
    {
    }

    private function renderAction($item): string
    {
        return sprintf(
            '<a class="view-sku color-link cursor-pointer" 
              href="/wp-admin/edit.php?s=%d&post_status=all&post_type=product"
              data-id="%d" 
              >%s</a>
        | <span class="delete-sku color-error cursor-pointer" data-id="%d">%s</span>',
            (int) $item['product_id'],
            (int) $item['id'],
            __('View'),
            (int) $item['id'],
            __('Delete')
        );
    }

    private function renderProductInfo(int $id): string
    {
        $title = get_the_title($id) ?: __('Deleted', 'G3');
        return "{$id}-{$title}";
    }
}
