<?php
use JEALER\G3\Components;
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Utilities\Container;
use JEALER\G3\Includes\WechatOAMessageListTable;

Frontend::loadStyle('jui');
$table = new WechatOAMessageListTable();

$option = Components::getProperty('WechatOA', 'option');
$enable = $option['storeMessages'] ?? false;

if (!$enable) :
    echo Container::tip(
        __('Message is unavailable. Because the WechatOA message storage function has been disabled.', 'G3'),
        'danger',
        'mt-4'
    );
else :
    echo '<form id="list-form" method="post">';
    $table->display();
    echo '</form>';
endif;
