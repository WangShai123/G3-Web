<?php
namespace JEALER\G3\Components;

use JEALER\G3\Components;

class Ad extends Components {

    protected function adminMenu(): void
    {
        add_menu_page(
            __('Ad', 'G3'),
            __('Ad', 'G3'),
            'manage_options',
            'ad',
            [$this, 'render'],
            'dashicons-images-alt2',
            '30'
        );
    }
    public function render()
    {
        echo 'todo';
    }
}