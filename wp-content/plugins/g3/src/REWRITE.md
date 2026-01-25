# G3 URL重写系统文档

G3 URL重写系统是一个强大而灵活的URL路由解决方案，它简化了WordPress的重写规则配置，支持复杂的URL模式匹配、模板分发和依赖管理。最重要的是，它支持主题级别的配置覆盖，让开发者能够在主题中自定义URL结构。

## 🚀 核心特性

### 架构特性
- **配置驱动** - 通过简单的PHP数组配置复杂的URL重写规则
- **主题优先** - 主题配置自动覆盖插件默认配置
- **智能模板分发** - 根据URL参数自动选择合适的模板文件
- **依赖管理** - 支持基于组件状态的条件路由
- **自动优化** - 智能检测配置变化并自动刷新重写规则

### 功能特性
- **多参数支持** - 单个URL可以捕获多个参数
- **优先级模板** - 根据参数值选择不同的模板文件
- **模板继承** - 主题模板优先于插件模板
- **开发友好** - 开发模式下自动刷新规则
- **生产优化** - 生产环境下智能缓存机制

## 📋 系统要求

- **WordPress**: >= 6.5
- **PHP**: >= 8.3
- **固定链接**: 必须启用（非默认结构）
- **主题支持**: 支持自定义模板的主题

## 🏗️ 架构设计

### 核心组件

```
Rewrite System/
├── Rewrite.php                    # 核心重写类
├── config/rewriteRouter.php       # 插件默认配置
├── templates/                     # 插件模板目录
└── [theme]/
    ├── config/rewriteRouter.php   # 主题配置（覆盖插件配置）
    └── templates/                 # 主题模板目录（优先级更高）
```

### 工作流程

1. **配置加载** - 加载插件配置，然后加载主题配置覆盖
2. **规则注册** - 根据配置注册WordPress重写规则
3. **查询变量** - 自动注册所需的查询变量
4. **模板分发** - 根据匹配的URL选择合适的模板
5. **智能缓存** - 基于配置哈希的智能缓存机制

## 🔧 配置格式

### 基本配置结构

```php
<?php
// config/rewriteRouter.php
return [
    'url_pattern' => [
        'var'        => 'query_var_name',      // 查询变量名
        'path'       => 'template/path.php',   // 默认模板路径
        'priority'   => [...],                 // 优先级模板（可选）
        'dependency' => 'ComponentName',       // 依赖条件（可选）
    ],
    // 更多规则...
];
```

### 配置参数详解

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| `url_pattern` | string | ✅ | URL正则表达式，必须以 `/?$` 结尾 |
| `var` | string/array | ✅ | 查询变量名，支持单个或多个 |
| `path` | string | ✅ | 默认模板文件路径（相对于templates目录） |
| `priority` | array | ❌ | 优先级模板配置 |
| `dependency` | mixed | ❌ | 依赖条件（组件名/回调函数/布尔值） |

## 📝 配置示例

### 1. 基本单参数路由

```php
// 匹配: /user/123
'user/([^/]+)/?$' => [
    'var'  => 'user_id',
    'path' => 'user/profile.php',
],
```

**生成的重写规则**：
- URL: `user/123`
- 查询变量: `user_id=123`
- 模板: `templates/user/profile.php`

### 2. 多参数路由

```php
// 匹配: /product/electronics/smartphone
'product/([^/]+)/([^/]+)/?$' => [
    'var'  => ['category', 'product_name'],
    'path' => 'product/detail.php',
],
```

**生成的重写规则**：
- URL: `product/electronics/smartphone`
- 查询变量: `category=electronics&product_name=smartphone`
- 模板: `templates/product/detail.php`

### 3. 优先级模板路由

```php
// 匹配: /user/login, /user/register, /user/profile
'user/([^/]+)/?$' => [
    'var'      => 'user_action',
    'path'     => 'user/default.php',        // 默认模板
    'priority' => [
        [
            'value' => 'login',
            'path'  => 'user/login.php'      // 登录专用模板
        ],
        [
            'value' => 'register',
            'path'  => 'user/register.php'   // 注册专用模板
        ],
        [
            'value' => 'profile',
            'path'  => 'user/profile.php'    // 个人资料模板
        ]
    ]
],
```

