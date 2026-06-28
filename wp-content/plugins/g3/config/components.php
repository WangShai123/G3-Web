<?php
/**
 * Default component config file
 *
 * 默认组件配置文件
 *
 * 配置说明：
 * - enabled: 全局组件开关；false 时不加载任何组件。
 * - components: 按声明顺序启用组件；依赖会优先于声明顺序加载。
 * - 主题端 config/components.php 存在时，其 components 列表按组件名覆盖或追加插件默认列表。
 * - 主题端同名组件配置覆盖插件默认配置；主题端新增组件追加启用；主题端设为 false 可禁用插件默认组件。
 * - force: 仅插件默认配置支持。默认 false，不需要声明；显式 true 时表示强制启用组件，不允许主题端同名组件配置覆盖。
 *          如果主题端 config/components.php 中声明了同名组件，G3 会忽略主题端覆盖配置，并在页面中显示强提醒错误提示。
 *
 * 支持格式：
 * 'Setting' => true,                                  // 启用组件
 * 'Mail' => false,                                    // 禁用组件
 * 'Security' => [
 *     'enabled' => true,
 *     'dependency' => 'Setting',                      // 依赖单个组件
 * ],
 * 'Themes' => [
 *     'enabled' => true,
 *     'force' => true,                                // 强制启用，不允许主题端覆盖或禁用
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
        'Setting'      => [
            'enabled' => true,
            'force'   => true,
        ],
        'Themes'       => [
            'enabled' => true,
            'force'   => true,
        ],

        'Auth'         => true,
        'Post'         => true,
        'Share'        => true,
        'Product'      => true,

        'Wallet'       => true,
        'WechatOA'     => true,

        'Comment'      => true,
        'Activity'     => true,
        'Announcement' => true,
        'Orders'       => true,
        'Swiper'       => true,
        'Ad'           => true,
        'Security'     => true,
        'Mail'         => true,
        'User'         => true,
        'Sms'          => true,
        'Developer'    => true,
    ]
];
