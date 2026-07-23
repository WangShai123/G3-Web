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
    'g3.redirect'            => [G3_ASSETS_URL . '/js/es/g3.redirect.min.js', [], '1.0.0'],
    'g3.login.modal'         => [G3_ASSETS_URL . '/js/es/g3.login.modal.min.js', ['jui'], '1.0.0'],
    'g3.subscribe.modal'     => [G3_ASSETS_URL . '/js/es/g3.subscribe.modal.min.js', ['jui', 'vanilla-signal-i18n', 'vanilla-create-storage'], '1.0.0'],
    'g3.login.oa'            => [G3_ASSETS_URL . '/js/es/g3.login.oa.min.js', ['vanilla-signal', 'vanilla-signal-i18n', 'vanilla-create-storage', 'jui'], '1.0.0'],
    'g3.form'                => [G3_ASSETS_URL . '/js/es/g3.form.min.js', ['jui', 'vanilla-signal-i18n'], '1.0.0'],
    'g3.switch.language'     => [G3_ASSETS_URL . '/js/es/g3.switch.language.min.js', ['jui', 'vanilla-signal-i18n'], '1.0.0'],
    'g3.customer.service'    => [G3_ASSETS_URL . '/js/es/g3.customer-service.min.js', ['jui'], '1.0.0'],

    /**
     * fingerprintjs
     * @link: https://github.com/fingerprintjs/fingerprintjs
     * format: IIFE
     */
    'fingerprint'            => [G3_JS_URL . '/es/fingerprint.min.js', [], '5.2.0', 'https://unpkg.com/@fingerprintjs/fingerprintjs@5.2.0/dist/fp.min.js'],
    'g3.visitor'             => [G3_ASSETS_URL . '/js/es/g3.visitor.min.js', ['fingerprint'], '1.0.0'],

    /**
     * infiniteGrid
     * @link: https://github.com/naver/egjs-infinitegrid
     */
    // 'infiniteGrid'           => [G3_JS_URL . '/es/infinitegrid.esm.min.js', [], '4.13.0', 'https://unpkg.com/@egjs/infinitegrid@4.13.0/dist/infinitegrid.esm.js'],

    /**
     * qrcodeJS: Cross-browser QRCode generator for javascript
     * @link: https://github.com/davidshimjs/qrcodejs
     * @todo: add file
     */
    'qrcode'                 => ['', [], '1.0.0', 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/+esm'],
];