**模板选择逻辑**：
- `/user/login` → `templates/user/login.php`
- `/user/register` → `templates/user/register.php`
- `/user/settings` → `templates/user/default.php`（未匹配优先级规则）

### 4. 依赖条件路由

```php
// 仅在Security组件启用时生效
'admin/([^/]+)/?$' => [
    'var'        => 'admin_action',
    'path'       => 'admin/dashboard.php',
    'dependency' => 'Security',  // 依赖Security组件
],

// 使用回调函数检查依赖
'api/([^/]+)/?$' => [
    'var'        => 'api_endpoint',
    'path'       => 'api/handler.php',
    'dependency' => function() {
        return get_option('api_enabled', false);
    },
],
```

### 5. 复杂示例：电商产品页面

```php
// 产品详情页：/shop/category/product-name
'shop/([^/]+)/([^/]+)/?$' => [
    'var'      => ['product_category', 'product_slug'],
    'path'     => 'shop/product.php',
    'priority' => [
        [
            'value' => 'digital',
            'path'  => 'shop/digital-product.php'  // 数字产品专用模板
        ],
        [
            'value' => 'physical',
            'path'  => 'shop/physical-product.php' // 实体产品专用模板
        ]
    ],
    'dependency' => 'Product',  // 依赖Product组件
],
```

## 🎨 主题配置覆盖

### 配置优先级

G3重写系统的一个重要特性是**主题配置优先**：

1. **插件默认配置** - `/wp-content/plugins/g3/config/rewriteRouter.php`
2. **主题配置覆盖** - `/wp-content/themes/your-theme/config/rewriteRouter.php`

### 主题配置示例

```php
<?php
// 主题配置文件: your-theme/config/rewriteRouter.php
return [
    // 覆盖插件的用户路由配置
    'user/([^/]+)/?$' => [
        'var'      => 'user_action',
        'path'     => 'custom-user/main.php',    // 使用主题自定义模板
        'priority' => [
            [
                'value' => 'login',
                'path'  => 'custom-user/login.php'
            ],
            [
                'value' => 'dashboard',
                'path'  => 'custom-user/dashboard.php'
            ]
        ]
    ],
    
    // 添加主题专有的路由
    'portfolio/([^/]+)/?$' => [
        'var'  => 'portfolio_item',
        'path' => 'portfolio/single.php',
    ],
    
    // 条件路由：仅在特定页面模板时启用
    'special/([^/]+)/?$' => [
        'var'        => 'special_page',
        'path'       => 'special/handler.php',
        'dependency' => function() {
            return is_page_template('page-special.php');
        }
    ]
];
```

### 模板文件优先级

模板文件的查找顺序：

1. **主题模板** - `/wp-content/themes/your-theme/templates/`
2. **插件模板** - `/wp-content/plugins/g3/templates/`

```php
// 模板查找示例
// 配置: 'path' => 'user/profile.php'

// 1. 首先查找主题模板
$themeTemplate = get_stylesheet_directory() . '/templates/user/profile.php';

// 2. 如果主题模板不存在，使用插件模板
$pluginTemplate = WP_PLUGIN_DIR . '/g3/templates/user/profile.php';
```

## 🔄 模板开发

### 模板文件结构

```
templates/
├── user/
│   ├── login.php           # 用户登录页面
│   ├── register.php        # 用户注册页面
│   └── profile.php         # 用户资料页面
├── product/
│   ├── list.php           # 产品列表页面
│   ├── detail.php         # 产品详情页面
│   └── category.php       # 产品分类页面
└── api/
    ├── handler.php        # API处理器
    └── auth.php          # API认证
```

### 模板文件示例

#### 用户登录模板

