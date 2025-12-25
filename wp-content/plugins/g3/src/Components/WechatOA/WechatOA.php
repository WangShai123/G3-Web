<?php
namespace JEALER\G3\Components;

use JEALER\G3\Components;
use JEALER\G3\Services\WechatOAService;
use JEALER\G3\Utilities\Container;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Utilities\Response;

class WechatOA extends Components {
    public array $option = [];

    #[\override]
    protected function options(): void
    {
        $option       = Option::get(WechatOAService::OPTION_KEY, [
            'service'        => '0',
            'search'         => '0',
            'storeMessages'  => '0',
            'count'          => '5',
            'length'         => '16',
            'followMessage'  => __('Welcome! Thanks for your attention.', 'G3'),
            'visitMessage'   => __('Welcome back, my friend.', 'G3'),
            'defaultMessage' => __('Message received, thanks for your advice!', 'G3'),
            'lastestPosts'   => 'n'
        ]);
        $this->option = Option::cache(WechatOAService::OPTION_KEY, $option);
    }
    #[\Override]
    protected function admin(): void
    {
        $this->wpAjax();
    }
    #[\Override]
    protected function adminMenu(): void
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
        add_action('admin_head', function () {
            remove_submenu_page('g3-settings', 'wechat-oa-menu-edit');
        });
    }
    public function render(): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . __('Wechat Official Account', 'G3') . '</h1>';
        $tabs = [
            'general' => __('General', 'G3'),
            'menus'   => __('Menus'),
            'message' => __('Messages', 'G3'),
            'reply'   => __('Custom Reply', 'G3'),
            'event'   => __('Event Replay', 'G3'),
        ];
        Container::tab('WechatOA', 'general', $tabs);
        echo '</div>';
    }
    #[\Override]
    protected function settings(): void
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
                'title'    => __('News Count', 'G3'),
                'callback' => function () {
                    echo Container::select(
                        WechatOAService::OPTION_KEY,
                        $this->option,
                        'count',
                        __('News Count', 'G3'),
                        __('The maximum number of news items returned in the Key-Event message. Default: 5.', 'G3'),
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
                'id'       => 'defaultMessage',
                'title'    => __('Default Message', 'G3'),
                'callback' => function () {
                    echo Container::textarea(
                        WechatOAService::OPTION_KEY,
                        $this->option,
                        'defaultMessage',
                        __('Default Message', 'G3'),
                        __('The default message replied to users when the search is disabled and no message is found.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'defaultMessage',
                ]
            ],
            // [
            //     'id'       => 'visitMessage',
            //     'title'    => __('Visit Message', 'G3'),
            //     'callback' => function () {
            //         echo Container::textarea(
            //             WechatOAService::OPTION_KEY,
            //             $this->option,
            //             'visitMessage',
            //             __('Visit Message', 'G3'),
            //             __('The message sent to users when they visit the WeChat Official Account.', 'G3')
            //         );
            //     },
            //     'args'     => [
            //         'label_for' => 'visitMessage',
            //     ]
            // ],
        ]);

        add_settings_section(
            'eventReply',
            null,
            '__return_false',
            'wechat-oa&tab=event'
        );
        register_setting('eventReply', WechatOAService::OPTION_KEY);
        Container::settingFields('wechat-oa&tab=event', 'eventReply', [
            [
                'id'       => 'lastestPosts',
                'title'    => __('Latest Posts', 'G3'),
                'callback' => function () {
                    echo Container::input(
                        WechatOAService::OPTION_KEY,
                        $this->option,
                        'lastestPosts',
                        __('Latest Posts', 'G3'),
                        __('The Key that will call the latest posts. Default: n', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'lastestPosts',
                ]
            ],
        ]);
    }
    private function serviceAvailable(): bool
    {
        return $this->option['service'] ? true : false;
    }
    private function checkServiceInAjax(): void
    {
        if (!$this->serviceAvailable()) {
            Response::ajaxError(__('Wechat Official Account service is not enabled', 'G3'));
        }
    }
    private function wpAjax(): void
    {
        // delete menu
        add_action('wp_ajax_g3_delete_wechatOA_menu', function () {
            $this->checkServiceInAjax();
            $id = $_POST['id'] ?? 0;
            if (!current_user_can('manage_options') || !$id || !is_admin()) {
                Response::ajaxForbidden();
            }
            $result = WechatOAService::deleteMenu($id);
            if (!$result) {
                Response::ajaxFailed();
            }
            Response::ajaxDeleted();
        });

        // edit menu
        add_action('wp_ajax_g3_edit_wechatOA_menu', function () {
            $this->checkServiceInAjax();

            $id     = (int) $_POST['id'] ?? 0;
            $parent = (int) $_POST['parent'] ?? 0;
            $sort   = (int) $_POST['sort'] ?? 1;
            $type   = (int) $_POST['type'] ?? 1;
            $name   = $_POST['name'] ?? '';
            $value  = $_POST['value'] ?? '';
            $appId  = $_POST['appId'] ?? '';

            if (!current_user_can('manage_options') || empty($name) || empty($value) || !is_admin()) {
                Response::ajaxForbidden();
            }

            $data   = [
                'name'   => $name,
                'parent' => $parent,
                'sort'   => $sort,
                'type'   => $type,
                'value'  => $value,
                'app_id' => $appId,
            ];
            $result = WechatOAService::updateMenu($id, $data);
            if (!$result) {
                Response::ajaxFailed();
            }
            Response::ajaxUpdated();
        });

        // sync menus
        add_action('wp_ajax_g3_create_wechatOA_menus', function () {
            $this->checkServiceInAjax();
            if (!is_admin() || !current_user_can('manage_options')) {
                Response::ajaxForbidden();
            }
            if (!wp_verify_nonce($_POST['nonce'], 'g3_create_wechatOA_menus')) {
                Response::ajaxForbidden();
            }

            $service = WechatOAService::run();
            $service->deleteMenus();
            $result = $service->createMenus();

            if ($result) {
                Response::ajaxUpdated();
            } else {
                Response::ajaxFailed();
            }
        });
        // flush menus
        add_action('wp_ajax_g3_flush_wechatOA_menus', function () {
            $this->checkServiceInAjax();
            if (!is_admin() || !current_user_can('manage_options')) {
                Response::ajaxForbidden();
            }
            if (!wp_verify_nonce($_POST['nonce'], 'g3_flush_wechatOA_menus')) {
                Response::ajaxForbidden();
            }
            $result = WechatOAService::run()->deleteMenus();
            if ($result) {
                Response::ajaxSuccess(__('Data Flush Complete', 'G3'));
            } else {
                Response::ajaxFailed();
            }
        });

        // get message content
        add_action('wp_ajax_g3_get_wechatOA_message_content', function () {
            $this->checkServiceInAjax();
            if (!is_admin() || !current_user_can('manage_options')) {
                Response::ajaxForbidden();
            }
            if (!wp_verify_nonce($_POST['nonce'], 'g3_get_wechatOA_message_content')) {
                Response::ajaxForbidden();
            }
            $id     = $_POST['id'] ?? 0;
            $result = WechatOAService::getMessageContent($id);
            if ($result) {
                Response::ajaxSuccess($result);
            } else {
                Response::ajaxFailed();
            }
        });
        // delete message
        add_action('wp_ajax_g3_delete_wechatOA_message', function () {
            $this->checkServiceInAjax();
            if (!is_admin() || !current_user_can('manage_options')) {
                Response::ajaxForbidden();
            }
            if (!wp_verify_nonce($_POST['nonce'], 'g3_delete_wechatOA_message')) {
                Response::ajaxForbidden();
            }
            $id     = $_POST['id'] ?? 0;
            $result = WechatOAService::deleteMessage($id);
            if ($result) {
                Response::ajaxDeleted();
            } else {
                Response::ajaxFailed();
            }
        });
    }
}