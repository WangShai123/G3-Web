<?php
namespace JEALER\G3\Includes;
use WP_List_Table;
use JEALER\G3\Services\SwiperService;
class SwiperLocationTable extends WP_List_Table {
    private string $key;
    private array $locations;
    public function __construct($args = [])
    {
        parent::__construct();
        $this->key       = SwiperService::LOCATION_OPTION_KEY;
        $this->locations = get_option($this->key, []);
    }

    public function get_columns()
    {
        return [
            'cb'     => '<input type="checkbox" />',
            'key'    => __('Slug'),
            'name'   => __('Name'),
            'action' => __('Action')
        ];
    }

    public function prepare_items()
    {
        $perPage    = 50;
        $totalItems = count($this->locations);

        $columns               = $this->get_columns();
        $hidden                = [];
        $sortable              = [];
        $this->_column_headers = array($columns, $hidden, $sortable);

        $this->items = $this->getData();
        $this->set_pagination_args(
            array(
                'total_items' => $totalItems,
                'per_page'    => $perPage
            )
        );
    }

    public function column_default($item, $column_name)
    {
        return $item[$column_name];
    }

    private function getData(): array
    {
        $data = [];
        foreach ($this->locations as $key => $value) {
            array_push($data, ['key' => $key, 'name' => $value]);
        }
        return $data;
    }

    public function extra_tablenav($which): void
    {
        if ($which == "top") {
            echo '<div class="alignleft actions">
            <button class="button button-primary" id="addNew" type="button">' . __('Add New', 'G3') . '</button></div>';
            // submit_button(__('Add New', 'G3'), 'primary', 'addNew', false);
        }
    }

    public function column_action($item)
    {
        return sprintf(
            '<a href="javascript:void(0);" class="editLocation" data-key="%s" data-name="%s">%s</a>',
            $item['key'],
            $item['name'],
            __('Edit')
        );
    }
    public function get_bulk_actions(): array
    {
        return ['delete' => __('Delete')];
    }
    public function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="swiperLocation[]" value="%s" />',
            $item['key']
        );
    }
    public function process_bulk_actions(): void
    {
        if ($this->current_action() === 'delete') {
            if (!empty($_POST['swiperLocation'])) {
                $items = $_POST['swiperLocation'];

                if (!is_array($items)) {
                    $items = [$items];
                }

                foreach ($items as $key) {
                    unset($this->locations[$key]);
                }

                update_option($this->key, $this->locations);

                $msg = __('Deleted', 'G3');
                wp_add_inline_script('jui', 'JUI.Toast.success("' . $msg . '",1000);setTimeout(()=>{location.reload()},800)');
            }
        }
    }
    public function no_items()
    {
        _e('No data found.', 'G3');
    }
}