```php
<?php
// templates/user/login.php
get_header();

// 获取URL参数
$user_action = get_query_var('user_action'); // 'login'

// 检查是否已登录
if (is_user_logged_in()) {
    wp_redirect(home_url('/user/profile'));
    exit;
}
?>

<div class="user-login-page">
    <h1>用户登录</h1>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="error-message">
            登录失败，请检查用户名和密码。
        </div>
    <?php endif; ?>
    
    <form method="post" action="<?php echo wp_login_url(); ?>">
        <div class="form-group">
            <label for="user_login">用户名或邮箱</label>
            <input type="text" name="log" id="user_login" required>
        </div>
        
        <div class="form-group">
            <label for="user_pass">密码</label>
            <input type="password" name="pwd" id="user_pass" required>
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" name="rememberme" value="forever">
                记住我
            </label>
        </div>
        
        <button type="submit">登录</button>
        
        <p>
            还没有账户？<a href="<?php echo home_url('/user/register'); ?>">立即注册</a>
        </p>
    </form>
</div>

<?php get_footer(); ?>
```

#### 产品详情模板

```php
<?php
// templates/product/detail.php
get_header();

// 获取URL参数
$category = get_query_var('product_category');    // 'electronics'
$product_slug = get_query_var('product_slug');    // 'smartphone'

// 根据参数查询产品
$product = get_posts([
    'post_type' => 'product',
    'name' => $product_slug,
    'meta_query' => [
        [
            'key' => 'product_category',
            'value' => $category,
            'compare' => '='
        ]
    ]
]);

if (empty($product)) {
    // 产品不存在，显示404
    global $wp_query;
    $wp_query->set_404();
    status_header(404);
    get_template_part('404');
    exit;
}

$product = $product[0];
setup_postdata($product);
?>

<div class="product-detail">
    <div class="product-breadcrumb">
        <a href="<?php echo home_url(); ?>">首页</a> > 
        <a href="<?php echo home_url('/shop/' . $category); ?>"><?php echo ucfirst($category); ?></a> > 
        <?php echo get_the_title($product); ?>
    </div>
    
    <div class="product-content">
        <div class="product-images">
            <?php echo get_the_post_thumbnail($product, 'large'); ?>
        </div>
        
        <div class="product-info">
            <h1><?php echo get_the_title($product); ?></h1>
            <div class="product-price">
                ¥<?php echo get_post_meta($product->ID, 'price', true); ?>
            </div>
            <div class="product-description">
                <?php echo get_the_content(null, false, $product); ?>
            </div>
            
            <form class="add-to-cart-form" method="post">
                <input type="hidden" name="product_id" value="<?php echo $product->ID; ?>">
                <button type="submit" name="add_to_cart">加入购物车</button>
            </form>
        </div>
    </div>
</div>

<?php
wp_reset_postdata();
get_footer();
?>
```

### 在模板中获取参数

```php
// 获取单个参数
$user_id = get_query_var('user_id');

// 获取多个参数
$category = get_query_var('category');
$product_name = get_query_var('product_name');

// 参数验证
if (empty($user_id) || !is_numeric($user_id)) {
    // 参数无效，处理错误
    wp_redirect(home_url());
    exit;
}

// 使用参数查询数据
$user = get_user_by('ID', $user_id);
if (!$user) {
    // 用户不存在，显示404
    global $wp_query;
    $wp_query->set_404();
    status_header(404);
    get_template_part('404');
    exit;
}
```

## ⚙️ 高级功能

### 1. 动态依赖检查

```php
// 基于用户权限的路由
'admin/([^/]+)/?$' => [
    'var'        => 'admin_page',
    'path'       => 'admin/dashboard.php',
    'dependency' => function() {
        return current_user_can('manage_options');
    }
],

// 基于插件状态的路由
'woocommerce/([^/]+)/?$' => [
    'var'        => 'wc_page',
    'path'       => 'woocommerce/handler.php',
    'dependency' => function() {
        return class_exists('WooCommerce');
    }
],
```

### 2. 复杂参数处理

```php
// 支持可选参数的路由
'blog/([^/]+)(?:/([^/]+))?/?$' => [
    'var'  => ['blog_category', 'blog_page'],
    'path' => 'blog/archive.php',
],

// 匹配示例:
// /blog/technology → category='technology', page=''
// /blog/technology/2 → category='technology', page='2'
```

### 3. 条件模板选择

