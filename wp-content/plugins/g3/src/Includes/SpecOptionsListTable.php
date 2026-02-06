<?php

namespace JEALER\G3\Includes;

use JEALER\G3\Container\Container;
use JEALER\G3\Services\ProductService;
use WP_List_Table;

class SpecOptionsListTable extends WP_List_Table {
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
            'cb'      => '<input type="checkbox" />',
            'name'    => __('Name'),
            'key'     => 'Key',
            'spec_id' => __('Specifications', 'G3'),
            'status'  => __('Status', 'G3'),
            'count'   => __('Linked SKU', 'G3'),
            'action'  => __('Action')
        ];
    }
    public function prepare_items(): void
    {
        $this->service = Container::run()->get(ProductService::class);
        $this->table   = $this->wpdb->prefix . ProductService::SPECS_OPTIONS_TABLE;
        $this->perPage = 20;

        $columns               = $this->get_columns();
        $hidden                = [];
        $sortable              = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $currentPage = $this->get_pagenum();
        $offset      = ($currentPage - 1) * $this->perPage;
        $search      = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $this->count = $this->getCount($search) ?: '';

        $perPage     = $this->perPage;
        $this->items = $this->getData(compact('search', 'perPage', 'offset'));
        $this->set_pagination_args([
            'total_items' => $this->count,
            'per_page'    => $this->perPage,
        ]);
    }

    public function display(): void
    {
        echo '<h3 class="float-left">' . __('Options', 'G3') . '</h3>';
        echo '<form id="list-form" method="post">';
        $this->search_box(__('Search'), 'options');
        parent::display();
        echo '</form>';
        // $this->process_bulk_action();
    }
    public function column_default($item, $column_name)
    {
        return match ($column_name) {
            'name' => $item['name'],
            'key' => $item['key'],
            'spec_id' => $this->renderSpec($item['spec_id']),
            'status' => $item['status'] ? __('Enabled') : __('Disabled'),
            'count' => $this->renderCount($item),
            'action' => $this->renderAction($item),
            default => $item[$column_name] ?? '',
        };
    }
    public function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="option_ids[]" value="%s" />',
            $item['id']
        );
    }
    public function extra_tablenav($which): void
    {
        if ($which == "top") {
            echo '<div class="alignleft actions mb-2"><button type="button" id="add-spec-option" class="button button-primary">' . __('Add') . '</button></div>';
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

        // Build query
        if ($search) {
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$this->table} 
                 WHERE name LIKE %s OR `key` LIKE %s 
                 ORDER BY id DESC 
                 LIMIT %d OFFSET %d",
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
             WHERE name LIKE %s OR `key` LIKE %s",
                '%' . $this->wpdb->esc_like($search) . '%',
                '%' . $this->wpdb->esc_like($search) . '%'
            );
        } else {
            $query = "SELECT COUNT(*) FROM {$this->table}";
        }

        return (int) $this->wpdb->get_var($query);
    }
    public function process_bulk_action(): void
    {
    }
    private function renderSpec($id): string|null
    {
        $specs = $this->service->getSpecs();
        foreach ($specs as $spec) {
            if ($spec['id'] == $id) {
                return (string) $spec['name'];
            }
        }
        return null;
    }
    private function renderCount($item): string|null
    {
        $table = $this->wpdb->prefix . ProductService::SPECS_RELATIONS_TABLE;
        $query = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE spec_option_id = %d",
            $item['id']
        );
        return $this->wpdb->get_var($query) ?: null;
    }
    private function renderAction($item): string
    {
        return sprintf(
            '<span class="edit-spec-option color-link cursor-pointer" 
              data-id="%d" 
              data-name=\'%s\' 
              data-key=\'%s\' 
              data-spec="%d"
              data-status="%d"
              data-count=\'%s\'
              >%s</span>
        |
        <span class="delete-spec-option color-error cursor-pointer" data-id="%d" data-count="%s" %s>%s</span>',
            (int) $item['id'],
            $item['name'],
            $item['key'],
            (int) ($item['spec_id'] ?? 1),
            (int) ($item['status'] ?? 0),
            $this->renderCount($item),
            __('Edit'),
            (int) $item['id'],
            $this->renderCount($item),
            ((int) $this->renderCount($item) > 0) ? 'disabled' : '',
            __('Delete')
        );
    }
}