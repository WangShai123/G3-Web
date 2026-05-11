# G3 Web - WordPress 主题开发框架

G3 Web 是一个现代化的 WordPress 插件框架，旨在帮助开发者快速构建强大的 WordPress 主题和应用程序。它提供了完整的组件化架构、依赖注入容器、RESTful API 路由系统以及丰富的功能模块。

## 🚀 核心特性

### 架构特性
- **现代化 PHP 8.3+** - 使用最新的 PHP 特性和语法
- **PSR-11 容器** - 符合 PSR-11 标准的依赖注入容器
- **组件化架构** - 模块化的组件系统，支持主题覆盖
- **RESTful API** - 基于注解的 REST API 路由系统
- **中间件支持** - 灵活的中间件机制
- **AOP 编程** - 面向切面编程支持

### 功能模块
- **用户认证系统** - 支持微信登录、社交登录
- **内容管理** - 增强的文章管理、浏览统计、版权保护
- **电商功能** - 商品管理、订单系统、SKU 管理
- **微信集成** - 微信公众号管理、消息处理
- **队列系统** - 异步任务处理
- **缓存系统** - Redis 对象缓存支持
- **安全防护** - 多层安全防护机制

## 📋 系统要求

- **PHP**: >= 8.3
- **WordPress**: >= 6.5
- **Redis**: 推荐用于对象缓存
- **扩展要求**:
  - ext-curl
  - ext-openssl
  - ext-simplexml
  - ext-fileinfo
- **固定链接**: 需要设置为 `%postname%` 结构

## 🛠️ 安装配置

### 1. 插件安装
```bash
# 将插件文件放置到 WordPress 插件目录
wp-content/plugins/g3/

# 在 WordPress 后台激活插件
```

### 2. 依赖安装
```bash
cd wp-content/plugins/g3/
composer install
```

### 3. 基础配置
插件激活后会自动：
- 创建必要的数据库表
- 初始化对象缓存（如果启用 Redis）
- 设置 CLI 命令支持

## 🏗️ 项目结构

```
g3/
├── src/                    # 核心源代码
│   ├── Components/         # 功能组件
│   │   ├── Auth/          # 用户认证
│   │   ├── Post/          # 内容管理
│   │   ├── Product/       # 商品管理
│   │   ├── WechatOA/      # 微信公众号
│   │   └── ...
│   ├── Services/          # 业务服务层
│   ├── Controllers/       # REST API 控制器
│   ├── Middleware/        # 中间件
│   ├── Utilities/         # 工具类
│   ├── Container/         # 依赖注入容器
│   ├── Queue/            # 队列系统
│   └── Attributes/       # 注解类
├── config/               # 配置文件
├── templates/           # 模板文件
├── public/             # 静态资源
├── vendor/             # Composer 依赖
├── tests/              # 测试文件
└── loader.php          # 插件入口文件
```

## 🔧 核心架构

### 1. 依赖注入容器

G3 使用符合 PSR-11 标准的依赖注入容器：

```php
// 获取容器实例
$container = \JEALER\G3\Container::run();

// 注册服务
$container->setRawDefinition('myService', MyService::class);

// 获取服务实例
$service = $container->get('myService');

// 使用静态方法快速获取
$service = \JEALER\G3\Container::use(MyService::class);
```

### 2. 组件系统

组件是 G3 的核心功能模块，支持主题覆盖：

```php
// 组件配置文件: config/components.php
return [
    'enabled' => true,
    'components' => [
        'Auth' => true,      // 启用用户认证组件
        'Post' => true,      // 启用内容管理组件
        'Product' => true,   // 启用商品管理组件
        // ...
    ]
];
```

组件文件结构：

```
src/Components/ComponentName/
├── ComponentName.php           # 主组件类
├── widgets/                    # 小工具文件
├── views/                      # 模板文件
├── includes/                   # 附加功能文件
└── tests/                      # 测试文件
```

### 3. REST API 路由

使用注解定义 REST API 路由：

```php
use JEALER\G3\Attributes\RestRouter;
use JEALER\G3\Attributes\Middleware;
use JEALER\G3\Attributes\Schema;

class AuthController {
    #[RestRouter(
        namespace: 'g3/v1',
        route: '/auth/login',
        methods: 'POST'
    )]
    #[Middleware(RateLimitMiddleware::class, [10, 60])]
    #[Schema([
        'username' => ['type' => 'string', 'required' => true],
        'password' => ['type' => 'string', 'required' => true]
    ])]
    public function login(WP_REST_Request $request) {
        // 登录逻辑
    }
}
```

### 4. 中间件系统

支持灵活的中间件机制：

```php
class RateLimitMiddleware implements MiddlewareInterface {
    public function handle(WP_REST_Request $request): bool {
        // 限流逻辑
        return true; // 继续执行
    }
}
```

