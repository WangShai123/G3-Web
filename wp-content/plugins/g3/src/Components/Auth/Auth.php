<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Services\AuthService;
use JEALER\G3\Utilities\Response;
use Override;

class Auth extends Components {
    public ?array $option = [];
    public ?array $wechat = [];

    protected function options(): void
    {
        $this->option = Option::get(AuthService::OPTION_KEY, [
            'code'        => '0',
            'force'       => '0',
            'expire'      => '7',
            'allowToSale' => '0',
            'payment'     => '1',
            'price'       => '10.00',
        ]);
        $this->wechat = Option::get(AuthService::WECHAT_OPTION_KEY, [
            'subscribe' => '0',
            'client'    => '0',
        ]);
    }
    protected function form(): void
    {
        if (!isset($_REQUEST['page']) || $_REQUEST['page'] !== 'auth-settings') return;
        $this->option = Option::cache(AuthService::OPTION_KEY, $this->option);
        $this->wechat = Option::cache(AuthService::WECHAT_OPTION_KEY, $this->wechat);
    }
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
            'general'    => __('General'),
            'social'     => __('Social Login', 'G3'),
            'invitation' => __('Invitation Code', 'G3'),
        ];
        Element::tab('Auth', 'general', $args);
        echo '</div>';
    }
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
        Element::settingFields('auth-settings', 'general', [
            [
                'id'       => 'code',
                'title'    => __('Registration Code', 'G3'),
                'callback' => function () {
                    echo Element::select(
                        AuthService::OPTION_KEY,
                        $this->option,
                        'code',
                        __('Registration Code', 'G3'),
                        __('Whether to allow to register by registration code, The referral code is used as the user slug.', 'G3'),
                        '',
                        [
                            '0' => __('Disable', 'G3'),
                            '1' => __('Invitation Code', 'G3'),
                            '2' => __('Referral Code', 'G3'),
                        ]
                    );
                },
                'args'     => ['class' => 'advanced']
            ],
            [
                'id'       => 'force',
                'title'    => __('Invite-Only Registration', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        AuthService::OPTION_KEY,
                        $this->option,
                        'force',
                        __('Invite-Only Registration', 'G3'),
                        __('Whether to enforce mandatory invite-only registration, users without an invitation code cannot sign up.', 'G3')
                    );
                },
                'args'     => ['class' => 'advanced']
            ],
            [
                'id'       => 'expire',
                'title'    => __('Expiration'),
                'callback' => function () {
                    echo Element::select(
                        AuthService::OPTION_KEY,
                        $this->option,
                        'expire',
                        __('Expiration'),
                        __('The expiration of the invitation code.', 'G3'),
                        '',
                        [
                            '1'   => '1 ' . __('Day'),
                            '3'   => '3 ' . __('Days', 'G3'),
                            '7'   => '7 ' . __('Days', 'G3'),
                            '15'  => '15 ' . __('Days', 'G3'),
                            '30'  => '30 ' . __('Days', 'G3'),
                            '90'  => '90 ' . __('Days', 'G3'),
                            '180' => '180 ' . __('Days', 'G3'),
                            '365' => '365 ' . __('Days', 'G3'),
                        ]
                    );
                }
            ],
            [
                'id'       => 'allowToSale',
                'title'    => __('onSale', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        AuthService::OPTION_KEY,
                        $this->option,
                        'allowToSale',
                        __('onSale', 'G3'),
                        __('Whether to allow to sale the invitation code.', 'G3')
                    );
                },
                'args'     => ['class' => 'advanced']
            ],
            [
                'id'       => 'payment',
                'title'    => __('Payment Method', 'G3'),
                'callback' => function () {
                    echo Element::select(
                        AuthService::OPTION_KEY,
                        $this->option,
                        'payment',
                        __('Payment Method', 'G3'),
                        __('The payment method used to pay for the invitation code.', 'G3'),
                        '',
                        [
                            '1' => __('Points Pay', 'G3'),
                            '2' => __('Currency Pay', 'G3'),
                        ]
                    );
                },
            ],
            [
                'id'       => 'price',
                'title'    => __('Price', 'G3'),
                'callback' => function () {
                    echo Element::input(
                        AuthService::OPTION_KEY,
                        $this->option,
                        'price',
                        __('Price', 'G3'),
                        __('The price of the invitation code.', 'G3'),
                        'number'
                    );
                },
            ]
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
        Element::settingFields('auth-settings&tab=social', 'social', [
            [
                'id'       => 'subscribe',
                'title'    => __('WeChat Subscribe Login', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        AuthService::WECHAT_OPTION_KEY,
                        $this->wechat,
                        'subscribe',
                        __('WeChat Subscribe Login', 'G3'),
                        __('Users can subscribe your WeChat official account to complete the login.', 'G3')
                    );
                },
                'args'     => ['class' => 'advanced']
            ],
            [
                'id'       => 'client',
                'title'    => __('Login via Wechat Client', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        AuthService::WECHAT_OPTION_KEY,
                        $this->wechat,
                        'client',
                        __('Login via Wechat Client', 'G3'),
                        __('Users can complete the login automatically while browsing your website with Wechat client.', 'G3'),
                        'field-client',
                        'md'
                    );
                }
            ],
            // [
            //     'id'       => 'wechatQRCode',
            //     'title'    => __('Login via Wechat QRCode', 'G3'),
            //     'callback' => function () {
            //         echo Element::enable(
            //             AuthService::OPTION_KEY,
            //             $this->option,
            //             'wechatQRCode',
            //             __('Login via Wechat QRCode', 'G3')
            //         );
            //     }
            // ],
        ]);
    }
    protected function ajax(): void
    {
        add_action('wp_ajax_g3_generate_invite_code', function () {
            if (!current_user_can('manage_options')) {
                Response::ajaxForbidden();
            }

            $data   = $_POST['data'] ?? [];
            $amount = (int) ($data['amount'] ?? 1);

            if ($amount < 1 || $amount > 20) {
                Response::ajaxError(__('Failed. It is recommended to generate 1-20 at a time.', 'G3'));
            }

            /** @var AuthService $service */
            $service = $this->getService(AuthService::class);

            $successCodes = [];
            $failCount    = 0;

            for ($i = 0; $i < $amount; $i++) {
                $code = $service->generateInviteCode(true);
                if ($code !== false) {
                    $successCodes[] = $code;
                } else {
                    $failCount++;
                }
            }

            if (empty($successCodes)) {
                wp_send_json_error([
                    'message'   => __('Failed', 'G3'),
                    'failCount' => $failCount
                ], 500);
            }

            $response = [
                'message'      => sprintf(
                    /* translators: %d: number of successfully generated codes */
                    _n('Successfully generated %d invitation code.', 'Successfully generated %d invitation codes.', count($successCodes), 'G3'),
                    count($successCodes)
                ),
                'codes'        => $successCodes,
                'total'        => $amount,
                'successCount' => count($successCodes),
                'failCount'    => $failCount,
            ];

            if ($failCount > 0) {
                // If some failed but some succeeded, still return success but with warning info
                $response['warning'] = sprintf(
                    /* translators: %d: number of failed attempts */
                    _n('%d code generation failed.', '%d codes generation failed.', $failCount, 'G3'),
                    $failCount
                );
            }

            wp_send_json_success($response);
        });
        add_action('wp_ajax_g3_delete_invite_code', function () {
            if (!current_user_can('manage_options')) {
                Response::ajaxForbidden();
            }
            $data = $_POST['data'] ?? [];
            $id   = $data['id'] ?? '';
            if (!$id) {
                Response::ajaxIllegal();
            }
            /** @var AuthService $service */
            $service = $this->getService(AuthService::class);
            $result  = $service->deleteInviteCode((int) $id);
            if ($result !== false) {
                Response::ajaxDeleted();
            } else {
                Response::ajaxFailed();
            }
        });
    }
}
