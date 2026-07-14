<?php
namespace JEALER\G3\Components;
use JEALER\G3\Core\Admin\Panel;
use JEALER\G3\Services\ShareService;
use Override;

class Share extends Components {
    private function optionDefaults(): array
    {
        return [
            'enable'             => '1',
            'poster'             => '0',
            'wechatTitle'        => '0',
            'wechatMediaLibrary' => '0',
            'weiBo'              => '0',
            'qqZone'             => '0',
            'douYin'             => '0',
        ];
    }

    private function option(): array
    {
        $defaults = $this->optionDefaults();
        $option   = get_option(ShareService::OPTION_KEY, $defaults);
        return is_array($option) ? $option : $defaults;
    }

    #[Override]
    protected function admin(): void
    {
        add_action('save_post', [$this, 'saveShareMeta']);
    }

    #[Override]
    protected function adminMenu(): void
    {
        add_submenu_page(
            'g3-settings',
            __('Share', 'G3'),
            __('Share', 'G3'),
            'manage_options',
            'share-settings',
            [$this, 'render'],
            4
        );
    }

    protected function adminPanelPage(): string
    {
        return 'share-settings';
    }

    protected function adminPanels(): array
    {
        return [
            $this->panel('share-settings', __('Share', 'G3'))
                ->tab('general', __('General'))
                ->option(ShareService::OPTION_KEY, $this->optionDefaults())
                ->switch('enable', __('Content Distribution', 'G3'), __('Whether to display the content distribution section on the post edit page.', 'G3'))
                ->switch('poster', __('Share via Poster', 'G3'))
                ->switch('wechatTitle', __('WeChat Title', 'G3'), __('Customize the title that will be displayed when shared on WeChat.', 'G3'))
                ->switch('wechatMediaLibrary', __('Wechat OA Media Library', 'G3'), __('When publishing post, the content will be automatically synchronized to the WeChat official account media library.', 'G3'))
                ->switch('qqZone', __('Share to QQ Zone', 'G3'))
                ->switch('douYin', __('Share to DouYin', 'G3'))
                ->tab('account', __('Social Accounts', 'G3')),
        ];
    }

    public function render(): void
    {
        $this->createPanel();
    }

    public static function metaBoxRender(): void
    {
        @require_once __DIR__ . '/views/metabox-share.php';
    }

    #[Override]
    protected function metaBox(): void
    {
        if (($this->option()['wechatTitle'] ?? '0') !== '1') {
            return;
        }

        add_action('g3_action_seo', [$this, 'addWechatTitleField']);
    }

    public function addWechatTitleField($post): void
    {
        $label    = __('WeChat Title', 'G3');
        $value    = get_post_meta($post->ID, ShareService::WECHAT_TITLE_KEY, true);
        $des      = __('Customize the title that will be displayed when shared on WeChat.', 'G3') . ' ' . __('If the title is empty, the title of the post will be used.', 'G3');
        $template = <<<HTML
<div class="grid-container grid-col-1">
    <div class="input-group">
        <div class="el-addon"><div class="is-text">$label</div></div>
        <input class="j-input" type="text" id="wechat-title" name="wechatTitle" value="$value">
    </div>
</div>
<div class="g3-metabox-description">$des</div>
HTML;
        echo $template;
    }

    public function saveShareMeta($postId): void
    {
        $this->saveWechatTitle($postId);
    }

    private function saveWechatTitle($postId): void
    {
        if (!isset($_POST['wechatTitle']) || ($this->option()['wechatTitle'] ?? '0') !== '1') {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $value = empty($_POST['wechatTitle']) ? sanitize_text_field($_POST['post_title']) : sanitize_text_field($_POST['wechatTitle']);
        update_post_meta($postId, ShareService::WECHAT_TITLE_KEY, $value);
    }
}
