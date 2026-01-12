<?php
use JEALER\G3\Services\AuthService;
use JEALER\G3\Utilities\Frontend;

$dataset = $args['dataset'] ?? [];

Frontend::loadModule('jui');
$subscribe = get_option(AuthService::WECHAT_OPTION_KEY)['subscribe'] ?? false;
if ($subscribe) {
    Frontend::loadModule('g3.subscribe.modal');
} else {
    Frontend::loadModule('g3.login.modal');
}
?>