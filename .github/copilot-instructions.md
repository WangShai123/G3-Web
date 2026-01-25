# GitHub Copilot Instructions for G3 Web Plugin

## 项目概述

G3 Web 是一个现代化的 WordPress 插件框架，专为主题开发而设计。它提供了完整的组件化架构、依赖注入容器、RESTful API 路由系统以及丰富的功能模块。

## 核心架构

### 1. 组件化架构
- 所有功能模块都以组件形式组织
- 组件位于 `src/Components/` 目录下
- 每个组件继承自 `Components` 基类
- 支持主题级覆盖

### 2. 依赖注入容器
- 使用 PSR-11 标准的容器
- 服务注册在 `Helper::registerServices()` 中
- 通过 `Container::use(Class::class)` 获取实例

### 3. REST API 路由
- 使用注解 `@RestRouter` 定义路由
- 控制器位于 `src/Controllers/` 目录
- 支持中间件和参数验证

### 4. 服务层
- 业务逻辑在 `src/Services/` 中
- 每个服务对应一个组件
- 使用依赖注入管理依赖

## 代码规范

### PHP 版本要求
- 最低 PHP 8.3+
- 使用现代 PHP 特性：类型声明、属性、枚举等

### 命名规范
- 类名：PascalCase
- 方法名：camelCase
- 属性名：camelCase
- 常量：UPPER_SNAKE_CASE
- 文件名：与类名一致

### 代码风格
- 使用 PSR-12 代码风格
- 使用类型声明和返回值类型
- 使用属性而不是 getter/setter（除非必要）
- 使用命名空间和自动加载

### 注释规范
- 使用 PHPDoc 注释
- 包含 @since, @author, @param, @return 等标签
- 方法注释描述功能和参数

## 开发指南

### 创建新组件
1. 在 `src/Components/` 下创建组件目录
2. 创建主类文件继承 `Components`
3. 实现 `init()`, `admin()`, `adminMenu()` 方法
4. 在 `config/components.php` 中启用

### 创建 REST API
1. 在 `src/Controllers/` 下创建控制器
2. 使用 `@RestRouter` 注解定义路由
3. 使用 `@Middleware` 添加中间件
4. 使用 `@Schema` 验证参数

### 创建服务
1. 在 `src/Services/` 下创建服务类
2. 在容器中注册服务
3. 使用依赖注入获取其他服务

### 使用队列
1. 创建 Job 类继承 `Job`
2. 实现 `handle()` 方法
3. 使用 `Queue::push()` 添加到队列

## 安全注意事项

- 始终验证和清理用户输入
- 使用 WordPress 数据库 API 防止 SQL 注入
- 对输出进行转义防止 XSS
- 使用权限检查保护敏感操作
- 实施速率限制防止滥用

## 性能优化

- 使用对象缓存（Redis）
- 优化数据库查询
- 延迟加载组件
- 使用异步队列处理重任务
- 压缩静态资源

## 测试

- 单元测试位于 `tests/` 目录
- 使用 PHPUnit 框架
- 测试覆盖核心功能
- 运行 `composer test` 执行测试

## 部署

- 使用 Composer 管理依赖
- 确保 PHP 8.3+ 和 WordPress 6.5+
- 配置 Redis 用于缓存
- 设置固定链接为 `%postname%`

## 贡献

- 遵循上述代码规范
- 为新功能编写测试
- 更新文档
- 提交 PR 进行代码审查

## 常用命令

```bash
# 安装依赖
composer install

# 运行测试
composer test

# 创建组件
php g3.php create:component ComponentName

# 处理队列
php g3.php queue:work
```

## 架构模式

- **MVC 模式**: 控制器处理请求，服务处理业务逻辑，组件管理 UI
- **依赖注入**: 通过容器管理对象依赖
- **观察者模式**: 使用 WordPress 钩子系统
- **策略模式**: 可插拔的组件和中间件
- **工厂模式**: 服务和组件的创建

## 最佳实践

- 保持组件松耦合
- 使用接口定义契约
- 编写可测试的代码
- 遵循 SOLID 原则
- 使用异常处理错误
- 记录重要操作日志

---

**记住**: G3 Web 的目标是让 WordPress 开发更简单、更强大。始终优先考虑用户体验和代码质量。