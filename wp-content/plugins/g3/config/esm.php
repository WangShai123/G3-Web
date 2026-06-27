<?php
return [
    // vanilla library
    'vanilla-signal'         => [G3_JS_URL . '/es/vanilla-signal.js', [], '1.1.0', 'https://unpkg.com/vanilla-signal@latest/dist/index.js'],
    'vanilla-simple-lru'     => [G3_JS_URL . '/es/vanilla-simple-lru.js', [], '1.0.0', 'https://unpkg.com/vanilla-simple-lru@latest/dist/index.js'],
    'vanilla-create-storage' => [G3_JS_URL . '/es/vanilla-storage.js', [], '1.0.0', 'https://unpkg.com/vanilla-storage@latest/dist/index.js'],
    'vanilla-signal-i18n'    => [G3_JS_URL . '/es/vanilla-i18n.js', ['vanilla-signal'], '1.1.0', 'https://unpkg.com/vanilla-i18n@latest/dist/index.js'],
    'vanilla-signal-query'   => [G3_JS_URL . '/es/vanilla-query.js', ['vanilla-signal', 'vanilla-simple-lru'], '1.1.0', 'https://unpkg.com/vanilla-query@latest/dist/index.js'],
    'jui'                    => [G3_JS_URL . '/es/jui.js', ['vanilla-signal', 'vanilla-signal-i18n'], '1.1.1', 'https://unpkg.com/vanilla-jui@latest/dist/index.js'],

    // build-in g3 modules
    'g3.login.modal'         => [G3_ASSETS_URL . '/javascript/es/g3.login.modal.min.js', ['jui'], '1.0.0'],
    'g3.subscribe.modal'     => [G3_ASSETS_URL . '/javascript/es/g3.subscribe.modal.min.js', ['jui', 'vanilla-signal-i18n', 'vanilla-create-storage'], '1.0.0'],
    'g3.login.oa'            => [G3_ASSETS_URL . '/javascript/es/g3.login.oa.min.js', ['vanilla-signal', 'vanilla-signal-i18n', 'vanilla-create-storage', 'jui'], '1.0.0'],

    /**
     * qrcodeJS: Cross-browser QRCode generator for javascript
     * @link: https://github.com/davidshimjs/qrcodejs
     * @todo: add file
     */
    'qrcode'                 => ['', [], '1.0.0', 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/+esm'],
];