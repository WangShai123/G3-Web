<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components;
use JEALER\G3\Utilities\Container;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Services\ShareService;
class Share extends Components {
    public array $option;
    public array $accountOption;
    #[\Override]
    protected function options(): void
    {
        $option       = Option::get(ShareService::OPTION_KEY, [
            'enable'             => '1',
            'poster'             => '0',
            'wechatTitle'        => '0',
            'wechatMediaLibrary' => '0',
            'weiBo'              => '0',
            'qqZone'             => '0',
            'douYin'             => '0'
        ]);
        $this->option = Option::cache(ShareService::OPTION_KEY, $option);

        $accountOption       = Option::get(ShareService::ACCOUNT_OPTION_KEY, [
            'wechat'         => '',
            'wechatQRCode'   => '',
            'wechatOA'       => '',
            'wechatOAQRCode' => '',
        ]);
        $this->accountOption = Option::cache(ShareService::ACCOUNT_OPTION_KEY, $accountOption);
    }

    #[\Override]
    protected function admin(): void
    {
        $this->saveWechatTitle();
    }
    #[\Override()]
    protected function adminMenu(): void
    {
        add_submenu_page(
            'g3-settings',
            __('Share', 'G3'),
            __('Share', 'G3'),
            'manage_options',
            'share-setting',
            [$this, 'render'],
            4
        );
    }
    public function render(): void
    {
        echo '<div class="wrap"><h1>' . __('Share', 'G3') . '</h1>';
        $args = [
            'general' => __('General', 'G3'),
            'account' => __('Social Accounts', 'G3'),
        ];
        Container::tab('Share', 'general', $args);
        echo '</div>';
    }
    #[\Override]
    public function settings(): void
    {
        add_settings_section(
            'general',
            null,
            '__return_false',
            'share-setting&tab=general'
        );
        register_setting(
            'general',
            ShareService::OPTION_KEY,
        );
        Container::settingFields('share-setting&tab=general', 'general', [
            [
                'id'       => 'enable',
                'title'    => __('Content Distribution', 'G3'),
                'callback' => function () {
                    echo Container::enable(
                        ShareService::OPTION_KEY,
                        $this->option,
                        'enable',
                        __('Content Distribution', 'G3'),
                        __('Whether to display the content distribution section on the post edit page.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'enable'
                ]
            ],
            [
                'id'       => 'poster',
                'title'    => __('Share via Poster', 'G3'),
                'callback' => function () {
                    echo Container::enable(
                        ShareService::OPTION_KEY,
                        $this->option,
                        'poster',
                        __('Share via Poster', 'G3'),
                    );
                },
                'args'     => [
                    'label_for' => 'poster'
                ]
            ],
            [
                'id'       => 'wechatTitle',
                'title'    => __('WeChat Title', 'G3'),
                'callback' => function () {
                    echo Container::enable(
                        ShareService::OPTION_KEY,
                        $this->option,
                        'wechatTitle',
                        __('WeChat Title', 'G3'),
                        __('Customize the title that will be displayed when shared on WeChat.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'wechatTitle'
                ]
            ],
            [
                'id'       => 'wechatMediaLibrary',
                'title'    => __('Wechat OA Media Library', 'G3'),
                'callback' => function () {
                    echo Container::enable(
                        ShareService::OPTION_KEY,
                        $this->option,
                        'wechatMediaLibrary',
                        __('Wechat OA Media Library', 'G3'),
                        __('When publishing post, the content will be automatically synchronized to the WeChat official account media library.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'wechatMediaLibrary'
                ]
            ],
            // [
            //     'id'       => 'weiBo',
            //     'title'    => __('Share to WeiBo', 'G3'),
            //     'callback' => function () {
            //         echo Container::enable(
            //             ShareService::OPTION_KEY,
            //             $this->option,
            //             'weiBo',
            //             __('Share to WeiBo', 'G3'),
            //         );
            //     },
            //     'args'     => [
            //         'label_for' => 'weiBo'
            //     ]
            // ],
            [
                'id'       => 'qqZone',
                'title'    => __('Share to QQ Zone', 'G3'),
                'callback' => function () {
                    echo Container::enable(
                        ShareService::OPTION_KEY,
                        $this->option,
                        'qqZone',
                        __('Share to QQ Zone', 'G3'),
                    );
                },
                'args'     => [
                    'label_for' => 'qqZone'
                ]
            ],
            [
                'id'       => 'douYin',
                'title'    => __('Share to DouYin', 'G3'),
                'callback' => function () {
                    echo Container::enable(
                        ShareService::OPTION_KEY,
                        $this->option,
                        'douYin',
                        __('Share to DouYin', 'G3'),
                    );
                },
                'args'     => [
                    'label_for' => 'douYin'
                ]
            ]
        ]);
    }

    public static function metaBoxRender(): void
    {
        @require_once __DIR__ . '/views/metabox-share.php';
    }

    #[\Override]
    protected function metaBox(): void
    {
        if (!isset($this->option['wechatTitle']) || $this->option['wechatTitle'] !== '1') return;

        add_meta_box(
            'wechatTitle',
            __('WeChat Title', 'G3'),
            [$this, 'wechatTitleRender'],
            'post',
            'normal',
            'default'
        );
    }
    public function wechatTitleRender(): void
    {
        $label    = __('WeChat Title', 'G3');
        $value    = get_post_meta(get_the_ID(), ShareService::WECHAT_TITLE_KEY, true);
        $des      = __('Customize the title that will be displayed when shared on WeChat.', 'G3') . ' ' .
            __('If the title is empty, the title of the post will be used.', 'G3');
        $template = <<<HTML
<div class="j-input-group">
    <div class="input-group-item views-data">
        <input type="text" id="wechat-title" name="wechatTitle" value="$value">
    </div>
</div>
<div class="g3-metabox-description">$des</div>
HTML;
        echo $template;
    }
    private function saveWechatTitle(): void
    {
        if (!isset($_POST['wechatTitle']) || !isset($this->option['wechatTitle']) || $this->option['wechatTitle'] !== '1') return;

        add_action('save_post', function ($postId) {
            $value = empty($_POST['wechatTitle']) ? sanitize_text_field($_POST['post_title']) : sanitize_text_field($_POST['wechatTitle']);
            update_post_meta($postId, ShareService::WECHAT_TITLE_KEY, $value);
        });
    }
}