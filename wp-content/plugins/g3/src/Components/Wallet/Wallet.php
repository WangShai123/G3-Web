<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components;
use JEALER\G3\Utilities\Container;
use JEALER\G3\Utilities\Option;
class Wallet extends Components {
    public string $optionKey = 'g3_option_wallet';
    public array $option = [];
    #[\Override]
    protected function options(): void
    {
        $default      = Option::get($this->optionKey, [
            'enable'   => '0',
            'recharge' => '0',
        ]);
        $this->option = Option::cache($this->optionKey, $default);
    }
    #[\Override]
    protected function admin(): void
    {
        $this->settings();
    }
    #[\Override]
    protected function adminMenu(): void
    {
        $this->submenu();
    }
    private function submenu(): void
    {
        add_submenu_page(
            'digital-operations',
            __('Wallet', 'G3'),
            __('Wallet', 'G3'),
            'manage_options',
            'wallet-setting',
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
        Container::tab('Wallet', 'general', $tabs);
        echo '</div>';
    }
    private function settings(): void
    {
        add_settings_section(
            'sectionGeneral',
            null,
            '__return_false',
            'wallet-setting&tab=general'
        );
        register_setting(
            'generalSetting',
            $this->optionKey,
        );
        add_settings_field(
            'enable',
            __('Wallet', 'G3'),
            function () {
                echo Container::enable($this->optionKey, $this->option, 'enable', __('Wallet', 'G3'), __('Enable Wallet, if you want balance feature.', 'G3'));
            },
            'wallet-setting&tab=general',
            'sectionGeneral',
            ['class' => 'general_enable']
        );
        add_settings_field(
            'recharge',
            __('Recharge', 'G3'),
            function () {
                echo Container::enable($this->optionKey, $this->option, 'recharge', __('Recharge', 'G3'), __('Enable Recharge, if you want users can recharge wallet.', 'G3'));
            },
            'wallet-setting&tab=general',
            'sectionGeneral',
            ['class' => 'general_recharge']
        );
    }
}
