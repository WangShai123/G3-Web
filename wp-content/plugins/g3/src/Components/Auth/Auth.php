<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components;
use JEALER\G3\Utilities\Container;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Services\AuthService;
class Auth extends Components {
    public array $option;
    public array $subscribe;
    #[\Override]
    protected function options(): void
    {
        $this->option    = Option::get(AuthService::OPTION_KEY, [
            'wechatQRCode' => '0',
            'wechatClient' => '0',
            'wechatOA'     => '0',
        ]);
        $this->subscribe = Option::get(AuthService::SUBSCRIBE_OPTION_KEY, [
            'wechatOA' => '0',
        ]);
    }
    #[\Override]
    protected function adminOptions(): void
    {
        $this->option    = Option::cache(AuthService::OPTION_KEY, $this->option);
        $this->subscribe = Option::cache(AuthService::SUBSCRIBE_OPTION_KEY, $this->subscribe);
    }
    #[\Override]
    protected function admin(): void
    {
    }
    #[\Override]
    protected function adminMenu(): void
    {
        add_submenu_page(
            'g3-settings',
            __('Login', 'G3'),
            __('Login', 'G3'),
            'manage_options',
            'auth-settings',
            [$this, 'render'],
            2
        );
    }
    public function render(): void
    {
        echo '<div class="wrap"><h1>' . __('Login', 'G3') . '</h1>';
        $args = [
            'general' => __('General', 'G3'),
            'follow'  => __('WeChat Subscription Login', 'G3'),
        ];
        Container::tab('Auth', 'general', $args);
        echo '</div>';
    }
    #[\Override]
    protected function settings(): void
    {
        add_settings_section(
            'general',
            null,
            '__return_false',
            'auth-settings'
        );
        register_setting(
            'general',
            AuthService::OPTION_KEY,
        );
        Container::settingFields('auth-settings', 'general', [
            [
                'id'       => 'wechatQRCode',
                'title'    => __('Login via Wechat QRCode', 'G3'),
                'callback' => function () {
                    echo Container::enable(
                        AuthService::OPTION_KEY,
                        $this->option,
                        'wechatQRCode',
                        __('Login via Wechat QRCode', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'wechatQRCode'
                ]
            ],
            [
                'id'       => 'wechatClient',
                'title'    => __('Login via Wechat Client', 'G3'),
                'callback' => function () {
                    echo Container::enable(
                        AuthService::OPTION_KEY,
                        $this->option,
                        'wechatClient',
                        __('Login via Wechat Client', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'wechatClient'
                ]
            ]
        ]);

        add_settings_section(
            'subscribe',
            null,
            '__return_false',
            'auth-settings&tab=subscribe'
        );
        register_setting(
            'subscribe',
            AuthService::SUBSCRIBE_OPTION_KEY,
        );
        Container::settingFields('auth-settings&tab=subscribe', 'subscribe', [
            [
                'id'       => 'wechatOA',
                'title'    => __('Subscribe Login', 'G3'),
                'callback' => function () {
                    echo Container::enable(
                        AuthService::SUBSCRIBE_OPTION_KEY,
                        $this->subscribe,
                        'wechatOA',
                        __('Subscribe Login', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'wechatOA'
                ]
            ]
        ]);
    }
}