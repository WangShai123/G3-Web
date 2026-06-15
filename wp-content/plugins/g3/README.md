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
- **支付系统** - 支持多种支付方式集成
- **等等**

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
# 在 WordPress 后台搜索 "G3-Web" 插件并安装

# 在 WordPress 后台激活插件
```

### 2. 基础配置

插件激活后会自动：

- 创建必要的数据库表
- 初始化对象缓存（如果启用 Redis）
- 设置 CLI 命令支持

## 🏗️ 项目结构

```
G3-Web
├── assets                  // 插件静态资源目录（开发环境）
├── backup
│   └── options.php         // 卸载插件时, 备份的options数据文件
├── bin                     // 插件相关的命令行脚本
│   ├── supervisor          // supervisor配置文件目录
│   │   └── queue-consumer
│   │   │   ├── g3-queue-worker.conf
│   │   │   └── install.sh
│   ├── systemd             // systemd配置文件目录
│   │   └── queue-consumer
│   │   │   ├── g3-queue-worker@.service
│   │   │   ├── install.sh
│   │   │   └── manage.sh
│   ├── console.php         // symfony console
│   ├── queue-manager.php   // 队列管理脚本
│   ├── queue-worker.php    // 队列工作脚本
│   └── start-workers.sh    // 启动所有工作脚本
├── config
│   ├── aspects.php         // AOP默认配置
│   ├── components.php      // 组件默认配置
│   ├── define.php          // 常量默认配置
│   ├── encrypt.php         // 加密参数默认配置
│   ├── options.php         // 默认options配置
│   ├── queue.php           // 队列默认配置
│   ├── rewriteRouter.php   // rewrite规则默认配置
│   └── whitelist.php       // 白名单默认配置
├── dist                    // 本地静态资源（开发打包）
├── documents               // 文档
├── extensions              // php扩展
│   ├── jealer.so           // 本插件核心扩展，含授权验证，按php版本分录
│   ├── jealer.dll          // 本插件核心扩展，含授权验证，按php版本分录
│   ├── {Extension File}
│   └── {Extension File}
├── library                 // 第三方非composer库目录
│   ├── {Library Folder}
│   └── {Library Folder}
├── public                  // G3-Web内置公共静态资源（生产环境）
│   ├── css
│   ├── fonts
│   ├── images
│   ├── javascript
│   ├── languages
│   ├── audios
│   └── videos
├── src                         // 核心功能源码
│   ├── Core                    // 核心功能
│   │   ├── Aspects                 // 切面
│   │   │   └── Aspects.php
│   │   ├── Attributes              // 注解
│   │   │   ├── Aspects.php
│   │   │   ├── Inject.php
│   │   │   ├── Middleware.php
│   │   │   ├── RestRouter.php
│   │   │   └── Schema.php
│   │   ├── Container               // 容器
│   │   │   ├── ConfigLoader.php
│   │   │   ├── Container.php
│   │   │   ├── ContainerBuilder.php
│   │   │   ├── ContainerExtensionInterface.php
│   │   │   ├── DefinitionInterface.php
│   │   │   ├── ExtensionManager.php
│   │   │   ├── ExtensionManagerInterface.php
│   │   │   ├── FactoryDefinition.php
│   │   │   ├── ParameterManager.php
│   │   │   ├── ParameterManagerInterface.php
│   │   │   ├── Reference.php
│   │   │   ├── ServiceDecorator.php
│   │   │   ├── ServiceDecoratorInterface.php
│   │   │   ├── TagManager.php
│   │   │   ├── TagManagerInterface.php
│   │   │   └── ValueDefinition.php
│   │   ├── Helper
│   │   │   └── Helper.php
│   │   ├── Queue                               // 队列
│   │   │   ├── CronSchedules.php
│   │   │   ├── DatabaseQueue.php
│   │   │   ├── Job.php
│   │   │   ├── Queue.php
│   │   │   ├── QueueCronProcessor.php
│   │   │   ├── QueueInterface.php
│   │   │   └── RedisQueue.php
│   │   ├── Rewrite
│   │   │   └── RewriteRouter.php
│   │   ├── Router
│   │   │   └── Router.php
│   │   ├── Activator.php       // 激活类
│   │   ├── ComponentLoader.php
│   │   ├── Deactivator.php     // 停用类
│   │   └── Loader.php          // 插件加载类
│   ├── Jobs
│   │   └── EmailJob.php
│   ├── Cache                   // 缓存
│   │   ├── EasyWechat.php
│   ├── Commands                // 命令行
│   │   ├── CreateCommand.php
│   │   └── TestCommand.php
│   ├── Components              // 业务组件
│   │   ├── ComponentManager.php
│   │   ├── Components.php
│   │   └── {Component Name}
│   │   │   ├── tests                   // 组件测试目录
│   │   │   ├── views                   // 组件模板目录
│   │   │   ├── widgets                 // 组件widget目录
│   │   │   ├── includes                // 组件功能类
│   │   └── └── {Component Name}.php    // 当前组件类
│   ├── Controllers                     // 控制器
│   │   └── {Controller Folder}
│   ├── Middleware                      // 中间件
│   │   ├── MiddlewareInterface.php
│   │   ├── RateLimitMiddleware.php
│   │   ├── RestAuthMiddleware.php
│   │   ├── RoleMiddleware.php
│   │   ├── SchemaMiddleware.php
│   │   └── WhitelistMiddleware.php
│   ├── Services            // 服务类
│   │   ├── AuthService.php
│   │   ├── DBService.php
│   │   ├── FundService.php
│   │   ├── LogService.php
│   │   ├── MailerService.php
│   │   ├── OrderService.php
│   │   ├── PageService.php
│   │   ├── PaymentService.php
│   │   ├── PostService.php
│   │   ├── ProductService.php
│   │   ├── ShareService.php
│   │   ├── SidebarService.php
│   │   ├── SwiperService.php
│   │   ├── SystemService.php
│   │   ├── TaxonomyService.php
│   │   ├── TemplateService.php
│   │   ├── ThemeGeneratorService.php
│   │   ├── UserService.php
│   │   └── WechatOAService.php
│   ├── Utilities           // 工具类
│   │   ├── Common.php      // 通用工具
│   │   ├── Context.php     // 数据管理器
│   │   ├── Date.php        // 日期处理
│   │   ├── Element.php     // 元素工具
│   │   ├── Event.php       // 事件分发器
│   │   ├── Frontend.php    // 前端工具
│   │   ├── Image.php       // 图像处理
│   │   ├── Message.php     // 消息处理
│   │   ├── Option.php      // 选项处理
│   │   ├── Request.php     // 请求工具
│   │   ├── Response.php    // 响应工具
│   │   ├── RobustEncoder.php // 健壮编码器
│   │   ├── Router.php      // 路由工具
│   │   ├── Session.php     // 会话处理
│   │   ├── System.php      // 系统处理
│   │   └── Validator.php   // 验证工具
├── templates               // 模板目录
├── tests                   // 测试目录
├── vendor                  // composer依赖包目录
├── composer.json
├── composer.lock
└── loader.php              // 插件入口文件
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

期望的主题项目结构：

```
theme/
├── config
│   ├── aop.php
│   ├── components.php
│   ├── define.php
│   ├── encrypt.php
│   └── rewriteRouter.php
├── components // 自定义业务组件
│   └── {Comopnent Folder}
├── src
│   ├── Aspects/
│   ├── Components/
│   ├── Controllers/
│   └── Middleware/
├── assets
├── dist
├── public
├── archive
│   ├── default.php
├── category
│   ├── default.php
├── tag
│   ├── default.php
├── taxonomy
│   ├── default.php
├── single
│   ├── default.php
├── user
│   ├── default.php
├── my
│   ├── default.php
├── editor
│   ├── default.php
├── templates
├── parts
├── functions.php
├── header.php
├── footer.php
├── index.php
├── page.php
├── 404.php
├── style.css
├── screenshot.png
└── readme.txt
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
    protected function hooks(): void {
        // 钩子逻辑
    }
    protected function setting(): void {
        // 后台设置逻辑
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
