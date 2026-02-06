<?php

namespace JEALER\G3\Services;

class FundService {
    const TABLE = 'g3_fund_flow';
    private $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
}