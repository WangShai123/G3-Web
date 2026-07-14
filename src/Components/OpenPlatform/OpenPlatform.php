<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use JEALER\G3\Core\Admin\Panel;
use JEALER\G3\Services\SystemService;
use Override;

class OpenPlatform extends Components {
    private function wechatDefaults(): array
    {
        return [
            'type'           => '0',
            'slug'           => '',
            'appId'          => '',
            'appSecret'      => '',
            'token'          => '',
            'encodingAESKey' => '',
        ];
    }
    #[Override]
    protected function adminMenu(): void
    {
        add_submenu_page(
            'g3-settings',
            __('Open Platform', 'G3'),
            __('Open Platform', 'G3'),
            'manage_options',
            'open-platform',
            [$this, 'render'],
            19
        );
    }
    protected function adminPanelPage(): string
    {
        return 'open-platform';
    }
    public function render(): void
    {
        $this->createPanel('wechatOA');
    }
    protected function adminPanels(): array
    {
        return [
            $this->panel('open-platform', __('Open Platform', 'G3'))
                ->tab('wechatOA', __('Wechat OA', 'G3'))
                ->option(SystemService::OPEN_WECHAT_OA_KEY, $this->wechatDefaults())
                ->select('type', __('Official Account Type', 'G3'), [
                    ''  => __('Please Select', 'G3'),
                    '1' => __('Service Account', 'G3'),
                    '2' => __('Subscription Account', 'G3'),
                    '3' => __('Verified Service Account', 'G3'),
                    '4' => __('Verified Subscription Account', 'G3')
                ])
                ->input('slug', __('Wechat ID', 'G3'))
                ->input('appId', 'App ID')
                ->password('appSecret', 'App Secret')
                ->input('token', 'Token')
                ->input('encodingAESKey', 'Encoding AES Key')
                ->callback('url', 'URL', function () {
                    return site_url('/dev/wechat_oa/callback');
                })
        ];
    }
}
