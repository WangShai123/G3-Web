<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * Default Rewrite Config
 * 默认 rewrite 配置
 * 
 * 配置格式:
 * 'url_pattern' => [
 *     'var'  => 'query_var_name',     // 单个query var
 *     'var'  => ['var1', 'var2'],     // 多个query vars
 *     'path' => 'demo/default.php',
 *     'priority' => [
 *         [
 *             'value' => 'value1',
 *             'path'  => 'demo/template-1.php'
 *         ],
 *         [
 *             'value' => 'value2',
 *             'path'  => 'demo/template-2.php'     
 *         ]
 *     ],
 *     'dependency' => 'ComponentName', // 依赖的组件名
 * ]
 * 注意1: 使用正则表达式配置 url_pattern 且必须以 /?$ 结尾
 * 注意2: var 类型为 string / array，string 代表一个查询参数，array 代表多个查询参数
 * 注意3: path 类型为 string，是 templates 目录下的 php 文件路径，代表默认加载的模板文件
 * 注意4: priority 为可选参数，值为二维数组。处理高优先级的模板绑定
 * 注意5: dependency 为可选参数，类型为 string / bool / callback ，处理依赖关系，依赖的条件满足时才注册 rewrite 规则。string 是组件的类名，代表依赖的组件
 * 注意6: 多条配置时，不能有相同的 var 或 path，否则仅第一条配置生效
 * 注意7: 用户主题配置优先级高于系统默认配置，会覆盖插件中系统默认配置
 * 注意8: 在 Debug 模式下或 Development 环境下，系统会自动注册 query vars 并自动刷新 rewrite 规则
 * 注意9: 在 Production 环境下，需要手动刷新 rewrite 规则
 * 
 * @return array
 * @since 1.0.0
 * @author Wang Shai
 */
return [
    // Admin Login
    'oa/([^/]+)/?$'               => [
        'var'        => 'custom_admin_login',
        'path'       => 'admin/oa.php',
        'priority'   => [
            [
                'value' => [\JEALER\G3\Components\Security::class, 'customAdminParam'],
                'path'  => 'admin/oa.php'
            ]
        ],
        'dependency' => [\JEALER\G3\Components\Security::class, 'customAdminLogin']
    ],

    // User Register Page
    'user/([^/]+)/?$'             => [
        'var'      => 'user_action',
        'path'     => 'user/user.php',
        'priority' => [
            [
                'value' => 'register',
                'path'  => 'user/register.php'
            ],
            [
                'value' => 'login',
                'path'  => 'user/login.php'
            ]
        ]
    ],

    // Wechat OA Callback
    'dev/wechat_oa/([^/]+)/?$'    => [
        'var'      => 'callback',
        'path'     => 'wechat/_.php',
        'priority' => [
            [
                'value' => 'callback',
                'path'  => 'wechat/callback.php'
            ]
        ]
    ],


    // 单个 query var 示例
    'test/one/([^/]+)/?$'         => [
        'var'  => 'test_id',
        'path' => 'test/single.php',
    ],

    // 多个 query vars 示例
    'test/two/([^/]+)/([^/]+)/?$' => [
        'var'  => ['multiple_id', 'multiple_name'],
        'path' => 'test/multiple.php',
    ],

];