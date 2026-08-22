<?php
namespace JEALER\G3\Services;
use JEALER\G3\Core\Service\Service;

class PaymentService extends Service {
    // Wallet Option Key
    const WALLET_OPTION_KEY = 'g3_option_wallet';
    // Payment Log Table
    const TABLE = 'g3_payment_log';

    public function __construct()
    {
        parent::__construct();
    }
    public static function optionDefaults(): array
    {
        return [
            'enable'     => '0',
            'recharge'   => '0',
            'withdrawal' => '0',
            'bank_fee'   => '5',
            'tax_rate'   => '20',
        ];
    }
}
