<?php
/**
 * Default Rewrite Config
 * 默认 rewrite 配置
 * 
 * 配置格式:
 * 'url_pattern' => [
 *     'var'  => 'query_var_name',     // 单个query var
 *     'var'  => ['var1', 'var2'],     // 多个query vars
 *     'path' => 'template_file_path',
 * ]
 * 注意1: url_pattern配置需要以 /?$ 结尾
 * 注意2: var 配置类型为 string 或 array，path 配置类型为 string
 * 注意3: var 为 string 代表一个查询参数，为 array 代表多个查询参数
 * 注意4: 多条配置时，不能有相同的 var 或 path，否则仅第一条配置生效
 * 注意5: 用户主题配置优先级高于系统默认配置，会覆盖插件中系统默认配置
 * 注意6: 配置完成后，系统会自动注册 query var 并自动刷新 rewrite 规则
 * 
 * @return array
 * @since 1.0.0
 * @author Wang Shai
 */
return [
    // 单个query var示例
    'test/([^/]+)/?$'                  => [
        'var'  => 'test_id',
        'path' => 'test/index.php',
    ],

    // 多个query vars示例
    'test/multiple/([^/]+)/([^/]+)/?$' => [
        'var'  => ['the_first_id', 'the_second_id'],
        'path' => 'test/multiple.php',
    ],
];