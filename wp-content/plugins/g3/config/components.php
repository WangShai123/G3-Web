<?php

/**
 * Default component config file
 *
 * 默认组件配置文件
 *
 * 配置说明：
 * - enabled: 全局组件开关；false 时不加载任何组件。
 * - components: 按声明顺序启用组件；依赖会优先于声明顺序加载。
 * - 主题端 config/components.php 存在时，其 components 列表整体覆盖插件默认列表。
 *
 * 支持格式：
 * 'Setting' => true,                                  // 启用组件
 * 'Mail' => false,                                    // 禁用组件
 * 'Security' => [
 *     'enabled' => true,
 *     'dependency' => 'Setting',                      // 依赖单个组件
 * ],
 * 'Developer' => [
 *     'enabled' => true,
 *     'dependency' => ['Setting', 'Security'],        // 依赖多个组件，全部满足才加载
 * ],
 * 'WechatOA' => [true, ['Setting', 'Security']],      // 短格式：[enabled, dependency]
 * 'Sms' => [
 *     'enabled' => true,
 *     'dependency' => static function ($registry): bool {
 *         return $registry->has('Setting');
 *     },
 * ],
 *
 * dependency 支持：
 * - bool/null: true 或 null 表示无依赖，false 表示不加载。
 * - string: 组件名。
 * - array<string>: 组件名列表，按 AND 处理。
 * - callable: 回调返回 true 时加载；回调参数为 ComponentRegistry 和 ComponentLoader。
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
