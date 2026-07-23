<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use Override;

class Ad extends Components {
    protected function adminMenu()
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
        echo '<div class="wrap"><h1>' . __('Ad', 'G3') . '</h1>';
        echo __('Stay tuned, coming soon!', 'G3');
        echo '</div>';
    }
}
