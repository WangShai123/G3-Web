<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use JEALER\G3\Core\Admin\Panel;
use JEALER\G3\Core\Admin\PanelRenderer;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Message;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Services\AuthService;
use JEALER\G3\Utilities\Response;
use Override;

class Auth extends Components {
    private function optionDefaults(): array
    {
        return [
            'code'        => '0',
            'override'    => '0',
            'force'       => '0',
            'expire'      => '7',
            'allowToSale' => '0',
            'payment'     => '1',
            'price'       => '10.00',
        ];
    }

    private function wechatDefaults(): array
    {
        return [
            'subscribe' => '0',
            'client'    => '0',
        ];
    }
    protected function adminMenu()
    {
        add_submenu_page(
            'g3-settings',
            __('Login', 'G3'),
            __('Login', 'G3'),
            'manage_options',
            'auth-settings',
            // [$this, 'render'],
            fn() => $this->createPanel(),
            2
        );
    }
    protected function hooks(): void
    {
        $this->filter([
            'login_url'        => [[$this, 'loginUrl'], 10, 3],
            'lostpassword_url' => [[$this, 'lostPasswordUrl'], 10, 2],
            'register_url'     => [[$this, 'registerUrl'], 10, 1],
        ]);
    }
    protected function adminPanelPage(): string
    {
        return 'auth-settings';
    }
    public function render()
    {
        $this->createPanel();
    }
    protected function adminPanels(): array
    {
        return [
            $this->panel('auth-settings', __('Login', 'G3'))
                ->tab('general', __('General'))
                ->option(AuthService::OPTION_KEY, $this->optionDefaults())
                ->switch('override', __('Auth Route Override', 'G3'), __('Replace WordPress native login, registration and password reset URLs with the custom auth pages.', 'G3'))
                ->select('code', __('Registration Code', 'G3'), [
                    '0' => __('Disabled'),
                    '1' => __('Invitation Code', 'G3'),
                    '2' => __('Referral Code', 'G3'),
                ], __('Whether to allow to register by registration code, The referral code is used as the user slug.', 'G3'))
                ->rowClass('advanced')
                ->switch('force', __('Invite-Only Registration', 'G3'), __('Whether to enforce mandatory invite-only registration, users without an invitation code cannot sign up.', 'G3'))
                ->rowClass('advanced')
                ->select('expire', __('Expiration'), [
                    '1'   => '1 ' . __('Day'),
                    '3'   => '3 ' . __('Days', 'G3'),
                    '7'   => '7 ' . __('Days', 'G3'),
                    '15'  => '15 ' . __('Days', 'G3'),
                    '30'  => '30 ' . __('Days', 'G3'),
                    '90'  => '90 ' . __('Days', 'G3'),
                    '180' => '180 ' . __('Days', 'G3'),
                    '365' => '365 ' . __('Days', 'G3'),
                ], __('The expiration of the invitation code.', 'G3'))
                ->switch('allowToSale', __('onSale', 'G3'), __('Whether to allow to sale the invitation code.', 'G3'))
                ->rowClass('advanced')
                ->select('payment', __('Payment Method', 'G3'), [
                    '1' => __('Points Pay', 'G3'),
                    '2' => __('Currency Pay', 'G3'),
                ], __('The payment method used to pay for the invitation code.', 'G3'))
                ->input('price', __('Price', 'G3'), __('The price of the invitation code.', 'G3'))
                ->tab('social', __('Social Login', 'G3'))
                ->option(AuthService::WECHAT_OPTION_KEY, $this->wechatDefaults())
                ->switch('subscribe', __('WeChat Subscribe Login', 'G3'), __('Users can subscribe your WeChat official account to complete the login.', 'G3'))
                ->rowClass('advanced')
                ->switch('client', __('Login via Wechat Client', 'G3'), __('Users can complete the login automatically while browsing your website with Wechat client.', 'G3'))
                ->rowClass('advanced')
                ->tab('invitation', __('Invitation Code', 'G3'))
        ];
    }
    public static function onAuthOverride(): bool
    {
        $option = self::optionData();
        return ($option['override'] ?? '0') === '1';
    }
    public function loginUrl(string $loginUrl, string $redirect = '', bool $forceReauth = false): string
    {
        if (!self::onAuthOverride()) {
            return $loginUrl;
        }

        $args = [];
        if ($redirect !== '') {
            $args['redirect_to'] = $redirect;
        }
        if ($forceReauth) {
            $args['reauth'] = '1';
        }

        return add_query_arg($args, home_url('/user/login/'));
    }
    public function lostPasswordUrl(string $lostPasswordUrl, string $redirect = ''): string
    {
        if (!self::onAuthOverride()) {
            return $lostPasswordUrl;
        }

        $args = [];
        if ($redirect !== '') {
            $args['redirect_to'] = $redirect;
        }

        return add_query_arg($args, home_url('/user/lost-password/'));
    }
    public function registerUrl(string $registerUrl): string
    {
        return self::onAuthOverride() ? home_url('/user/register/') : $registerUrl;
    }
    private static function optionData(): array
    {
        $option = get_option(AuthService::OPTION_KEY, []);
        return is_array($option) ? $option : [];
    }
    protected function ajax()
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
            $service = $this->container->get(AuthService::class);

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
                'message'      => Message::generated() . ': ' . count($successCodes) . ' ' . __('Invitation Code', 'G3'),
                'codes'        => $successCodes,
                'total'        => $amount,
                'successCount' => count($successCodes),
                'failCount'    => $failCount,
            ];

            if ($failCount > 0) {
                // If some failed but some succeeded, still return success but with warning info
                $response['warning'] = sprintf(
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
