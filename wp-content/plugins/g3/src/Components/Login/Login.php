<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components;
use JEALER\G3\Utilities\Container;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Services\AuthService;
class Login extends Components {
    public array $option;
    public array $followOption;

    #[\Override]
    protected function options(): void
    {
        $option       = Option::get(AuthService::OPTION_KEY, [
            'wechatQRCode' => '0',
            'wechatClient' => '0'
        ]);
        $this->option = Option::cache(AuthService::OPTION_KEY, $option);

        $followOption       = Option::get(AuthService::FOLLOW_OPTION_KEY, [
            'wechatMP' => '0',
            'message'  => __('Welcome'),
            'unionId'  => '0'
        ]);
        $this->followOption = Option::cache(AuthService::FOLLOW_OPTION_KEY, $followOption);
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
    private function settings(): void
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
            AuthService::FOLLOW_OPTION_KEY,
        );
        Container::settingFields('login-setting&tab=follow', 'follow', [
            [
                'id'       => 'wechatMP',
                'title'    => __('Follow Login', 'G3'),
                'callback' => function () {
                    echo Container::enable(
                        AuthService::FOLLOW_OPTION_KEY,
                        $this->followOption,
                        'wechatMP',
                        __('Follow Login', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'wechatMP'
                ]
            ],
            [
                'id'       => 'message',
                'title'    => __('Message'),
                'callback' => function () {
                    echo Container::input(
                        AuthService::FOLLOW_OPTION_KEY,
                        $this->followOption,
                        'message',
                        __('Message', 'G3'),
                        //用户首次关注微信公众号来登录时，系统自动发送的消息
                        __('System automatically sends a message to the user when they first follow the WeChat official account to log in.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'message'
                ]
            ],
            [
                'id'       => 'unionId',
                'title'    => __('UnionID'),
                'callback' => function () {
                    echo Container::enable(
                        AuthService::FOLLOW_OPTION_KEY,
                        $this->followOption,
                        'unionId',
                        __('UnionID', 'G3'),
                        //是否存储用户UnionID。UnionID是用户在微信系统中的唯一身份标识，通过它可以准确识别微信用户。
                        __('Whether to store user UnionID. UnionID is the unique identity identifier of a user in the WeChat system, and it can accurately identify WeChat users.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'unionId'
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