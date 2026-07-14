<?php
return [
    'jui'       => [G3_CSS_URL . '/jui.css', [], '1.0.1', 'https://unpkg.com/vanilla-jui@latest/dist/style.css'],
    'widget'              => [G3_ASSETS_URL . '/css/widget.min.css', ['jui'], '1.0.0'],
    'g3.customer.service' => [G3_ASSETS_URL . '/css/g3.customer-service.min.css', ['jui'], '1.0.0'],
    'g3.customer.admin'   => [G3_ASSETS_URL . '/css/g3.customer-admin.min.css', ['jui'], '1.0.0'],
    /**
     * highlight: JavaScript syntax highlighter with language auto-detection and zero dependencies.
     * @link https://github.com/highlightjs/highlight.js
     */
    'highlight' => [G3_CSS_URL . '/highlight.atom-one-dark.min.css', [], '11.11.1', 'https://cdn.jsdelivr.net/npm/highlight.js@11.11.1/styles/atom-one-dark.min.css'],
];
