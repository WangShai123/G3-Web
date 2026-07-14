<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use JEALER\G3\Core\Admin\Panel;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Services\WechatOAService;
use JEALER\G3\Utilities\Response;
use Override;

class WechatOA extends Components {
    private function optionDefaults(): array
    {
        return [
            'service'        => '0',
            'search'         => '0',
            'storeMessages'  => '0',
            'count'          => '5',
            'length'         => '16',
            'followMessage'  => __('Welcome! Thanks for your attention.', 'G3'),
            'visitMessage'   => __('Welcome back, my friend.', 'G3'),
            'defaultMessage' => __('Message received, thanks for your advice!', 'G3'),
        ];
    }
    private function eventOptionDefaults(): array
    {
        return ['latestPosts' => 'n'];
    }
    protected function adminMenu(): void
    {
        add_submenu_page(
            'g3-settings',
            __('Wechat OA', 'G3'),
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
        $this->createPanel();
    }
    protected function adminPanelPage(): string
    {
        return 'wechat-oa';
    }
    protected function adminPanels(): array
    {
        return [
            $this->panel('wechat-oa', __('Wechat OA', 'G3'))
                ->tab('general', __('General'))
                ->option(WechatOAService::OPTION_KEY, $this->optionDefaults())
                ->switch('service', __('Wechat OA Service', 'G3'))
                ->rowClass('advanced')
                ->switch('search', __('Search'), __('Users who send messages to the WeChat Official Account will automatically search for the content on the website and return the content.', 'G3'))
                ->switch('storeMessages', __('Store Messages', 'G3'), __('The messages sent to the WeChat Official Account will be stored in the database.', 'G3'))
                ->select('count', __('News Count', 'G3'), ['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6', '7' => '7', '8' => '8'], __('The maximum number of news items returned in the Key-Event message. Default: 5.', 'G3'))
                ->number('length', __('Max Length of Search Keyword', 'G3'), __('The maximum length of the search keyword. Default: 16.', 'G3'))
                ->textarea('followMessage', __('Subscribe Message', 'G3'), __('The message sent to users when they Subscribe the WeChat Official Account.', 'G3'))
                ->textarea('defaultMessage', __('Default Message', 'G3'), __('The default message replied to users when the search is disabled and no message is found.', 'G3'))
                ->tab('menus', __('Menus'))
                ->tab('message', __('Messages', 'G3'))
                ->tab('reply', __('Custom Reply', 'G3'))
                ->tab('event', __('Event Replay', 'G3'))
                ->option(WechatOAService::EVENT_OPTION_KEY, $this->eventOptionDefaults())
                ->input('latestPosts', __('Latest Posts', 'G3'), __('The Key that will call the latest posts. Default: n', 'G3')),
        ];
    }
    private function serviceAvailable(): bool
    {
        $container = Container::run();
        if ($container->has('loader')) {
            $result = $container->get('loader')->y();
            $option = get_option(WechatOAService::OPTION_KEY, []);
            return is_array($option) && ($option['service'] ?? '0') === '1' && $result;
        }
        return false;
    }
    private function checkServiceInAjax(): void
    {
        if (!$this->serviceAvailable()) {
            Response::ajaxError(__('Wechat Official Account service is not enabled', 'G3'));
        }
    }

    protected function ajax(): void
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

            $service = Container::run()->get(WechatOAService::class);
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
            $result = Container::run()->get(WechatOAService::class)->deleteMenus();
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
        // flush old messages
        add_action('wp_ajax_g3_flush_old_wechatOA_messages', function () {
            $this->checkServiceInAjax();
            if (!is_admin() || !current_user_can('manage_options')) {
                Response::ajaxForbidden();
            }
            if (!wp_verify_nonce($_POST['nonce'], 'g3_flush_old_wechatOA_messages')) {
                Response::ajaxForbidden();
            }
            // delete old messages older than n days, default: 7
            $days   = (int) $_POST['days'];
            $days   = $days < 1 ? 7 : $days;
            $result = WechatOAService::flushOldMessages($days);
            if ($result === 0) {
                Response::ajaxError(__('No items found.'));
            } elseif ($result === false) {
                Response::ajaxFailed();
            } else {
                Response::ajaxDeleted();
            }
        });
    }
}
