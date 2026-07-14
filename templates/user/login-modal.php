<?php
use JEALER\G3\Services\AuthService;
use JEALER\G3\Utilities\Frontend;

$subscribe = get_option(AuthService::WECHAT_OPTION_KEY)['subscribe'] ?? false;
if ($subscribe) {
    Frontend::esm('g3.subscribe.modal');
} else {
    Frontend::esm('g3.login.modal');
}
?>
