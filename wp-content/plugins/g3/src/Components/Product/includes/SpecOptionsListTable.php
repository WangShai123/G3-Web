<?php
namespace JEALER\G3\Components\Product\Includes;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Services\ProductService;
use WP_List_Table;
class SpecOptionsListTable extends WP_List_Table {
    private string         $table;
    private int            $perPage;
    private int            $count;
    private                $wpdb;
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
    public function get_sortable_columns(): array
    {
        return [
            'spec_id' => ['spec_id', false],
            'status'  => ['status', false]
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

        // $this->count = $this->getCount($search) ?: '';
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
        echo '<form id="list-form" method="post">';
        $this->search_box(__('Search'), 'options');
        parent::display();
        echo '</form>';
        // $this->process_bulk_action();
    }
    public function column_default($item, $column_name)
    {
        return match ($column_name) {
            'name'    => $item['name'],
            'key'     => $item['key'],
            'spec_id' => $this->renderSpec($item['spec_id']),
            'status'  => $item['status'] ? __('Enabled') : __('Disabled'),
            'count'   => $this->renderCount($item),
            'action'  => $this->renderAction($item),
            default   => $item[$column_name] ?? '',
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

        // 获取排序参数
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'id';
        $order   = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'DESC';

        // 验证排序字段是否合法
        $allowedOrderBy = ['id', 'name', 'key', 'spec_id', 'status', 'count'];
        if (!in_array($orderby, $allowedOrderBy)) {
            $orderby = 'id';
        }

        // 验证排序方向是否合法
        if (!in_array($order, ['ASC', 'DESC'])) {
            $order = 'DESC';
        }

        // 获取规格映射（spec_id -> 规格名称），配合规格值搜索
        $specMap = $this->getSpecMap();
        if ($search) {
            foreach ($specMap as $id => $name) {
                if (stripos($name, $search) !== false) {
                    $matchingSpecIds[] = (int) $id;
                }
            }
        }

        // Build query
        if ($search) {
            $likeSearch = '%' . $this->wpdb->esc_like($search) . '%';
            if (!empty($matchingSpecIds)) {
                // 如果找到了匹配的 spec_id，则优先使用它们进行筛选
                $placeholders = implode(',', array_fill(0, count($matchingSpecIds), '%d'));
                $params       = array_merge(
                    [$likeSearch, $likeSearch],
                    $matchingSpecIds,
                    [$perPage, $offset]
                );

                $query = $this->wpdb->prepare(
                    "SELECT * FROM {$this->table} 
                WHERE name LIKE %s 
                OR `key` LIKE %s 
                OR `spec_id` IN ($placeholders)
                ORDER BY {$orderby} {$order} 
                LIMIT %d OFFSET %d",
                    ...$params
                );
            } else {
                // 如果没有匹配的 spec_id，则只匹配 name 和 key
                $query = $this->wpdb->prepare(
                    "SELECT * FROM {$this->table} 
                    WHERE name LIKE %s 
                    OR `key` LIKE %s 
                    ORDER BY {$orderby} {$order} 
                    LIMIT %d OFFSET %d",
                    $likeSearch,
                    $likeSearch,
                    $perPage,
                    $offset
                );
            }
        } else {
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$this->table} 
                ORDER BY {$orderby} {$order} 
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
        // 获取规格映射（spec_id -> 规格名称）
        $specMap = $this->getSpecMap();

        // 如果有搜索关键词，则查找匹配的 spec_id
        $matchingSpecIds = [];

        if ($search) {
            $likeSearch = '%' . $this->wpdb->esc_like($search) . '%';

            // 如果找到了匹配的 spec_id，则优先使用它们进行筛选
            if (!empty($matchingSpecIds)) {
                $placeholders = implode(',', array_fill(0, count($matchingSpecIds), '%d'));
                $params       = array_merge([$likeSearch, $likeSearch], $matchingSpecIds);

                $query = $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table} 
                WHERE name LIKE %s 
                OR `key` LIKE %s 
                OR `spec_id` IN ($placeholders)",
                    ...$params
                );
            } else {
                $query = $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table} 
                    WHERE name LIKE %s 
                    OR `key` LIKE %s",
                    $likeSearch,
                    $likeSearch
                );
            }
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
    private function getSpecMap(): array
    {
        // 使用静态变量缓存结果
        static $specMap = null;

        if ($specMap === null) {
            $specs   = $this->service->getSpecs();
            $specMap = [];

            foreach ($specs as $spec) {
                $specMap[$spec['id']] = $spec['name'];
            }
        }

        return $specMap;
    }
}