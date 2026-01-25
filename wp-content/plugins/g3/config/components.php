<?php

use JEALER\G3\Services\AuthService;
use JEALER\G3\Services\SystemService;

/**
 * Default component config file
 * 默认组件配置文件
 * 
 * @return array
 * @since 1.0.0
 * @author Wang Shai
 */
return [
    'enabled'    => true,
    'components' => [
        'Setting'      => true, // 1
        'Auth'         => true, // 2
        'Post'         => true, // 3
        'Share'        => true, // 4
        'Product'      => true, // 5

        'Wallet'       => true, // 14
        'WechatOA'     => true, // 15

        'Comment'      => true,
        'Jui'          => true,
        'Activity'     => true,
        'Announcement' => true,
        'Order'        => true,
        'Swiper'       => true,
        'Stock'        => true,
        'Themes'       => true,
        'Ad'           => true,

        'Security'     => true,

        'Mail'         => true,
        'Menu'         => true,
        'User'         => true,
        'Developer'    => true,
    ]
];
