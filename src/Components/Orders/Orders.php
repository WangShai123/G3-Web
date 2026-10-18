<?php
namespace JEALER\G3\Components;
use JEALER\G3\Core\Components\Components;
use JEALER\G3\Services\OrdersService;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Utilities\Response;
use Override;

class Orders extends Components {
    private OrdersService $service;

    protected function adminMenu(): void
    {
        $this->service = Container::run()->get(OrdersService::class);
        $orders        = __('Orders', 'G3');

        $title     = $orders;
        $paidCount = $this->service->getPaidOrdersCount();
        if ($paidCount > 0) {
            $title .= " <span class=\"g3-badge is-primary\">{$paidCount}</span>";
        }

        add_menu_page(
            $title,
            $title,
            'manage_options',
            'orders',
            '__return_false',
            'dashicons-chart-bar',
            28
        );

        add_submenu_page(
            'orders',
            sprintf(__('%s %s', 'G3'), __('All', 'G3'), $orders),
            sprintf(__('%s %s', 'G3'), __('All', 'G3'), $orders),
            'manage_options',
            'orders',
            [$this, 'render'],
            1
        );
        add_submenu_page(
            'orders',
            __('Manual Orders', 'G3'),
            __('Manual Orders', 'G3'),
            'manage_options',
            'manual',
            function () {
                require_once __DIR__ . '/views/view-manual.php';
            },
            2
        );
    }
    public function render(): void
    {
        require_once __DIR__ . '/views/view-orders.php';
    }
    protected function ajax(): void
    {
        $this->service = Container::run()->get(OrdersService::class);

        add_action('wp_ajax_g3_close_order', function () {
            $orderId = $_POST['order_id'];
            $result  = $this->service->closeOrder($orderId);
            if ($result !== false) {
                Response::ajaxUpdated();
            } else {
                Response::ajaxFailed();
            }
        });
        add_action('wp_ajax_g3_delete_order', function () {
            $orderId = $_POST['order_id'];
            $result  = $this->service->deleteOrderById($orderId);
            if ($result !== false) {
                Response::ajaxDeleted();
            } else {
                Response::ajaxFailed();
            }
        });
        add_action('wp_ajax_g3_ship_order', function () {
            // @todo
        });
    }
}
