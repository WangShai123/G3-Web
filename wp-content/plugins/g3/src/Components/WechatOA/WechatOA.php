<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components;
use JEALER\G3\Services\WechatOAService;
use JEALER\G3\Utilities\Container;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Utilities\Request;
class WechatOA extends Components {
    public array $option = [];

    #[\override]
    protected function options(): void
    {
        $option       = Option::get(WechatOAService::OPTION_KEY, [
            'service'       => '0',
            'search'        => '0',
            'storeMessages' => '0',
            'count'         => '5',
            'length'        => '16',
            'cover'         => '',
            'followMessage' => __('Thanks for your attention.', 'G3'),
            'visitMessage'  => __('Welcome back, my friend.', 'G3'),
        ]);
        $this->option = Option::cache(WechatOAService::OPTION_KEY, $option);
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
            remove_submenu_page('g3-settings', 'wechat-oa-menu-edit');
        });
    }
    private function submenu(): void
    {
        add_submenu_page(
            'g3-settings',
            __('Wechat Official Account', 'G3'),
            __('Wechat OA', 'G3'),
            'manage_options',
            'wechat-oa',
            [$this, 'render'],
            15
        );
        add_submenu_page(
            'g3-settings',
            __('Edit'),
            __('Edit'),
            'manage_options',
            'wechat-oa-menu-edit',
            function () {
                require_once __DIR__ . '/views/view-menu-edit.php';
            }
        );
    }
    public function render(): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . __('Wechat Official Account', 'G3') . '</h1>';
        $tabs = [
            'general'  => __('General', 'G3'),
            'menus'    => __('Menus'),
            'message'  => __('Messages', 'G3'),
            'custom'   => __('Custom Reply', 'G3'),
            'advanced' => __('Advanced Replay', 'G3'),
        ];
        Container::tab('WechatOA', 'general', $tabs);
        echo '</div>';
    }
    private function settings(): void
    {
        add_settings_section(
            'wechatOA',
            null,
            '__return_false',
            'wechat-oa'
        );
        register_setting('wechatOA', WechatOAService::OPTION_KEY);
        Container::settingFields('wechat-oa', 'wechatOA', [
            [
                'id'       => 'service',
                'title'    => __('Wechat OA Service', 'G3'),
                'callback' => function () {
                    echo Container::enable(
                        WechatOAService::OPTION_KEY,
                        $this->option,
                        'service',
                        __('Wechat OA Service', 'G3')
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
                        WechatOAService::OPTION_KEY,
                        $this->option,
                        'search',
                        __('Search', 'G3'),
                        __('After activating it, users who send messages to the WeChat Official Account will automatically search for the content on the website and return the content.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'search',
                ]
            ],
            [
                'id'       => 'storeMessages',
                'title'    => __('Store Messages', 'G3'),
                'callback' => function () {
                    echo Container::enable(
                        WechatOAService::OPTION_KEY,
                        $this->option,
                        'storeMessages',
                        __('Store Messages', 'G3'),
                        __('After activating it, the messages sent to the WeChat Official Account will be stored in the database.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'storeMessages',
                ]
            ],
            [
                'id'       => 'count',
                'title'    => __('Count in Search Result', 'G3'),
                'callback' => function () {
                    echo Container::select(
                        WechatOAService::OPTION_KEY,
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
                        WechatOAService::OPTION_KEY,
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
                        WechatOAService::OPTION_KEY,
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
                        WechatOAService::OPTION_KEY,
                        $this->option,
                        'followMessage',
                        __('Follow Message', 'G3'),
                        __('The message sent to users when they follow the WeChat Official Account.', 'G3')
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
                        WechatOAService::OPTION_KEY,
                        $this->option,
                        'visitMessage',
                        __('Visit Message', 'G3'),
                        __('The message sent to users when they visit the WeChat Official Account.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'visitMessage',
                ]
            ]
        ]);
    }
    private function serviceAvailable(): bool
    {
        return $this->option['service'] ? true : false;
    }
    private function checkServiceInAjax(): void
    {
        if (!$this->serviceAvailable()) {
            Request::ajaxError(__('Wechat Official Account service is not enabled', 'G3'));
        }
    }
    private function wpAjax(): void
    {
        // delete menu
        add_action('wp_ajax_g3_delete_wechatOA_menu', function () {
            $this->checkServiceInAjax();
            $id = $_POST['id'] ?? 0;
            if (!current_user_can('manage_options') || !$id || !is_admin()) {
                Request::ajaxError(__('Forbidden', 'G3'));
            }
            $result = WechatOAService::deleteMenu($id);
            if (!$result) {
                Request::ajaxError(__('Failed', 'G3'));
            }
            Request::ajaxSuccess(__('Success', 'G3'));
        });

        // edit menu
        add_action('wp_ajax_g3_edit_wechatOA_menu', function () {
            $this->checkServiceInAjax();

            $id     = $_POST['id'] ?? 0;
            $name   = $_POST['name'] ?? '';
            $parent = $_POST['parent'] ?? 0;
            $sort   = $_POST['sort'] ?? 1;
            $type   = $_POST['type'] ?? 1;
            $value  = $_POST['value'] ?? '';

            if (!current_user_can('manage_options') || empty($name) || empty($value) || !is_admin()) {
                Request::ajaxForbidden();
            }

            $data   = [
                'name'   => $name,
                'parent' => $parent,
                'sort'   => $sort,
                'type'   => $type,
                'value'  => $value
            ];
            $result = WechatOAService::updateMenu($id, $data);
            if (!$result) {
                Request::ajaxFailed();
            }
            Request::ajaxUpdated();
        });

        add_action('wp_ajax_g3_create_wechatOA_menus', function () {
            $this->checkServiceInAjax();
            if (!is_admin() || !current_user_can('manage_options')) {
                Request::ajaxForbidden();
            }
            if (!wp_verify_nonce($_POST['nonce'], 'g3_create_wechatOA_menus')) {
                Request::ajaxForbidden();
            }

            $service = WechatOAService::run();
            $service->deleteMenus();
            $result = $service->createMenus();

            if ($result) {
                Request::ajaxUpdated();
            } else {
                Request::ajaxFailed();
            }
        });
        add_action('wp_ajax_g3_flush_wechatOA_menus', function () {
            $this->checkServiceInAjax();
            if (!is_admin() || !current_user_can('manage_options')) {
                Request::ajaxForbidden();
            }
            if (!wp_verify_nonce($_POST['nonce'], 'g3_flush_wechatOA_menus')) {
                Request::ajaxForbidden();
            }
            $result = WechatOAService::run()->deleteMenus();
            if ($result) {
                Request::ajaxSuccess(__('Data Flush Complete', 'G3'));
            } else {
                Request::ajaxFailed();
            }
        });
    }
}