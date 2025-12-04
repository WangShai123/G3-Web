<?php

/** CLI entry point */
if (php_sapi_name() != 'cli') {
    exit('illegal request');
}

/** Load the console */
require_once __DIR__ . '/wp-content/plugins/g3/bin/console.php';
