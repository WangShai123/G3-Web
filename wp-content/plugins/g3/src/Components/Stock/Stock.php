<?php
namespace JEALER\G3\Components;

use JEALER\G3\Components;
use JEALER\G3\Utilities\Container;
use JEALER\G3\Utilities\Option;
use Override;

class Stock extends Components {
    private bool $dep;

    #[Override]
    protected function options(): void
    {
        $dep       = Components::getProperty('Product', 'option')['skuMode'] ?? false;
        $this->dep = $dep === '1' ? true : false;
    }

    #[Override]
    protected function admin(): void
    {
        if (!$this->dep) return;
    }
}