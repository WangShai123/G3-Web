# G3 Queue Test

## 概述

这个测试文件演示了如何使用G3队列系统推送EmailJob任务。

## 文件说明

### test.php
- 实例化Queue对象
- 创建EmailJob任务数据
- 推送任务到队列
- 显示执行结果

## 使用方法

### 1. 启用测试

测试代码已经在 `loader.php` 中引入，只在调试模式下执行：

```php
if (defined('WP_DEBUG') && WP_DEBUG) {
    require_once __DIR__ . '/src/Queue/tests/test.php';
}
```

### 2. 启用调试模式

在 `wp-config.php` 中确保启用了调试模式：

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### 3. 查看结果

#### 管理员通知
- 登录WordPress管理后台
- 如果任务推送成功，会显示绿色通知
- 如果任务推送失败，会显示红色错误通知

#### 错误日志
查看WordPress错误日志文件，会看到类似信息：
```
[G3] EmailJob pushed to queue successfully. Job ID: 123
[G3] Current queue size: 1
[G3] Queue driver: database
```

### 4. 处理队列任务

使用队列工作器处理任务：

```bash
# 进入插件bin目录
cd wp-content/plugins/g3/bin

# 处理队列任务
php queue-worker.php --stop-when-empty --verbose
```

### 5. 查看队列状态

```bash
# 查看队列状态
php queue-manager.php status

# 查看驱动信息
php queue-manager.php info
```

## 测试数据

测试会创建一个EmailJob任务，包含以下数据：

```php
$emailData = [
    'to' => 'test@example.com',
    'subject' => 'G3队列系统测试邮件 - 2024-01-23 10:30:00',
    'message' => '这是一封来自G3队列系统的测试邮件...',
    'template' => 'default',
    'headers' => [
        'From: G3系统 <noreply@example.com>',
        'Content-Type: text/html; charset=UTF-8'
    ]
];
```

## 安全注意事项

1. **仅调试模式执行**：测试代码只在 `WP_DEBUG` 为 `true` 时执行
2. **避免重复执行**：使用静态变量防止重复执行
3. **管理员权限**：通知只对有管理权限的用户显示
4. **生产环境**：生产环境中应该移除或禁用此测试

## 移除测试

生产环境中，可以通过以下方式移除测试：

### 方法1：注释掉loader.php中的引入
```php
// if (defined('WP_DEBUG') && WP_DEBUG) {
//     require_once __DIR__ . '/src/Queue/tests/test.php';
// }
```

### 方法2：删除测试文件
```bash
rm wp-content/plugins/g3/src/Queue/tests/test.php
```

### 方法3：禁用调试模式
在 `wp-config.php` 中：
```php
define('WP_DEBUG', false);
```

## 故障排除

### 任务推送失败
1. 检查队列配置文件 `/config/queue.php`
2. 确保数据库表已创建
3. 检查Redis连接（如果使用Redis驱动）
4. 查看错误日志获取详细信息

### 通知不显示
1. 确保以管理员身份登录
2. 确保在管理后台页面
3. 检查是否启用了调试模式

### 队列任务不执行
1. 确保EmailJob类存在
2. 检查任务类的命名空间
3. 使用队列工作器手动处理任务
4. 检查任务的handle方法是否正确实现