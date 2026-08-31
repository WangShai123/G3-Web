<?php
return [
    // vanilla signal & library
    'vanilla-signal'         => [G3_JS_URL . '/vanilla-signal.umd.js', [], '1.1.14', 'https://unpkg.com/vanilla-signal@latest/dist/index.umd.js'],
    'vanilla-simple-lru'     => [G3_JS_URL . '/vanilla-simple-lru.umd.js', [], '1.0.2', 'https://unpkg.com/vanilla-simple-lru@latest/dist/index.umd.js'],
    'vanilla-create-storage' => [G3_JS_URL . '/vanilla-storage.umd.js', [], '1.0.4', 'https://unpkg.com/vanilla-storage@latest/dist/index.umd.js'],
    'vanilla-signal-i18n'    => [G3_JS_URL . '/vanilla-i18n.umd.js', ['vanilla-signal'], '1.1.2', 'https://unpkg.com/vanilla-i18n@latest/dist/index.umd.js'],
    'vanilla-signal-query'   => [G3_JS_URL . '/vanilla-query.umd.js', ['vanilla-signal', 'vanilla-simple-lru'], '1.1.6', 'https://unpkg.com/vanilla-query@latest/dist/index.umd.js'],
    'vanilla-request'        => [G3_JS_URL . '/vanilla-request.umd.js', [], '1.1.0', 'https://unpkg.com/vanilla-request@latest/dist/index.umd.js'],
    'jui'                    => [G3_JS_URL . '/jui.umd.js', ['vanilla-signal', 'vanilla-signal-i18n', 'vanilla-create-storage'], '1.6.0', 'https://unpkg.com/vanilla-jui@latest/dist/index.umd.js'],
    'jui.pca'                => [G3_JS_URL . '/jui.pca.min.js', [], '1.0.0'],
    // G3
    'g3.admin'               => [G3_ASSETS_URL . '/js/g3.admin.min.js', ['jquery', 'jui'], '1.0.0'],
    'g3.admin.notification'  => [G3_ASSETS_URL . '/js/g3.admin.notification.min.js', ['jui'], '1.0.0'],
    'g3.admin.customer'      => [G3_ASSETS_URL . '/js/g3.admin.customer.min.js', ['jui'], '1.0.0'],
    'g3.admin.tablelist'     => [G3_ASSETS_URL . '/js/g3.admin.tablelist.min.js', ['jui'], '1.0.0'],

    // G3 WP Media Scripts
    'g3.media.upload'        => [G3_ASSETS_URL . '/js/g3.media.upload.min.js', ['jquery'], '1.0.0'],
    'g3.media.image'         => [G3_ASSETS_URL . '/js/g3.media.image.upload.min.js', ['jquery'], '1.0.0'],

    /**
     * infiniteGrid
     * @link: https://github.com/naver/egjs-infinitegrid
     */
    'infiniteGrid'           => [G3_JS_URL . '/infinitegrid.min.js', [], '4.13.0', 'https://unpkg.com/@egjs/infinitegrid@4.13.0/dist/infinitegrid.min.js'],
    /**
     * Decimal: An arbitrary-precision Decimal type for JavaScript
     * @link https://github.com/MikeMcl/decimal.js
     */
    'decimal'                => [G3_JS_URL . '/decimal.min.js', [], '10.6.0', 'https://cdn.jsdelivr.net/npm/decimal.js@10.6.0/decimal.min.js'],
    /**
     * highlight: JavaScript syntax highlighter with language auto-detection and zero dependencies.
     * @link https://github.com/highlightjs/highlight.js
     */
    'highlight'              => [G3_JS_URL . '/highlight.min.js', [], '11.11.1', 'https://cdn.jsdelivr.net/npm/highlight.js@11.11.1/lib/common.min.js'],
    /**
     * High-performance engine for capturing, modifying, and converting DOM elements into any format.
     * @link https://unpkg.com/@zumer/snapdom@dev/dist/
     */
    'snapdom'                => [G3_JS_URL . '/snapdom.js', [], '2.24.1', 'https://unpkg.com/@zumer/snapdom/dist/snapdom.js'],
];
