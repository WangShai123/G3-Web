<?php
return [
    // jQuery
    'jquery'                 => [includes_url('js/jquery/jquery.min.js'), [], '', 'https://unpkg.com/jquery@latest/dist/jquery.min.js'],
    // vanilla signal & library
    'vanilla-signal'         => [G3_JS_URL . '/vanilla-signal.umd.js', [], '1.1.0', 'https://unpkg.com/vanilla-signal@latest/dist/index.umd.js'],
    'vanilla-simple-lru'     => [G3_JS_URL . '/vanilla-simple-lru.umd.js', [], '1.0.0', 'https://unpkg.com/vanilla-simple-lru@latest/dist/index.umd.js'],
    'vanilla-create-storage' => [G3_JS_URL . '/vanilla-storage.umd.js', [], '1.0.0', 'https://unpkg.com/vanilla-storage@latest/dist/index.umd.js'],
    'vanilla-signal-i18n'    => [G3_JS_URL . '/vanilla-i18n.umd.js', ['vanilla-signal'], '1.1.0', 'https://unpkg.com/vanilla-i18n@latest/dist/index.umd.js'],
    'vanilla-signal-query'   => [G3_JS_URL . '/vanilla-query.umd.js', ['vanilla-signal', 'vanilla-simple-lru'], '1.1.0', 'https://unpkg.com/vanilla-query@latest/dist/index.umd.js'],
    'jui'                    => [G3_JS_URL . '/jui.umd.js', ['vanilla-signal', 'vanilla-signal-i18n'], '1.1.1', 'https://unpkg.com/vanilla-jui@latest/dist/index.umd.js'],
    'jui.pca'                => [G3_JS_URL . '/jui.pca.min.js', [], '1.0.0'],
    // G3
    'g3.redirect'            => [WP_PLUGIN_URL . '/g3/assets/javascript/g3.redirect.min.js', [], '1.0.0'],
    'g3.admin'               => [WP_PLUGIN_URL . '/g3/assets/javascript/g3.admin.min.js', ['jquery', 'jui'], '1.0.0'],
    // G3 Theme Helpers
    'g3.login.modal'         => [G3_ASSETS_URL . '/javascript/g3.login.modal.min.js', ['vanilla-signal', 'jui', 'vanilla-signal-i18n', 'vanilla-signal-query'], '1.0.0'],
    // Template Scripts
    'g3.media.upload'        => [WP_PLUGIN_URL . '/g3/assets/javascript/g3.template.media.upload.min.js', ['jquery'], '1.0.0'],
    'g3.media.image'         => [WP_PLUGIN_URL . '/g3/assets/javascript/g3.template.media.image.upload.min.js', ['jquery'], '1.0.0'],

    /**
     * Htm:
     * @link https://github.com/developit/htm
     */
    'htm'                    => [G3_JS_URL . '/htm.umd.js', [], '3.1.1', 'https://unpkg.com/htm@3.1.1/dist/htm.umd.js'],
    /**
     * Decimal: An arbitrary-precision Decimal type for JavaScript
     * @link https://github.com/MikeMcl/decimal.js
     */
    'decimal'                => [G3_JS_URL . '/decimal.min.js', [], '10.6.0', 'https://cdn.jsdelivr.net/npm/decimal.js@10.6.0/decimal.min.js'],
    /**
     * pace: Automatically add a progress bar to your site
     * @link https://github.com/CodeByZach/pace/
     */
    'pace'                   => [G3_JS_URL . '/pace.min.js', [], '1.2.4', 'https://cdn.jsdelivr.net/npm/pace-js@1.2.4/pace.min.js'],
    /**
     * vanilla-lazyload: it leverages IntersectionObserver to lazy load images, backgrounds, videos, iframes and scripts.
     * @link https://github.com/verlok/vanilla-lazyload
     */
    'lazyload'               => [G3_JS_URL . '/lazyload.min.js', [], '19.1.3', 'https://cdn.jsdelivr.net/npm/vanilla-lazyload@19.1.3/dist/lazyload.min.js'],
    /**
     * highlight: JavaScript syntax highlighter with language auto-detection and zero dependencies.
     * @link https://github.com/highlightjs/highlight.js
     */
    'highlight'              => [G3_JS_URL . '/highlight.min.js', [], '11.11.1', 'https://cdn.jsdelivr.net/npm/highlight.js@11.11.1/lib/common.min.js'],
    /**
     * qrcodeJS: Cross-browser QRCode generator for javascript.
     * @link https://github.com/davidshimjs/qrcodejs
     */
    'qrcode'                 => [G3_JS_URL . '/qrcode.min.js', [], '1.0.0', 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js'],
];