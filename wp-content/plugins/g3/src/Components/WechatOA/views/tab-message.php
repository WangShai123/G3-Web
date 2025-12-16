<?php
use JEALER\G3\Components;
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Utilities\Container;
use JEALER\G3\Includes\WechatOAMessagesListTable;

Frontend::loadStyle('jui');
$table = new WechatOAMessagesListTable();

$option = Components::getProperty('WechatOA', 'option');
$enable = $option['storeMessages'] ?? false;

if (!$enable) :
    echo Container::tip(
        __('Message is unavailable. Because the WechatOA message storage function has been disabled.', 'G3'),
        'danger',
        'mt-4'
    );
else :
    $table->display();
endif;
