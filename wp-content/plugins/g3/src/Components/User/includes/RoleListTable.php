<?php

namespace JEALER\G3\Components\User\Includes;

use JEALER\G3\Utilities\Option;
use JEALER\G3\Services\UserService;
use WP_List_Table;

/**
 * Role List Table
 * 
 * 用户角色列表表格
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class RoleListTable extends WP_List_Table {

    private array $columns;

    private int $perPage = 100;

    private array $default = [];

    public function __construct()
    {
        parent::__construct([
            'singular' => 'role',
            'plural'   => 'roles',
            'ajax'     => true
        ]);

        $this->default = $this->initDefaultData();
    }

    public function get_columns(): array
    {
        return [
            'cb'      => '<input type="checkbox" />',
            'name'    => __('Name'),
            'slug'    => __('Slug'),
            'credits' => __('Between Credits', 'G3'),
            'action'  => __('Action')
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
            'name'    => sprintf('%s', __($item['name'], 'G3')),
            'credits' => sprintf('(%s, %s]', $item['credits'][0], $item['credits'][1]),
            'action'  => $this->renderAction($item),
            default   => $item[$column_name] ?? ''
        };
    }

    public function column_cb($item): string
    {
        if ($item['slug'] === 'abandon' || $item['slug'] === 'beginner') {
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
            echo '<div class="alignleft actions mb-2"><button class="button button-primary add-role" data-name="" data-slug="" data-start="" data-end="">' . __('Add New', 'G3') . '</button></div>';
        }
    }

    public function process_bulk_actions(): void
    {
        $action = $this->current_action();
        $slugs  = isset($_REQUEST['slug']) ? (array) $_REQUEST['slug'] : [];

        if (!$action || empty($slugs)) {
            return;
        }

        $roles = Option::get(UserService::ROLE_OPTION_KEY);
        foreach ($slugs as $slug) {
            if (isset($roles[$slug])) {
                unset($roles[$slug]);
            }
        }
        $result = Option::update(UserService::ROLE_OPTION_KEY, $roles);
        if ($result) {
            $msg = __('Deleted', 'G3');
            wp_add_inline_script('jui', 'jui.Toast.success("' . $msg . '",1000);setTimeout(()=>{location.reload()},800)');
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
            'abandon'  => [
                'name'    => __('Limited User', 'G3'),
                'slug'    => 'abandon',
                'credits' => ['-∞', 0]
            ],
            'beginner' => [
                'name'    => __('Beginner', 'G3'),
                'slug'    => 'beginner',
                'credits' => [0, 50]
            ]
        ];
    }

    private function getData(): array
    {
        $roles = Option::get(UserService::ROLE_OPTION_KEY, $this->default);

        $items = [];
        foreach ($roles as $slug => $data) {
            $items[] = array_merge(['slug' => $slug], $data);
        }
        usort($items, function ($a, $b) {
            $endA = is_numeric($a['credits'][0]) ? $a['credits'][0] : PHP_INT_MIN;
            $endB = is_numeric($b['credits'][0]) ? $b['credits'][0] : PHP_INT_MIN;
            return $endA <=> $endB;
        });
        return $items;
    }

    private function getCount(): int
    {
        return count($this->getData());
    }

    private function renderAction($item): string
    {
        if ($item['slug'] === 'abandon' || $item['slug'] === 'beginner') {
            return sprintf(
                '<span class="edit-role color-link cursor-pointer" data-name="%s" data-slug="%s" data-start="%s" data-end="%s">%s</span> <span class="reset-role cursor-pointer" data-slug="%s">%s</span>',
                __($item['name'], 'G3'),
                $item['slug'],
                $item['credits'][0],
                $item['credits'][1],
                __('Edit'),
                $item['slug'],
                __('Reset', 'G3')
            );
        }
        return sprintf(
            '<span class="edit-role color-link cursor-pointer" data-name="%s" data-slug="%s" data-start="%s" data-end="%s">%s</span> <span class="delete-role color-error cursor-pointer" data-slug="%s">%s</span>',
            $item['name'],
            $item['slug'],
            $item['credits'][0],
            $item['credits'][1],
            __('Edit'),
            $item['slug'],
            __('Delete')
        );
    }
}