```php
'shop/([^/]+)/?$' => [
    'var'      => 'shop_category',
    'path'     => 'shop/default.php',
    'priority' => [
        [
            'value' => function($category) {
                // 检查是否为数字产品分类
                $digital_categories = ['software', 'ebooks', 'courses'];
                return in_array($category, $digital_categories);
            },
            'path'  => 'shop/digital.php'
        ]
    ]
],
```

## 🔍 调试和开发

### 开发模式特性

在开发模式下（`WP_DEBUG = true`），G3重写系统提供额外的调试功能：

```php
// 自动检查和修复重写规则
add_action('parse_request', [$rewrite, 'checkAndFixRewriteRules'], 1);

// 配置变化时自动刷新规则
private function checkIfFlushNeeded(): void {
    $storedHash = get_transient('g3_rewrite_config_hash');
    $currentHash = md5(serialize($this->config));
    
    if ($storedHash !== $currentHash) {
        set_transient('g3_rewrite_config_hash', $currentHash, 24 * HOUR_IN_SECONDS);
        self::flushRewriteRules();
    }
}
```

### 调试技巧

#### 1. 查看当前重写规则

```php
// 在functions.php中添加调试代码
add_action('init', function() {
    if (current_user_can('manage_options') && isset($_GET['debug_rewrite'])) {
        global $wp_rewrite;
        echo '<pre>';
        print_r($wp_rewrite->rules);
        echo '</pre>';
        exit;
    }
});

// 访问: yoursite.com/?debug_rewrite=1
```

#### 2. 检查查询变量

```php
// 在模板文件中添加调试信息
if (WP_DEBUG) {
    echo '<pre>Query Vars: ';
    print_r($wp_query->query_vars);
    echo '</pre>';
}
```

#### 3. 验证模板路径

```php
// 在模板文件开头添加
if (WP_DEBUG) {
    echo '<!-- Template: ' . __FILE__ . ' -->';
}
```

### 常见问题排查

#### 1. 重写规则不生效

```php
// 手动刷新重写规则
add_action('init', function() {
    if (isset($_GET['flush_rewrite'])) {
        flush_rewrite_rules();
        echo 'Rewrite rules flushed!';
        exit;
    }
});

// 访问: yoursite.com/?flush_rewrite=1
```

#### 2. 模板文件找不到

```php
// 检查模板文件路径
$template_paths = [
    get_stylesheet_directory() . '/templates/user/login.php',
    WP_PLUGIN_DIR . '/g3/templates/user/login.php'
];

foreach ($template_paths as $path) {
    if (file_exists($path)) {
        echo "Found: $path\n";
    } else {
        echo "Missing: $path\n";
    }
}
```

#### 3. 依赖条件不满足

```php
// 调试依赖检查
private function isDependencySatisfied($dependency): bool {
    $result = /* 依赖检查逻辑 */;
    
    if (WP_DEBUG) {
        error_log("Dependency check: " . var_export($dependency, true) . " = " . var_export($result, true));
    }
    
    return $result;
}
```

## 🚀 性能优化

### 1. 配置缓存

G3重写系统使用智能缓存机制：

```php
// 基于配置哈希的24小时缓存
$currentHash = md5(serialize($this->config));
set_transient('g3_rewrite_config_hash', $currentHash, 24 * HOUR_IN_SECONDS);
```

### 2. 规则验证优化

```php
// 只在必要时刷新规则
private function verifyRewriteRules(): bool {
    global $wp_rewrite;
    
    if (!isset($wp_rewrite->rules) || !is_array($wp_rewrite->rules)) {
        return false;
    }
    
    // 快速验证关键规则
    foreach ($this->config as $url => $route) {
        if (!isset($wp_rewrite->rules[$url])) {
            return false;
        }
    }
    
    return true;
}
```

### 3. 模板缓存

```php
// 在模板中使用对象缓存
$cache_key = 'product_' . $product_slug . '_' . $category;
$product_data = wp_cache_get($cache_key);

if (false === $product_data) {
    $product_data = /* 查询产品数据 */;
    wp_cache_set($cache_key, $product_data, '', 3600); // 1小时缓存
}
```

## 📚 最佳实践

