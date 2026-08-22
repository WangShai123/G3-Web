<?php
namespace JEALER\G3\Components;
use JEALER\G3\Core\Admin\Panel;
use JEALER\G3\Services\PaymentService;
use Override;

class Wallet extends Components {
    protected function defaultOption(): array
    {
        return [
            PaymentService::WALLET_OPTION_KEY => PaymentService::optionDefaults(),
        ];
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
    protected function adminPanelPage(): string
    {
        return 'wallet-settings';
    }
    public function render(): void
    {
        $this->createPanel();
    }
    protected function adminPanels(): array
    {
        return [
            $this->panel('wallet-settings', __('Wallet', 'G3'))
                ->tab('general', __('General'))
                ->option(PaymentService::WALLET_OPTION_KEY, PaymentService::optionDefaults())
                ->switch('enable', __('Wallet', 'G3'))
                ->rowClass('advanced')
                ->switch('recharge', __('Recharge', 'G3'))
                ->rowClass('advanced')
                ->switch('withdrawal', __('Withdrawal', 'G3'))
                ->rowClass('advanced')
                ->number('bank_fee', __('Bank transfer fees', 'G3'))
                ->number('tax_rate', __('Personal income tax rate', 'G3'), __('Default') . ' 20(%)')
                ->tab('credits', __('Credits', 'G3'))
                ->tab('cryptocurrency', __('Cryptocurrency', 'G3')),
        ];
    }


}
