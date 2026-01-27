<?php
namespace JEALER\G3\Components;

use JEALER\G3\Components\Components;
use JEALER\G3\Container\Container;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Services\PaymentService;
use Override;

class Wallet extends Components {
    public array $option = [];

    #[Override]
    protected function options(): void
    {
        $this->option = Option::get(PaymentService::WALLET_OPTION_KEY, [
            'enable'   => '0',
            'recharge' => '0',
        ]);
    }
    protected function init(): void
    {
    }
    #[Override]
    protected function form(): void
    {
        if (!isset($_REQUEST['page']) || $_REQUEST['page'] !== 'wallet-settings') return;
        $this->option = Option::cache(PaymentService::WALLET_OPTION_KEY, $this->option);
    }
    #[Override]
    protected function admin(): void
    {
    }
    #[Override]
    protected function adminMenu(): void
    {
        add_submenu_page(
            'g3-settings',
            __('Wallet', 'G3'),
            __('Wallet', 'G3'),
            'manage_options',
            'wallet-settings',
            [$this, 'render'],
            14
        );
    }
    public function render(): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . __('Wallet', 'G3') . '</h1>';
        $tabs = [
            'general'        => __('General', 'G3'),
            'withdrawal'     => __('Withdrawal', 'G3'),
            'cryptocurrency' => __('Cryptocurrency', 'G3'),
        ];
        Element::tab('Wallet', 'general', $tabs);
        echo '</div>';
    }
    #[Override]
    protected function settings(): void
    {
        add_settings_section(
            'sectionGeneral',
            null,
            '__return_false',
            'wallet-settings&tab=general'
        );
        register_setting(
            'generalSetting',
            PaymentService::WALLET_OPTION_KEY,
        );
        add_settings_field(
            'enable',
            __('Wallet', 'G3'),
            function () {
                echo Element::switch(
                    PaymentService::WALLET_OPTION_KEY,
                    $this->option,
                    'enable',
                    __('Wallet', 'G3'),
                    __('Enable Wallet, if you want balance feature.', 'G3')
                );
            },
            'wallet-settings&tab=general',
            'sectionGeneral',
            ['class' => 'general_enable']
        );
        add_settings_field(
            'recharge',
            __('Recharge', 'G3'),
            function () {
                echo Element::switch(
                    PaymentService::WALLET_OPTION_KEY,
                    $this->option,
                    'recharge',
                    __('Recharge', 'G3'),
                    __('Enable Recharge, if you want users can recharge wallet.', 'G3')
                );
            },
            'wallet-settings&tab=general',
            'sectionGeneral',
            ['class' => 'general_recharge']
        );
    }
}
