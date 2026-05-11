<?php

namespace JEALER\G3\Services;

/**
 * Payment Service
 * 
 * 支付服务
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class PaymentService {

    /**
     * Wallet Option Key
     * 
     * 钱包配置项键名
     */
    const WALLET_OPTION_KEY = 'g3_option_wallet';

    /**
     * Payment Log Table
     * 
     * 支付记录表
     */
    const TABLE = 'g3_payment_log';

    private $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
}
