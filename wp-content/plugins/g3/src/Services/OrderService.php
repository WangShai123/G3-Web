<?php

namespace JEALER\G3\Services;

class OrderService {
    const TABLE          = 'g3_orders';
    const ADDRESS_TABLE  = 'g3_order_address';
    const DELIVERY_TABLE = 'g3_order_delivery';
    const ITEMS_TABLE    = 'g3_order_items';
    private $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
}