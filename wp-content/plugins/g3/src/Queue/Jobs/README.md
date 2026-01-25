# G3 Queue Jobs

这个目录包含了G3队列系统的生产任务类。

## EmailJob

专门用于处理邮件发送的队列任务类。

### 基本使用

#### 1. 发送单个邮件

```php
use JEALER\G3\Queue\Jobs\EmailJob;

// 基本邮件发送
EmailJob::send(
    'user@example.com',
    'Welcome to our site!',
    '<h1>Welcome!</h1><p>Thank you for joining us.</p>'
);

// 带自定义头和附件的邮件
EmailJob::send(
    'user@example.com',
    'Your Invoice',
    '<h1>Invoice</h1><p>Please find your invoice attached.</p>',
    ['Reply-To: noreply@example.com'],
    ['/path/to/invoice.pdf'],
    300  // 延迟5分钟发送
);
```

#### 2. 批量发送邮件

```php
// 批量发送给多个收件人
$recipients = ['user1@example.com', 'user2@example.com', 'user3@example.com'];
EmailJob::sendBatch(
    $recipients,
    'Newsletter',
    '<h1>Monthly Newsletter</h1><p>Here is our latest news...</p>'
);
```

#### 3. 使用邮件模板

```php
// 发送欢迎邮件模板
EmailJob::sendTemplate(
    'newuser@example.com',
    'welcome',  // 模板名称
    [
        'user_name' => 'John Doe',
        'site_name' => 'My Website',
        'login_url' => 'https://example.com/login'
    ]
);

// 发送通知邮件模板
EmailJob::sendTemplate(
    'admin@example.com',
    'notification',
    [
        'title' => 'New User Registration',
        'message' => 'A new user has registered on your website.',
        'action_url' => 'https://example.com/admin/users',
        'action_text' => 'View Users'
    ]
);
```

### 命令行使用

#### 发送单个邮件

```bash
# 基本邮件发送
php bin/queue-manager.php email --to=user@example.com --subject="Test Email" --message="Hello World"

# 延迟发送
php bin/queue-manager.php email --to=user@example.com --subject="Delayed Email" --delay=3600

# 查看队列状态
php bin/queue-manager.php status

# 处理邮件队列
php bin/queue-worker.php --stop-when-empty --verbose
```

### 邮件模板

邮件模板位于 `templates/emails/` 目录下。

#### 创建自定义模板

1. 在 `templates/emails/` 目录下创建PHP文件
2. 模板文件应该返回包含 `subject` 和 `message` 的数组

```php
<?php
// templates/emails/custom.php

$user_name = $user_name ?? 'User';
$site_name = get_bloginfo('name');

return [
    'subject' => "Custom Email for {$user_name}",
    'message' => "
    <html>
    <body>
        <h1>Hello {$user_name}!</h1>
        <p>This is a custom email from {$site_name}.</p>
    </body>
    </html>
    "
];
```

#### 使用自定义模板

```php
EmailJob::sendTemplate(
    'user@example.com',
    'custom',
    ['user_name' => 'John Doe']
);
```

### 功能特性

1. **邮件验证** - 自动验证邮件地址格式和必需字段
2. **错误处理** - 完善的错误处理和重试机制
3. **日志记录** - 记录发送成功和失败的邮件
4. **管理员通知** - 发送失败时自动通知管理员
5. **附件支持** - 支持添加文件附件
6. **HTML邮件** - 支持HTML格式的邮件内容
7. **模板系统** - 支持可重用的邮件模板
8. **批量发送** - 支持批量发送给多个收件人
9. **延迟发送** - 支持延迟发送邮件

### 配置选项

EmailJob会自动使用以下WordPress设置：

- `admin_email` - 默认发件人邮箱
- `bloginfo('name')` - 网站名称
- WordPress邮件配置

### 监控和日志

EmailJob会记录以下信息：

1. **成功日志** - 存储在 `g3_email_logs` 选项中
2. **失败日志** - 存储在 `g3_email_failed_logs` 选项中
3. **错误日志** - 写入PHP错误日志

### 最佳实践

1. **使用模板** - 为常用邮件创建模板，提高代码复用性
2. **批量处理** - 对于大量邮件，使用批量发送功能
3. **错误监控** - 定期检查失败日志，确保邮件正常发送
4. **测试环境** - 在生产环境使用前，先在测试环境验证
5. **队列监控** - 定期检查队列状态，确保任务正常处理

### 故障排除

1. **邮件发送失败**
   - 检查WordPress邮件配置
   - 验证SMTP设置
   - 检查服务器邮件功能

2. **队列任务不执行**
   - 确保队列工作器正在运行
   - 检查任务类是否正确加载
   - 验证数据库连接

3. **模板不工作**
   - 检查模板文件路径
   - 验证模板文件语法
   - 确保模板返回正确的数组格式