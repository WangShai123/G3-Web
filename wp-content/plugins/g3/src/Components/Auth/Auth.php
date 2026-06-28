<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use JEALER\G3\Core\Admin\Panel;
use JEALER\G3\Core\Admin\PanelRenderer;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Services\AuthService;
use JEALER\G3\Utilities\Response;
use Override;

class Auth extends Components {
    public ?array $option     = [];
    public ?array $wechat     = [];
    public ?array $invitation = [];
    protected function state(): array
    {
        return [
            'option'     => $this->optionState(AuthService::OPTION_KEY, [
                'code'        => '0',
                'force'       => '0',
                'expire'      => '7',
                'allowToSale' => '0',
                'payment'     => '1',
                'price'       => '10.00',
            ]),
            'wechat'     => $this->optionState(AuthService::WECHAT_OPTION_KEY, [
                'subscribe' => '0',
                'client'    => '0',
            ]),
            'invitation' => $this->memoryState()
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
            [$this, 'render'],
            2
        );
    }
    protected function adminPanelPage(): string
    {
        return 'auth-settings';
    }
    public function render()
    {
        $firstPanel = $this->firstPanel();
        if (!$firstPanel instanceof Panel) {
            return;
        }
        $this->createPanel()->render($this, $firstPanel, 'general');
    }
    protected function adminPanels(): array
    {
        return [
            $this->panel('auth-settings', __('Login', 'G3'))
                ->tab('general', __('General'), 'option')
                ->select('code', __('Registration Code', 'G3'), [
                    '0' => __('Disable', 'G3'),
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
                ->tab('social', __('Social Login', 'G3'), 'wechat')
                ->switch('subscribe', __('WeChat Subscribe Login', 'G3'), __('Users can subscribe your WeChat official account to complete the login.', 'G3'))
                ->rowClass('advanced')
                ->switch('client', __('Login via Wechat Client', 'G3'), __('Users can complete the login automatically while browsing your website with Wechat client.', 'G3'))
                ->rowClass('advanced')
                ->tab('invitation', __('Invitation Code', 'G3'), 'invitation')
        ];
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
            if ($this->container->has('authService')) {
                $service = $this->container->get('authService');
            } else {
                $this->container->setRawDefinition('authService', AuthService::class);
                $service = $this->container->get('authService');
            }

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
