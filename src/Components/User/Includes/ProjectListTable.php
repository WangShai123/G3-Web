<?php
namespace JEALER\G3\Components\User\Includes;
use JEALER\G3\Services\UserService;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Option;
use WP_List_Table;

class ProjectListTable extends WP_List_Table {
    private int  $perPage;
    private      $wpdb;
    private bool $display;

    public function __construct()
    {
        parent::__construct([
            'singular' => 'project',
            'plural'   => 'projects',
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
            'duration' => __('Membership Duration', 'G3'),
            'price'    => __('Price', 'G3'),
            'copy'     => __('Payment Link', 'G3'),
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
            'name'     => $this->renderName($item),
            'duration' => $this->renderDuration($item),
            'copy'     => $this->renderCopy($item),
            'action'   => $this->renderAction($item),
            default    => $item[$column_name] ?? ''
        };
    }

    public function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="ids[]" value="%s" />',
            $item['id']
        );
    }

    public function extra_tablenav($which): void
    {
        if ($which == "top") {
            echo '<div class="alignleft actions mb-2"><button type="button" class="button button-primary add-project">' . __('Add New', 'G3') . '</button></div>';
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

        if ($this->ifDisplay()) {
            $this->display = true;
            echo '<form id="list-form" method="post">';
            parent::display();
            echo '</form>';
        } else {
            $this->display = false;
            echo Element::tip(__('The membership duration or group is not configured.', 'G3'), '', 'danger');
        }

        echo '</div>';

        $this->process_bulk_action();
    }

    public function process_bulk_action(): void
    {
        $action = $this->current_action();
        $ids    = isset($_REQUEST['ids']) ? (array) $_REQUEST['ids'] : [];
        if (!$action || empty($ids)) {
            return;
        }

        $array = $this->getData();
        foreach ($ids as $id) {
            if (isset($array[$id])) {
                unset($array[$id]);
            }
        }
        $result = update_option(UserService::MEMBERSHIP_OPTION_KEY, $array);
        if ($result) {
            $msg = __('Deleted', 'G3');
            wp_add_inline_script('jui', 'jui.Toast.success("' . $msg . '",1000);setTimeout(()=>{location.reload()},800)');
        }
    }

    private function ifDisplay(): bool
    {
        return (!$this->getDuration() || !$this->getGroup()) ? false : true;
    }

    private function getDuration(): array
    {
        return get_option(UserService::DURATION_OPTION_KEY, []);
    }

    private function getGroup(): array
    {
        return get_option(UserService::GROUP_OPTION_KEY, []);
    }

    private function getData(): array
    {
        return (array) get_option(UserService::MEMBERSHIP_OPTION_KEY, []);
    }

    private function getCount(): int
    {
        return count($this->getData());
    }

    private function renderName($item)
    {
        $group = $this->getGroup();
        return $group[$item['name']]['name'] ?? '';
    }
    private function renderDuration($item)
    {
        $duration = $this->getDuration();
        return $duration[$item['duration']]['name'] ?? '';
    }
    private function renderCopy($item)
    {
        //@todo membership payment link generation
        $link = '@todo';
        return sprintf(
            '<span class="copy-payLink color-link cursor-pointer" data-link="%s">%s</span>',
            $link,
            __('Copy'),
        );
    }
    private function renderAction($item)
    {
        return sprintf(
            '<span class="edit-project color-link cursor-pointer" data-name="%s" data-duration="%s" data-price="%s">%s</span> <span class="delete-project color-error cursor-pointer" data-id="%s">%s</span>',
            $item['name'],
            $item['duration'],
            $item['price'],
            __('Edit'),
            $item['id'],
            __('Delete')
        );
    }
}
