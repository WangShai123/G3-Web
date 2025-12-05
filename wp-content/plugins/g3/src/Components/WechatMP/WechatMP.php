<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components;
use JEALER\G3\Services\WechatMPService;
use JEALER\G3\Utilities\Container;
use JEALER\G3\Utilities\Option;
class WechatMP extends Components {
    public array $option = [];

    #[\override]
    protected function options(): void
    {
        $option       = Option::get(WechatMPService::OPTION_KEY, [
            'service'       => '0',
            'search'        => '0',
            'count'         => '5',
            'length'        => '16',
            'cover'         => '',
            'followMessage' => __('Thanks for your attention.', 'G3'),
            'visitMessage'  => __('Welcome back, my friend.', 'G3'),
        ]);
        $this->option = Option::cache(WechatMPService::OPTION_KEY, $option);
    }
    #[\Override]
    protected function admin(): void
    {
        $this->settings();
        $this->wpAjax();
    }
    #[\Override]
    protected function adminMenu(): void
    {
        $this->submenu();
        add_action('admin_head', function () {
            remove_submenu_page('g3-settings', 'wechat-mp-menu-edit');
        });
    }
    private function submenu(): void
    {
        add_submenu_page(
            'g3-settings',
            __('Wechat MP', 'G3'),
            __('Wechat MP', 'G3'),
            'manage_options',
            'wechat-mp',
            [$this, 'render'],
            15
        );
        add_submenu_page(
            'g3-settings',
            __('Edit'),
            __('Edit'),
            'manage_options',
            'wechat-mp-menu-edit',
            function () {
                require_once __DIR__ . '/views/view-menu-edit.php';
            }
        );
    }
    public function render(): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . __('Wechat MP', 'G3') . '</h1>';
        $tabs = [
            'general'  => __('General', 'G3'),
            'menus'    => __('Menus'),
            'custom'   => __('Custom Reply', 'G3'),
            'advanced' => __('Advanced Replay', 'G3'),
        ];
        Container::tab('WechatMP', 'general', $tabs);
        echo '</div>';
    }
    private function settings(): void
    {
        add_settings_section(
            'wechatMP',
            null,
            '__return_false',
            'wechat-mp'
        );
        register_setting('wechatMP', WechatMPService::OPTION_KEY);
        Container::settingFields('wechat-mp', 'wechatMP', [
            [
                'id'       => 'service',
                'title'    => __('Wechat MP Service', 'G3'),
                'callback' => function () {
                    echo Container::enable(
                        WechatMPService::OPTION_KEY,
                        $this->option,
                        'service',
                        __('Wechat MP Service', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'service',
                ]
            ],
            [
                'id'       => 'search',
                'title'    => __('Search'),
                'callback' => function () {
                    echo Container::enable(
                        WechatMPService::OPTION_KEY,
                        $this->option,
                        'search',
                        __('Search', 'G3'),
                        // 启用搜索后，用户在微信公众号里发送的信息，如果在自定义应答之外，系统会自动搜索并返回网站的内容。
                        __('After activating the search, users who send messages to the WeChat MP will automatically search for the content on the website and return the content.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'search',
                ]
            ],
            [
                'id'       => 'count',
                'title'    => __('Count in Search Result', 'G3'),
                'callback' => function () {
                    echo Container::select(
                        WechatMPService::OPTION_KEY,
                        $this->option,
                        'count',
                        __('Count in Search Result', 'G3'),
                        __('The number of search results returned. Default: 5.', 'G3'),
                        '',
                        [
                            '1' => '1',
                            '2' => '2',
                            '3' => '3',
                            '4' => '4',
                            '5' => '5',
                            '6' => '6',
                            '7' => '7',
                            '8' => '8',
                        ]
                    );
                },
                'args'     => [
                    'label_for' => 'count',
                ]
            ],
            [
                'id'       => 'length',
                'title'    => __('Max Length of Search Keyword', 'G3'),
                'callback' => function () {
                    echo Container::input(
                        WechatMPService::OPTION_KEY,
                        $this->option,
                        'length',
                        __('Max Length of Search Keyword', 'G3'),
                        __('The maximum length of the search keyword. Default: 16.', 'G3'),
                        'number',
                    );
                },
                'args'     => [
                    'label_for' => 'length',
                ]
            ],
            [
                'id'       => 'cover',
                'title'    => __('Default Cover', 'G3'),
                'callback' => function () {
                    echo Container::imageInput(
                        WechatMPService::OPTION_KEY,
                        $this->option,
                        'cover',
                        __('Default Cover', 'G3'),
                        __('The default cover image for search results.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'cover',
                ]
            ],
            [
                'id'       => 'followMessage',
                'title'    => __('Follow Message', 'G3'),
                'callback' => function () {
                    echo Container::textarea(
                        WechatMPService::OPTION_KEY,
                        $this->option,
                        'followMessage',
                        __('Follow Message', 'G3'),
                        __('The message sent to users when they follow the WeChat MP.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'followMessage',
                ]
            ],
            [
                'id'       => 'visitMessage',
                'title'    => __('Visit Message', 'G3'),
                'callback' => function () {
                    echo Container::textarea(
                        WechatMPService::OPTION_KEY,
                        $this->option,
                        'visitMessage',
                        __('Visit Message', 'G3'),
                        __('The message sent to users when they visit the WeChat MP.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'visitMessage',
                ]
            ]
        ]);
    }
    private function wpAjax(): void
    {
        // delete menu
        add_action('wp_ajax_g3_delete_wechatMP_menu', function () {
            $id = $_POST['id'] ?? 0;
            if (!current_user_can('manage_options') || !$id || !is_admin()) {
                wp_send_json_error([
                    'message' => __('Forbidden', 'G3')
                ], 400);
            }

            $result = WechatMPService::deleteMenu($id);
            if (!$result) {
                wp_send_json_error([
                    'message' => __('Failed', 'G3')
                ]);
            }
            wp_send_json_success([
                'message' => __('Success', 'G3')
            ]);
        });

        // edit menu
        add_action('wp_ajax_g3_edit_wechatMP_menu', function () {
            $id     = $_POST['id'] ?? 0;
            $name   = $_POST['name'] ?? '';
            $parent = $_POST['parent'] ?? 0;
            $sort   = $_POST['sort'] ?? 1;
            $type   = $_POST['type'] ?? 1;
            $value  = $_POST['value'] ?? '';

            if (!current_user_can('manage_options') || empty($name) || empty($value) || !is_admin()) {
                wp_send_json_error([
                    'message' => __('Forbidden', 'G3')
                ], 400);
            }

            $data   = [
                'name'   => $name,
                'parent' => $parent,
                'sort'   => $sort,
                'type'   => $type,
                'value'  => $value
            ];
            $result = WechatMPService::updateMenu($id, $data);
            if (!$result) {
                wp_send_json_error([
                    'message' => __('Failed', 'G3')
                ]);
            }
            wp_send_json_success([
                'message' => __('Success', 'G3')
            ]);
        });
    }
}