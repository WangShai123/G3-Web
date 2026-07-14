<?php
namespace JEALER\G3\Components\Swiper\Includes;
use WP_List_Table;
use JEALER\G3\Services\SwiperService;

class SwiperLocationTable extends WP_List_Table {
    private string $key;
    private array  $locations;
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
        $this->set_pagination_args([
            'total_items' => $totalItems,
            'per_page'    => $perPage
        ]);
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
            echo '<div class="alignleft actions mb-2">
            <button class="button button-primary addLocation" type="button">' . __('Add New', 'G3') . '</button></div>';
        }
    }
    public function column_action($item)
    {
        if ($item['key'] === 'home') {
            return '';
        }
        $actions = [
            'view'   => sprintf(
                '<span class="editLocation color-link cursor-pointer" data-key="%s" data-name="%s">%s</span>',
                $item['key'],
                $item['name'],
                __('Edit')
            ),
            'delete' => sprintf(
                '<span class="deleteLocation color-error cursor-pointer" data-key="%s" data-name="%s">%s</span>',
                $item['key'],
                $item['name'],
                __('Delete')
            )
        ];
        return join(' | ', $actions);
    }
    public function get_bulk_actions(): array
    {
        return ['delete' => __('Delete')];
    }
    public function column_cb($item)
    {
        if ($item['key'] === 'home') {
            return '';
        }
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
                $result = SwiperService::deleteLocations($items);
                if ($result) {
                    $msg = __('Deleted', 'G3');
                    wp_add_inline_script('jui', 'jui.Toast.success("' . $msg . '",1000);setTimeout(()=>{location.reload()},800)');
                } else {
                    $msg = __('Delete Failed', 'G3');
                    wp_add_inline_script('jui', 'jui.Toast.error("' . $msg . '",2000);');
                }
            }
        }
    }
}
