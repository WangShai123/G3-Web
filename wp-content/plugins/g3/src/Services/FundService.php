<?php

namespace JEALER\G3\Services;

/**
 * Fund Service
 * 
 * 资金服务
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class FundService {

    const TABLE = 'g3_fund_flow';
    private $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
}
