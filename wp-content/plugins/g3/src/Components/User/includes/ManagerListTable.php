<?php

namespace JEALER\G3\Components\User\Includes;

use JEALER\G3\Utilities\Option;
use JEALER\G3\Services\UserService;
use WP_List_Table;

/**
 * Manager List Table
 * 
 * 管理员列表表格
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class ManagerListTable extends WP_List_Table {
    private array $columns;

    private int $perPage = 100;

    private array $default = [];

    public function __construct()
    {
        parent::__construct([
            'singular' => 'customRole',
            'plural'   => 'customRoles',
            'ajax'     => true
        ]);

        $this->default = $this->initDefaultData();
    }

    public function get_columns(): array
    {
        return [
            'cb'     => '<input type="checkbox" />',
            'name'   => __('Name'),
            'slug'   => __('Slug'),
            'type'   => __('Type'),
            'action' => __('Action')
        ];
    }

    public function prepare_items(): void
    {
        $currentPage = $this->get_pagenum();
        $totalItems  = $this->getCount();

        $this->columns         = $this->get_columns();
        $hidden                = [];
        $sortable              = $this->get_sortable_columns();
        $this->_column_headers = array($this->columns, $hidden, $sortable);
        $this->set_pagination_args([
            'total_items' => $totalItems,
            'per_page'    => $this->perPage,
            'total_pages' => ceil($totalItems / $this->perPage)
        ]);
        $this->items = $this->getData();
    }

    public function column_default($item, $column_name)
    {
        return match ($column_name) {
            'name' => sprintf('%s', __($item['name'], 'G3')),
            'type' => $this->renderType($item[$column_name]),
            'action' => $this->renderAction($item),
            default => $item[$column_name] ?? ''
        };
    }

    public function column_cb($item): string
    {
        if ($item['slug'] === 'system' || $item['slug'] === 'admin') {
            return '';
        }
        return sprintf(
            '<input type="checkbox" name="slug[]" value="%s" />',
            $item["slug"]
        );
    }

    public function get_bulk_actions(): array
    {
        return [
            'delete' => __('Delete'),
        ];
    }

    public function extra_tablenav($which): void
    {
        if ($which == "top") {
            echo '<div class="alignleft actions mb-2"><button class="button button-primary add-role">' . __('Add New', 'G3') . '</button></div>';
        }
    }

    public function process_bulk_actions(): void
    {
        $action = $this->current_action();
        $slugs  = isset($_REQUEST['slug']) ? (array) $_REQUEST['slug'] : [];

        if (!$action || empty($slugs)) {
            return;
        }

        $roles = Option::get(UserService::MANAGER_OPTION_KEY);
        foreach ($slugs as $slug) {
            if (isset($roles[$slug])) {
                unset($roles[$slug]);
            }
        }
        $result = Option::update(UserService::MANAGER_OPTION_KEY, $roles);
        if ($result) {
            $msg = __('Deleted', 'G3');
            wp_add_inline_script('jui', 'jui.toast.success("' . $msg . '",1000);setTimeout(()=>{location.reload()},800)');
        }
    }

    public function display(): void
    {
        $this->prepare_items();
        echo '<form id="list-form" method="post">';
        parent::display();
        echo '</form>';
        $this->process_bulk_actions();
    }

    private function initDefaultData(): array
    {
        return [
            'system' => [
                'name' => __('System Admin', 'G3'),
                'slug' => 'system',
                'type' => 1
            ],
            'admin'  => [
                'name' => __('Administrator', 'G3'),
                'slug' => 'admin',
                'type' => 1
            ]
        ];
    }

    private function getData(): array
    {
        return Option::get(UserService::MANAGER_OPTION_KEY, $this->default);
    }

    private function getCount(): int
    {
        return count($this->getData());
    }

    private function renderType($key): string
    {
        return match ($key) {
            1 => __('Build-In', 'G3'),
            2 => __('Custom', 'G3'),
            default => __('Unknown', 'G3')
        };
    }

    private function renderAction($item): string
    {
        if ($item['slug'] === 'system' || $item['slug'] === 'admin') {
            return '';
        }
        return sprintf(
            '<span class="edit-role color-link cursor-pointer" data-name="%s" data-slug="%s">%s</span> <span class="delete-role color-error cursor-pointer" data-slug="%s">%s</span>',
            $item['name'],
            $item['slug'],
            __('Edit'),
            $item['slug'],
            __('Delete')
        );
    }
}
