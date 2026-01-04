<?php
namespace JEALER\G3\Components;

use JEALER\G3\Components;
use JEALER\G3\Utilities\Container;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Services\AuthService;
use Override;

class Auth extends Components {
    public array $option;
    public array $wechat;
    #[Override]
    protected function options(): void
    {
        $this->option = Option::get(AuthService::OPTION_KEY, [

        ]);
        $this->wechat = Option::get(AuthService::WECHAT_OPTION_KEY, [
            'subscribe' => '0',
            'client'    => '0',
        ]);
    }
    #[Override]
    protected function adminOptions(): void
    {
        $this->option = Option::cache(AuthService::OPTION_KEY, $this->option);
        $this->wechat = Option::cache(AuthService::WECHAT_OPTION_KEY, $this->wechat);
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
            'social'  => __('Social Login', 'G3'),
        ];
        Container::tab('Auth', 'general', $args);
        echo '</div>';
    }
    #[Override]
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
        ]);

        add_settings_section(
            'social',
            null,
            '__return_false',
            'auth-settings&tab=social'
        );
        register_setting(
            'social',
            AuthService::WECHAT_OPTION_KEY,
        );
        Container::settingFields('auth-settings&tab=social', 'social', [
            [
                'id'       => 'subscribe',
                'title'    => __('WeChat Subscribe Login', 'G3'),
                'callback' => function () {
                    echo Container::enable(
                        AuthService::WECHAT_OPTION_KEY,
                        $this->wechat,
                        'subscribe',
                        __('WeChat Subscribe Login', 'G3'),
                        __('Users can subscribe your WeChat official account to complete the login.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'subscribe'
                ]
            ],
            [
                'id'       => 'client',
                'title'    => __('Login via Wechat Client', 'G3'),
                'callback' => function () {
                    echo Container::enable(
                        AuthService::WECHAT_OPTION_KEY,
                        $this->wechat,
                        'client',
                        __('Login via Wechat Client', 'G3'),
                        __('Users can complete the login automatically while browsing your website with Wechat client.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'client'
                ]
            ],
            // [
            //     'id'       => 'wechatQRCode',
            //     'title'    => __('Login via Wechat QRCode', 'G3'),
            //     'callback' => function () {
            //         echo Container::enable(
            //             AuthService::OPTION_KEY,
            //             $this->option,
            //             'wechatQRCode',
            //             __('Login via Wechat QRCode', 'G3')
            //         );
            //     },
            //     'args'     => [
            //         'label_for' => 'wechatQRCode'
            //     ]
            // ],
        ]);
    }
}