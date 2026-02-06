<?php
namespace JEALER\G3\Components;

use JEALER\G3\Components\Components;
use JEALER\G3\Utilities\Element;
use Override;

class Orders extends Components {

    #[Override]
    protected function adminMenu(): void
    {
        add_menu_page(
            __('Orders', 'G3'),
            __('Orders', 'G3'),
            'manage_options',
            'order',
            [$this, 'render'],
            'dashicons-chart-bar',
            28
        );
    }

    public function render()
    {
        echo '<div class="wrap"><h1>' . __('Orders', 'G3') . '</h1>';
        $args = [
            'general' => __('General'),
        ];
        Element::tab('Orders', 'general', $args);
        echo '</div>';
    }
}