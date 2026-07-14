<?php
namespace JEALER\G3\Components;

use JEALER\G3\Core\Admin\Panel;
use JEALER\G3\Services\PaymentService;
use Override;

class Wallet extends Components {
    private function optionDefaults(): array
    {
        return [
            'exchange' => '0',
            'enable'   => '0',
            'recharge' => '0',
        ];
    }

    #[Override]
    protected function adminMenu(): void
    {
        add_submenu_page('g3-settings', __('Wallet', 'G3'), __('Wallet', 'G3'), 'manage_options', 'wallet-settings', [$this, 'render'], 14);
    }

    protected function adminPanelPage(): string
    {
        return 'wallet-settings';
    }

    protected function adminPanels(): array
    {
        return [
            $this->panel('wallet-settings', __('Wallet', 'G3'))
                ->tab('general', __('General'))
                ->option(PaymentService::WALLET_OPTION_KEY, $this->optionDefaults())
                ->switch('enable', __('Wallet', 'G3'), __('Enable Wallet, if you want balance feature.', 'G3'))
                ->rowClass('general_enable')
                ->switch('recharge', __('Recharge', 'G3'), __('Enable Recharge, if you want users can recharge wallet.', 'G3'))
                ->rowClass('general_recharge')
                ->tab('credits', __('Credits', 'G3'))
                ->tab('withdrawal', __('Withdrawal', 'G3'))
                ->tab('cryptocurrency', __('Cryptocurrency', 'G3')),
        ];
    }

    public function render(): void
    {
        $this->createPanel();
    }
}
