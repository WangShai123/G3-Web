<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components;
use JEALER\G3\Utilities\Container;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Services\AuthService;
class Login extends Components {
    public array $option;

    #[\Override]
    protected function options(): void
    {
        $this->option = Option::init(AuthService::OPTION_KEY, [
            'wechatQRCode' => '0',
            'wechatClient' => '0',
            'wechatOA'     => '0',
        ]);
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
            'login-setting',
            [$this, 'render'],
            3
        );
    }
    public function render(): void
    {
        echo '<div class="wrap"><h1>' . __('Login', 'G3') . '</h1>';
        $args = [
            'general' => __('General', 'G3'),
            'follow'  => __('Wechat Follow Login', 'G3'),
        ];
        Container::tab('Login', 'general', $args);
        echo '</div>';
    }
    #[\Override]
    protected function settings(): void
    {
        add_settings_section(
            'general',
            null,
            '__return_false',
            'login-setting'
        );
        register_setting(
            'general',
            AuthService::OPTION_KEY,
        );
        Container::settingFields('login-setting', 'general', [
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
            'follow',
            null,
            '__return_false',
            'login-setting&tab=follow'
        );
        register_setting(
            'follow',
            AuthService::OPTION_KEY,
        );
        Container::settingFields('login-setting&tab=follow', 'follow', [
            [
                'id'       => 'wechatOA',
                'title'    => __('Follow Login', 'G3'),
                'callback' => function () {
                    echo Container::enable(
                        AuthService::OPTION_KEY,
                        $this->option,
                        'wechatOA',
                        __('Follow Login', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'wechatOA'
                ]
            ],
            [
                'id'       => 'QRCode',
                'title'    => __('QRCode'),
                'callback' => function () {
                    echo '@todo';
                },
                'args'     => [
                    'label_for' => 'QRCode'
                ]
            ]
        ]);
    }
}