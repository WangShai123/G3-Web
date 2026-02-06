<?php

namespace JEALER\G3\Includes;

use JEALER\G3\Container\Container;
use JEALER\G3\Services\ProductService;
use WP_List_Table;

class OrderListTable extends WP_List_Table {
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
}