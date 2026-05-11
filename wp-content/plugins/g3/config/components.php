<?php

/**
 * Default component config file
 *
 * 默认组件配置文件
 *
 * @return array
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
        'Activity'     => true,
        'Announcement' => true,
        'Orders'       => true,
        'Swiper'       => true,
        'Themes'       => true,
        'Ad'           => true,

        'Security'     => true,

        'Mail'         => true,
        'User'         => true,
        'Sms'          => true,
        'Developer'    => true,
    ]
];
