<?php
namespace JEALER\G3\Components;

use JEALER\G3\Components\Components;
use Override;

class Order extends Components {

    #[Override]
    protected function adminMenu(): void
    {
        add_menu_page(
            __('Order', 'G3'),
            __('Order', 'G3'),
            'manage_options',
            'order',
            [$this, 'render'],
            'dashicons-chart-bar',
            28
        );
    }

    public function render()
    {
        echo 'todo';
    }
}