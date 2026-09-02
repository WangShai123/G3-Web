<?php
return [
    'jui'                 => [G3_PLUGIN_URL . '/node_modules/vanilla-jui/dist/style.css', [], '1.6.3', 'https://unpkg.com/vanilla-jui@latest/dist/style.css'],

    'g3.comment'          => [G3_ASSETS_URL . '/css/g3.comment.min.css', ['jui'], '1.0.0'],
    'g3.customer.service' => [G3_ASSETS_URL . '/css/g3.customer-service.min.css', ['jui'], '1.0.0'],
    'g3.widget'           => [G3_ASSETS_URL . '/css/g3.widget.min.css', ['jui'], '1.0.0'],

    'g3.admin'            => [G3_ASSETS_URL . '/css/g3.admin.min.css', ['jui'], '1.0.0'],
    'g3.admin.customer'   => [G3_ASSETS_URL . '/css/g3.admin.customer.min.css', ['jui'], '1.0.0'],
];