### 5. 队列系统

支持异步任务处理：

```php
use JEALER\G3\Queue\Job;

class EmailJob extends Job {
    public function handle() {
        // 发送邮件逻辑
    }
}

// 添加任务到队列
\JEALER\G3\Queue::push(new EmailJob($data));
```

## 📦 主要组件介绍

### Auth 组件 - 用户认证
- 支持微信公众号登录
- 微信客户端自动登录
- 社交登录集成
- 用户权限管理

### Post 组件 - 内容管理
- 文章浏览统计
- 版权保护（隐形水印）
- 自动版权声明
- 分类封面管理
- 自定义交互数据

### Product 组件 - 商品管理
- SKU 管理
- 商品属性
- 库存管理
- 价格体系

### WechatOA 组件 - 微信公众号
- 菜单管理
- 消息处理
- 自动回复
- 关键词匹配

### Order 组件 - 订单系统
- 订单管理
- 支付集成
- 物流跟踪
- 订单状态

## 🎨 主题开发

### 1. 主题结构
```
your-theme/
├── src/
│   ├── Components/     # 覆盖插件组件
│   └── Controllers/    # 自定义控制器
├── config/
│   └── components.php  # 组件配置覆盖
├── templates/          # 模板文件
└── public/            # 静态资源
```

### 2. 组件覆盖
在主题中创建同名组件文件即可覆盖插件组件：

```php
// 主题中: src/Components/Auth/Auth.php
namespace JEALER\G3\Components;

class Auth extends \JEALER\G3\Components {
    // 自定义实现
}
```

### 3. 配置覆盖
```php
// 主题中: config/components.php
return [
    'enabled' => true,
    'components' => [
        'Auth' => true,
        'CustomComponent' => true, // 主题专有组件
    ]
];
```

## 🔌 扩展开发

### 1. 创建自定义组件
```php
namespace JEALER\G3\Components;

class CustomComponent extends Components {
    protected function init(): void {
        // 初始化逻辑
    }
    
    protected function admin(): void {
        // 后台管理逻辑
    }
    
    protected function adminMenu(): void {
        // 添加管理菜单
    }
}
```

### 2. 创建 REST API 控制器
```php
namespace JEALER\G3\Controllers;

class CustomController {
    #[RestRouter(
        namespace: 'custom/v1',
        route: '/data',
        methods: 'GET'
    )]
    public function getData(WP_REST_Request $request) {
        return rest_ensure_response(['data' => 'custom data']);
    }
}
```

### 3. 创建服务类
```php
namespace JEALER\G3\Services;

class CustomService {
    public function processData($data) {
        // 业务逻辑
        return $processedData;
    }
}
```

## 🛡️ 安全特性

- **输入验证** - 自动参数验证和清理
- **权限控制** - 基于角色的访问控制
- **限流保护** - API 请求频率限制
- **CSRF 防护** - 跨站请求伪造防护
- **SQL 注入防护** - 使用 WordPress 数据库 API
- **XSS 防护** - 输出转义和内容过滤

## 📊 性能优化

- **对象缓存** - Redis 缓存支持
- **数据库优化** - 查询优化和索引
- **静态资源** - 资源压缩和合并
- **延迟加载** - 组件按需加载
- **队列处理** - 异步任务处理

## 🔧 CLI 命令

G3 提供了丰富的 CLI 命令：

```bash
# 创建组件
php g3.php create:component ComponentName

# 队列处理
php g3.php queue:work

# 测试命令
php g3.php test:performance
```

## 📝 配置选项

### 主要配置文件

1. **config/define.php** - 常量定义
2. **config/components.php** - 组件配置
3. **composer.json** - 依赖管理

### 环境变量
```php
// 调试模式
define('WP_DEBUG', true);

// Redis 配置
define('WP_REDIS_HOST', 'localhost');
define('WP_REDIS_PORT', 6379);
```

## 🤝 贡献指南

1. Fork 项目
2. 创建功能分支
3. 提交更改
4. 推送到分支
5. 创建 Pull Request

## 📄 许可证

本项目采用 MIT 许可证 - 查看 [LICENSE](license.txt) 文件了解详情。

## 🆘 支持

- **官方网站**: https://www.jealer.com/g3-system/
- **文档**: https://www.jealer.com/g3-system/docs/
- **赞助**: https://www.jealer.com/sponsor/

## 🏷️ 版本信息

- **当前版本**: 1.0.0
- **最低 PHP 版本**: 8.3
- **最低 WordPress 版本**: 6.5
- **作者**: Wang Shai (JEALER)
- **邮箱**: biz@jealer.com

---

**G3 Web** - 让 WordPress 主题开发更简单、更强大！
