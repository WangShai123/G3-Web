<?php
namespace JEALER\G3\Components;

use JEALER\G3\Components\Components;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Option;
use Override;

class Stock extends Components {
    private bool $dep;

    #[Override]
    protected function options(): void
    {
        // $dep       = Components::getProperty('Product', 'option')['skuMode'] ?? false;
        // $this->dep = $dep === '1' ? true : false;
    }

    #[Override]
    protected function admin(): void
    {
    }

    protected function adminMenu(): void
    {
        add_menu_page(
            __('Stock', 'g3'),
            __('Stock', 'g3'),
            'manage_options',
            'stock',
            [$this, 'render'],
            'dashicons-database',
            29
        );
    }

    public function render(): void
    {
        echo 'todo';
    }
}