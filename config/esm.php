<?php
return [
    // vanilla library
    'vanilla-signal'         => [G3_PLUGIN_URL . '/node_modules/vanilla-signal/dist/index.js', [], '1.1.15', 'https://unpkg.com/vanilla-signal@latest/dist/index.js'],
    'vanilla-simple-lru'     => [G3_PLUGIN_URL . '/node_modules/vanilla-simple-lru/dist/index.js', [], '1.0.3', 'https://unpkg.com/vanilla-simple-lru@latest/dist/index.js'],
    'vanilla-create-storage' => [G3_PLUGIN_URL . '/node_modules/vanilla-create-storage/dist/index.js', [], '1.0.4', 'https://unpkg.com/vanilla-storage@latest/dist/index.js'],
    'vanilla-request'        => [G3_PLUGIN_URL . '/node_modules/vanilla-request/dist/index.js', [], '1.1.0', 'https://unpkg.com/vanilla-request@latest/dist/index.js'],
    'vanilla-sse'            => [G3_PLUGIN_URL . '/node_modules/vanilla-sse/dist/index.js', [], '1.0.0', 'https://unpkg.com/vanilla-sse@latest/dist/index.js'],
    'vanilla-signal-i18n'    => [G3_PLUGIN_URL . '/node_modules/vanilla-signal-i18n/dist/index.js', ['vanilla-signal'], '1.1.2', 'https://unpkg.com/vanilla-i18n@latest/dist/index.js'],
    'vanilla-signal-query'   => [G3_PLUGIN_URL . '/node_modules/vanilla-signal-query/dist/index.js', ['vanilla-signal', 'vanilla-simple-lru'], '1.1.6', 'https://unpkg.com/vanilla-query@latest/dist/index.js'],
    'jui'                    => [G3_PLUGIN_URL . '/node_modules/vanilla-jui/dist/index.js', ['vanilla-signal', 'vanilla-signal-i18n', 'vanilla-create-storage'], '1.6.3', 'https://unpkg.com/vanilla-jui@latest/dist/index.js'],

    // build-in g3 modules
    'g3.comment'             => [G3_ASSETS_URL . '/js/es/g3.comment.min.js', ['vanilla-signal', 'jui', 'vanilla-signal-i18n', 'vanilla-create-storage'], '1.0.0'],
    'g3.customer.service'    => [G3_ASSETS_URL . '/js/es/g3.customer-service.min.js', ['jui', 'vanilla-create-storage'], '1.0.0'],
    'g3.form'                => [G3_ASSETS_URL . '/js/es/g3.form.min.js', ['jui', 'vanilla-signal-i18n'], '1.0.0'],
    'g3.login.modal'         => [G3_ASSETS_URL . '/js/es/g3.login.modal.min.js', ['jui'], '1.0.0'],
    'g3.login.oa'            => [G3_ASSETS_URL . '/js/es/g3.login.oa.min.js', ['vanilla-signal', 'vanilla-signal-i18n', 'vanilla-create-storage', 'jui'], '1.0.0'],
    'g3.redirect'            => [G3_ASSETS_URL . '/js/es/g3.redirect.min.js', [], '1.0.0'],
    'g3.subscribe.modal'     => [G3_ASSETS_URL . '/js/es/g3.subscribe.modal.min.js', ['jui', 'vanilla-signal-i18n', 'vanilla-create-storage'], '1.0.0'],
    'g3.switch.language'     => [G3_ASSETS_URL . '/js/es/g3.switch.language.min.js', ['jui', 'vanilla-signal-i18n'], '1.0.0'],
    'g3.visitor'             => [G3_ASSETS_URL . '/js/es/g3.visitor.min.js', ['fingerprint'], '1.0.0'],

    /**
     * fingerprintjs
     * @link: https://github.com/fingerprintjs/fingerprintjs
     * format: IIFE
     */
    'fingerprint'            => [G3_JS_URL . '/es/fingerprint.min.js', [], '5.2.0', 'https://unpkg.com/@fingerprintjs/fingerprintjs@5.2.0/dist/fp.min.js'],
    /**
     * High-performance engine for capturing, modifying, and converting DOM elements into any format.
     * @link https://unpkg.com/@zumer/snapdom@dev/dist/
     */
    'snapdom'                => [G3_JS_URL . '/es/snapdom.js', [], '2.24.1', 'https://unpkg.com/@zumer/snapdom/dist/snapdom.mjs'],
];