### 1. 配置组织

```php
// 按功能模块组织配置
return [
    // 用户相关路由
    'user/([^/]+)/?$' => [...],
    'profile/([^/]+)/?$' => [...],
    
    // 产品相关路由
    'shop/([^/]+)/?$' => [...],
    'product/([^/]+)/([^/]+)/?$' => [...],
    
    // API相关路由
    'api/v1/([^/]+)/?$' => [...],
    'webhook/([^/]+)/?$' => [...],
];
```

### 2. 命名规范

```php
// 使用清晰的变量名
'user/([^/]+)/?$' => [
    'var' => 'user_action',  // 而不是 'ua' 或 'action'
    'path' => 'user/handler.php',
],

// 使用描述性的模板路径
'path' => 'user/login-form.php',  // 而不是 'user/l.php'
```

### 3. 错误处理

```php
// 在模板中添加错误处理
$user_id = get_query_var('user_id');

if (empty($user_id)) {
    wp_redirect(home_url());
    exit;
}

if (!is_numeric($user_id)) {
    global $wp_query;
    $wp_query->set_404();
    status_header(404);
    get_template_part('404');
    exit;
}
```

### 4. SEO友好

```php
// 设置正确的页面标题
add_filter('wp_title', function($title) {
    $user_action = get_query_var('user_action');
    
    if ($user_action === 'login') {
        return '用户登录 - ' . get_bloginfo('name');
    }
    
    return $title;
});

// 设置正确的meta描述
add_action('wp_head', function() {
    $product_slug = get_query_var('product_slug');
    
    if ($product_slug) {
        $product = /* 获取产品信息 */;
        echo '<meta name="description" content="' . esc_attr($product->description) . '">';
    }
});
```

## 🔧 迁移指南

### 从WordPress默认重写规则迁移

#### 原有方式
```php
// 传统WordPress重写规则
add_action('init', function() {
    add_rewrite_rule(
        '^user/([^/]+)/?',
        'index.php?user_action=$matches[1]',
        'top'
    );
    
    add_rewrite_tag('%user_action%', '([^&]+)');
});
```

#### G3方式
```php
// G3配置方式
'user/([^/]+)/?$' => [
    'var'  => 'user_action',
    'path' => 'user/handler.php',
],
```

### 从其他路由系统迁移

#### 从自定义路由系统
```php
// 原有自定义路由
$router->add('user/{action}', 'UserController@handle');

// 转换为G3配置
'user/([^/]+)/?$' => [
    'var'  => 'user_action',
    'path' => 'user/controller.php',
],
```

## 🛡️ 安全考虑

### 1. 参数验证

```php
// 在模板中验证参数
$user_id = get_query_var('user_id');

// 验证参数类型
if (!is_numeric($user_id)) {
    wp_die('Invalid user ID');
}

// 验证参数范围
if ($user_id < 1 || $user_id > 999999) {
    wp_die('User ID out of range');
}

// 验证用户权限
if (!current_user_can('edit_user', $user_id)) {
    wp_die('Permission denied');
}
```

### 2. 防止路径遍历

```php
// 安全的模板路径处理
$template_file = sanitize_file_name($route['path']);
$template_path = get_stylesheet_directory() . '/templates/' . $template_file;

// 确保路径在允许的目录内
$allowed_dir = realpath(get_stylesheet_directory() . '/templates/');
$actual_path = realpath($template_path);

if (strpos($actual_path, $allowed_dir) !== 0) {
    wp_die('Invalid template path');
}
```

### 3. 输入过滤

```php
// 在模板中过滤输出
$category = sanitize_text_field(get_query_var('category'));
$product_name = sanitize_title(get_query_var('product_name'));

echo '<h1>' . esc_html($category) . '</h1>';
echo '<p>Product: ' . esc_html($product_name) . '</p>';
```

---

**G3 URL重写系统** - 让WordPress URL路由更简单、更强大、更灵活！

## 📞 技术支持

- **文档**: 查看完整的 G3 文档
- **问题反馈**: 通过 GitHub Issues 报告问题
- **社区支持**: 加入 G3 开发者社区

---

*最后更新: 2024年*