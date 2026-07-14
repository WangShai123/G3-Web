<?php

namespace JEALER\G3\Components;

use JEALER\G3\Components\Components;

class Sms extends Components {

    protected function adminMenu(): void
    {
        add_submenu_page(
            'g3-settings',
            __('SMS', 'G3'),
            __('SMS', 'G3'),
            'manage_options',
            'g3-sms',
            [$this, 'render'],
            17
        );
    }

    public function render(): void
    {
        $title = __('SMS', 'G3');
        $text  = __('The current feature will be integrated after the requirement vote is approved.', 'G3');
        echo "<div class='wrap'><h1>{$title}</h1><p>{$text}</p></div>";
    }
}
