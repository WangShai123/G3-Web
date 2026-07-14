<?php

namespace JEALER\G3\Components\User\Includes;

use JEALER\G3\Services\UserService;
use JEALER\G3\Utilities\Common;
use JEALER\G3\Utilities\Option;
use WP_List_Table;

class DurationListTable extends WP_List_Table {

    private int $perPage;

    private $wpdb;

    public function __construct()
    {
        parent::__construct([
            'singular' => 'duration',
            'plural'   => 'durations',
            'ajax'     => false
        ]);
        $this->init();
    }

    private function init()
    {
        global $wpdb;
        $this->wpdb = $wpdb;

        $this->perPage = 20;
    }

    public function get_columns(): array
    {
        return [
            'cb'       => '<input type="checkbox" />',
            'name'     => __('Name'),
            'slug'     => __('Slug'),
            'duration' => __('Membership Duration', 'G3'),
            'action'   => __('Action')
        ];
    }

    public function prepare_items()
    {
        $columns               = $this->get_columns();
        $hidden                = [];
        $sortable              = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $totalItems  = $this->getCount();
        $this->items = $this->getData();
        $this->set_pagination_args([
            'total_items' => $totalItems,
            'per_page'    => $this->perPage,
        ]);
    }

    public function column_default($item, $column_name)
    {
        return match ($column_name) {
            'duration' => sprintf(_n('%s second', '%s seconds', $item['duration']), $item['duration']),
            'action'   => $this->renderAction($item),
            default    => $item[$column_name] ?? ''
        };
    }

    public function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="slug[]" value="%s" />',
            $item['slug']
        );
    }

    public function extra_tablenav($which): void
    {
        if ($which == "top") {
            echo '<div class="alignleft actions mb-2"><button type="button" class="button button-primary add-duration">' . __('Add New', 'G3') . '</button></div>';
        }
    }

    public function get_bulk_actions(): array
    {
        return [
            'delete' => __('Delete')
        ];
    }

    public function display(): void
    {
        $this->prepare_items();

        echo '<div class="wrap">';
        echo '<form id="list-form" method="post">';
        parent::display();
        echo '</form></div>';

        $this->process_bulk_action();
    }

    public function process_bulk_action(): void
    {
        $action = $this->current_action();
        $slugs  = isset($_REQUEST['slug']) ? (array) $_REQUEST['slug'] : [];

        if (!$action || empty($slugs)) {
            return;
        }

        $array = $this->getData();
        foreach ($slugs as $slug) {
            if (isset($array[$slug])) {
                unset($array[$slug]);
            }
        }
        $result = update_option(UserService::DURATION_OPTION_KEY, $array);
        if ($result) {
            $msg = __('Deleted', 'G3');
            wp_add_inline_script('jui', 'jui.Toast.success("' . $msg . '",1000);setTimeout(()=>{location.reload()},800)');
        }
    }

    private function getData(): array
    {
        return get_option(UserService::DURATION_OPTION_KEY, []);
    }

    private function getCount(): int
    {
        return count($this->getData());
    }

    private function renderAction($item)
    {
        return sprintf(
            '<span class="edit-duration color-link cursor-pointer" data-name="%s" data-slug="%s" data-duration="%s">%s</span> <span class="delete-duration color-error cursor-pointer" data-slug="%s">%s</span>',
            $item['name'],
            $item['slug'],
            $item['duration'],
            __('Edit'),
            $item['slug'],
            __('Delete')
        );
    }
}
