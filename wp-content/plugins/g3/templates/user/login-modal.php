<?php
use JEALER\G3\Services\AuthService;
use JEALER\G3\Utilities\Frontend;

$dataset = $args['dataset'] ?? [];

// Frontend::esm('vanilla-signal');
// Frontend::esm('vanilla-simple-lru');
// Frontend::esm('vanilla-signal-query');
// Frontend::esm('vanilla-signal-i18n');
// Frontend::esm('jui');
$subscribe = get_option(AuthService::WECHAT_OPTION_KEY)['subscribe'] ?? false;
if ($subscribe) {
    Frontend::esm('g3.subscribe.modal');
} else {
    Frontend::umd('g3.login.modal');
}
?>