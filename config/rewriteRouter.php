<?php
if (!defined('ABSPATH')) {
    exit;
}
/**
 * Default Rewrite Config
 * 
 * 默认 rewrite 配置
 * 
 * 配置格式:
 * 'url_pattern' => [
 *     'var'  => 'query_var_name',     // 单个query var
 *     'var'  => ['var1', 'var2'],     // 多个query vars
 *     'path' => 'demo/default.php',
 *     'priority' => [
 *         [
 *             'value'      => 'value1',               // 固定值匹配
 *             'path'       => 'demo/template-1.php',
 *             'dependency' => 'ComponentName'          // 可选，仅当前 priority 项满足依赖时才参与匹配
 *         ],
 *         [
 *             'value' => [ClassName::class, 'method'], // 回调返回 true 或返回待比较值
 *             'path'  => 'demo/template-2.php'
 *         ],
 *         [
 *             'callback' => [ClassName::class, 'template'], // 回调返回 true、模板路径或 ['path' => '...']
 *             'path'     => 'demo/template-3.php'
 *         ]
 *     ],
 *     'dependency' => 'ComponentName', // 依赖的组件名
 * ]
 * 注意1: 使用正则表达式配置 url_pattern 且必须以 /?$ 结尾
 * 注意2: var 类型为 string / array，string 代表一个查询参数，array 代表多个查询参数
 * 注意3: path 类型为 string，是 templates 目录下的 php 文件路径，代表默认加载的模板文件
 * 注意4: priority 为可选参数，值为二维数组。处理高优先级的模板绑定，支持固定值和回调决定模板
 * 注意5: priority 回调参数顺序为 $value, $values, $route, $queryVars
 * 注意6: dependency 为可选参数，类型为 string / bool / callback ，处理依赖关系，依赖的条件满足时才注册 rewrite 规则。string 是组件的类名，代表依赖的组件。priority 内也支持 dependency，仅影响当前 priority 项
 * 注意7: 多条配置时，不能有相同的 var 或 path，否则仅第一条配置生效
 * 注意8: 用户主题配置优先级高于系统默认配置，相同 url_pattern 会覆盖插件默认配置；主题配置可用 false 或 ['enabled' => false] 禁用插件默认规则
 * 注意9: 在 Debug 模式下或 Development 环境下，系统会自动注册 query vars 并自动刷新 rewrite 规则
 * 注意10: 在 Production 环境下，需要手动刷新 rewrite 规则
 * 
 * @return array
 */
return [
    // Admin Login
    'oa/([^/]+)/?$'                      => [
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
    'user/([^/]+)/?$'                    => [
        'var'      => 'g3_var_user',
        'path'     => '302.php',
        'priority' => [
            [
                'callback' => [\JEALER\G3\Services\UserService::class, 'authTemplatePath']
            ]
        ],
    ],

    // Wechat OA Callback
    'dev/wechat_oa/([^/]+)/?$'           => [
        'var'      => 'callback',
        'path'     => '302.php',
        'priority' => [
            [
                'value' => 'callback',
                'path'  => 'wechat/callback.php'
            ]
        ]
    ],

    // Redirect Link
    'redirect/go/([^/]+)/?$'             => [
        'var'        => 'g3_var_redirect_url',
        'path'       => 'developer/redirect.php',
        'dependency' => [\JEALER\G3\Components\Setting::class, 'onRedirect']
    ],

    // LLM Endpoint
    'helper/llm/([^/]+)/?$'              => [
        'var'        => 'g3_var_llm',
        'path'       => '302.php',
        'priority'   => [
            [
                'value' => 'endpoint',
                'path'  => 'helper/llms.php'
            ]
        ],
        'dependency' => [\JEALER\G3\Components\Setting::class, 'onLLM']
    ],

    // Sitemap Endpoint
    'helper/sitemap/([^/]+)/?$'          => [
        'var'        => 'g3_var_sitemap',
        'path'       => '302.php',
        'priority'   => [
            [
                'value' => 'endpoint',
                'path'  => 'helper/sitemap.php'
            ]
        ],
        'dependency' => [\JEALER\G3\Components\Security::class, 'onSitemap']
    ],

    // Sitemap Endpoint Pagination
    'helper/sitemap/([^/]+)/([0-9]+)/?$' => [
        'var'        => ['g3_var_sitemap', 'g3_var_sitemap_page'],
        'path'       => 'helper/sitemap.php',
        'priority'   => [
            [
                'var'   => 'g3_var_sitemap',
                'value' => 'endpoint',
                'path'  => 'helper/sitemap.php'
            ]
        ],
        'dependency' => [\JEALER\G3\Components\Security::class, 'onSitemap']
    ],

    // form
    'helper/form/?$'                     => [
        'var'        => 'g3_var_form',
        'path'       => 'form/form.php',
        'dependency' => [\JEALER\G3\Components\Form::class, 'onForm']
    ],

    // my
    'my/(.*)?$'                          => [
        'var'      => 'g3_var_my',
        'path'     => '302.php',
        'priority' => [
            [
                'value' => 'home',
                'path'  => 'my/home.php'
            ],
            [
                'value' => 'message',
                'path'  => 'my/message.php'
            ],
            [
                'value' => 'order',
                'path'  => 'my/order.php'
            ],
            [
                'value' => 'wallet',
                'path'  => 'my/wallet.php'
            ],
            [
                'value' => 'profile',
                'path'  => 'my/profile.php'
            ],
            [
                'value' => 'setting',
                'path'  => 'my/setting.php'
            ],
        ],
    ],

];
