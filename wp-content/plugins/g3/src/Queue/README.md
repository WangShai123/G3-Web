# G3 队列系统文档

G3 队列系统是一个功能强大的异步任务处理框架，支持多种驱动器、延迟任务、自动重试和智能清理等特性。它可以帮助您将耗时的操作（如邮件发送、图片处理、数据同步等）从主请求中分离出来，提高应用程序的响应速度和用户体验。

## 🚀 核心特性

### 架构特性
- **多驱动支持** - 支持 Database 和 Redis 两种队列驱动
- **延迟任务** - 支持延迟执行任务
- **自动重试** - 任务失败时自动重试，支持指数退避
- **智能清理** - 自动清理过期和已完成的任务
- **多消费者模式** - 支持 CLI、Cron、Supervisor 等多种消费方式
- **任务统计** - 提供详细的队列统计信息

### 功能特性
- **任务持久化** - 任务数据持久化存储，确保不丢失
- **并发安全** - 支持多进程并发处理任务
- **内存优化** - 智能内存管理，避免内存泄漏
- **错误处理** - 完善的错误处理和日志记录
- **配置灵活** - 丰富的配置选项，适应不同场景

## 📋 系统要求

- **PHP**: >= 8.3
- **WordPress**: >= 6.5
- **数据库**: MySQL/MariaDB（Database 驱动）
- **Redis**: >= 5.0（Redis 驱动，可选）
- **Cron**: WordPress Cron 或系统 Cron

## 🏗️ 架构设计

### 核心组件

```
Queue/
├── Queue.php                 # 队列管理器（门面 + 实例模式）
├── QueueInterface.php        # 队列接口定义
├── Job.php                   # 任务基类
├── CronSchedules.php         # Cron 调度管理
├── QueueCronProcessor.php    # Cron 处理器
├── DatabaseQueue.php         # 数据库队列驱动
├── RedisQueue.php           # Redis 队列驱动
└── Jobs/                    # 任务类目录
    ├── EmailJob.php         # 邮件发送任务
    └── ...                  # 其他任务类
```

### 设计模式

1. **门面模式** - 提供简洁的静态 API
2. **策略模式** - 支持多种队列驱动
3. **工厂模式** - 动态创建队列驱动实例
4. **模板方法模式** - 任务执行流程标准化

## 🔧 配置说明

### 基础配置

队列系统通过 `System::config('queue')` 获取配置，支持以下选项：

```php
// 队列配置示例
$queueConfig = [
    // 队列驱动：database 或 redis
    'driver' => 'database',
    
    // 消费者类型：cli, cron, supervisor
    'consumer' => 'cron',
    
    // 全局自动清理设置
    'auto_cleanup' => true,
    
    // 数据库驱动配置
    'database' => [
        'table' => 'g3_jobs',  // 任务表名
    ],
    
    // Redis 驱动配置
    'redis' => [
        'host'     => '127.0.0.1',
        'port'     => 6379,
        'password' => null,
        'database' => 0,
        'prefix'   => 'g3_queue:',
        'timeout'  => 5,
    ],
    
    // Cron 消费者配置
    'cron' => [
        'interval_minutes'      => 1,      // 执行间隔（分钟）
        'jobs_per_run'         => 10,     // 每次处理任务数
        'process_delayed'      => true,   // 是否处理延迟任务
        'auto_cleanup'         => true,   // 是否自动清理
        'cron_cleanup_interval' => 5,     // 清理间隔（每N次运行）
        'reserved_timeout'     => 60,     // 保留任务超时（分钟）
        'old_jobs_days'        => 7,      // 旧任务保留天数
    ],
    
    // CLI 消费者配置
    'cli' => [
        'auto_cleanup'     => true,
        'cleanup_interval' => 100,        // 每处理N个任务后清理
        'reserved_timeout' => 60,
        'old_jobs_days'    => 7,
    ],
    
    // 清理配置
    'cleanup' => [
        'reserved_timeout' => 60,         // 保留任务超时（分钟）
        'old_jobs_days'    => 7,          // 旧任务保留天数
    ],
];
```

### 驱动选择

#### Database 驱动
- **优点**: 无需额外依赖，数据持久化可靠
- **缺点**: 性能相对较低，不支持高并发
- **适用场景**: 中小型应用，任务量不大的场景

#### Redis 驱动
- **优点**: 高性能，支持高并发，内存操作快速
- **缺点**: 需要 Redis 服务，数据存储在内存中
- **适用场景**: 高并发应用，对性能要求较高的场景

## 📝 基本用法

### 1. 创建任务类

继承 `Job` 基类创建自定义任务：

```php
<?php
namespace JEALER\G3\Queue\Jobs;

use JEALER\G3\Queue\Job;

class MyCustomJob extends Job {
    /**
     * 执行任务
     */
    public function handle(array $data): void {
        // 任务执行逻辑
        $userId = $data['user_id'];
        $message = $data['message'];
        
        // 执行具体业务逻辑
        $this->processUserData($userId, $message);
    }
    
    /**
     * 任务失败处理
     */
    public function failed(array $data, \Throwable $exception): void {
        // 失败后的处理逻辑
        error_log("Job failed: " . $exception->getMessage());
        
        // 可以发送通知、记录日志等
        $this->notifyAdmin($data, $exception);
    }
    
    private function processUserData($userId, $message) {
        // 具体业务逻辑
    }
    
    private function notifyAdmin($data, $exception) {
        // 通知管理员
    }
}
```

### 2. 推送任务到队列

#### 门面模式（静态调用）

```php
use JEALER\G3\Queue;

// 推送立即执行的任务
Queue::driver()->push(
    'JEALER\\G3\\Queue\\Jobs\\MyCustomJob',
    ['user_id' => 123, 'message' => 'Hello World'],
    0,           // 延迟时间（秒）
    'default'    // 队列名称
);

// 推送延迟任务（5分钟后执行）
Queue::driver()->push(
    'JEALER\\G3\\Queue\\Jobs\\MyCustomJob',
    ['user_id' => 123, 'message' => 'Delayed Task'],
    300,         // 5分钟延迟
    'default'
);
```

#### 实例模式

```php
use JEALER\G3\Queue;

// 创建队列实例
$queue = new Queue();

// 或者使用特定配置
$queue = Queue::database(['table' => 'custom_jobs']);
$queue = Queue::redis(['host' => 'redis.example.com']);

// 推送任务
$jobId = $queue->push(
    'JEALER\\G3\\Queue\\Jobs\\MyCustomJob',
    ['user_id' => 123, 'message' => 'Instance Mode'],
    0,
    'default'
);

echo "Job ID: {$jobId}";
```

### 3. 处理队列任务

#### 使用 Cron 自动处理

队列系统会自动注册 WordPress Cron 任务，按配置的间隔自动处理队列：

```php
// Cron 会自动调用 CronSchedules::processQueue()
// 无需手动干预，系统会自动处理
```

#### 手动处理任务

```php
use JEALER\G3\Queue;

$queue = new Queue();

// 处理单个任务
$processed = $queue->processJob('default');
if ($processed) {
    echo "处理了一个任务";
} else {
    echo "队列为空";
}

// 处理多个任务
$count = $queue->processJobs(10, 'default');
echo "处理了 {$count} 个任务";

// 处理所有任务
$queue->process(); // 最多处理10个任务
```

## 📧 邮件任务示例

### 基本邮件发送

```php
use JEALER\G3\Queue\Jobs\EmailJob;

// 发送单封邮件
$jobId = EmailJob::send(
    'user@example.com',                    // 收件人
    '欢迎注册我们的网站',                    // 主题
    '<h1>欢迎！</h1><p>感谢您的注册。</p>',   // 内容（HTML）
    ['From: noreply@example.com'],         // 邮件头
    [],                                    // 附件
    0                                      // 延迟时间
);

// 延迟发送（1小时后）
$jobId = EmailJob::send(
    'user@example.com',
    '提醒：完善您的个人资料',
    '<p>请完善您的个人资料以获得更好的体验。</p>',
    [],
    [],
    3600  // 1小时延迟
);
```

### 批量邮件发送

```php
use JEALER\G3\Queue\Jobs\EmailJob;

// 批量发送邮件
$recipients = [
    'user1@example.com',
    'user2@example.com',
    'user3@example.com'
];

$jobIds = EmailJob::sendBatch(
    $recipients,
    '系统维护通知',
    '<p>系统将于今晚进行维护，请提前保存您的工作。</p>',
    ['From: admin@example.com'],
    [],
    0
);

echo "创建了 " . count($jobIds) . " 个邮件任务";
```

### 模板邮件发送

```php
use JEALER\G3\Queue\Jobs\EmailJob;

// 使用模板发送邮件
$jobId = EmailJob::sendTemplate(
    'user@example.com',
    'welcome',  // 模板名称
    [           // 模板变量
        'username' => 'John Doe',
        'site_name' => get_bloginfo('name'),
        'login_url' => wp_login_url()
    ],
    [],         // 邮件头
    [],         // 附件
    0           // 延迟时间
);
```

## 🔄 任务重试机制

### 自动重试

任务失败时会自动重试，支持指数退避策略：

```php
// 重试逻辑（自动执行）
// 第1次失败：立即重试
// 第2次失败：2分钟后重试  
// 第3次失败：4分钟后重试
// 超过3次：调用 failed() 方法
```

### 自定义重试逻辑

```php
class CustomRetryJob extends Job {
    public function handle(array $data): void {
        // 可能失败的操作
        if (rand(1, 3) === 1) {
            throw new \Exception('Random failure for testing');
        }
        
        echo "任务执行成功！";
    }
    
    public function failed(array $data, \Throwable $exception): void {
        // 最终失败后的处理
        error_log("任务最终失败: " . $exception->getMessage());
        
        // 可以发送通知、记录到数据库等
        $this->handleFinalFailure($data, $exception);
    }
}
```

## 🧹 清理机制

### 自动清理

队列系统提供多层清理机制：

1. **实时清理** - 任务完成后立即删除（可配置）
2. **定期清理** - 每处理N个任务后清理一次
3. **Cron清理** - 定时清理过期和旧任务

### 手动清理

```php
use JEALER\G3\Queue;

$queue = new Queue();

// 执行清理
$cleaned = $queue->performCleanup();
echo "清理了 {$cleaned} 个任务";

// 自定义清理选项
$cleaned = $queue->getDriver()->cleanup([
    'reserved_timeout' => 30,  // 30分钟超时
    'old_jobs_days' => 3,      // 保留3天
]);
```

## 📊 监控和统计

### 队列状态监控

```php
use JEALER\G3\Queue;

$queue = new Queue();

// 获取队列大小
$size = $queue->size('default');
echo "队列中有 {$size} 个待处理任务";

// 获取详细统计（Database 驱动）
if ($queue->getDriver() instanceof \JEALER\G3\Queue\DatabaseQueue) {
    $stats = $queue->getDriver()->getQueueStats('default');
    
    echo "总任务数: {$stats['total']}\n";
    echo "可用任务: {$stats['available']}\n";
    echo "保留任务: {$stats['reserved']}\n";
    echo "延迟任务: {$stats['delayed']}\n";
    echo "平均重试次数: {$stats['avg_attempts']}\n";
}

// 获取 Redis 统计
if ($queue->getDriver() instanceof \JEALER\G3\Queue\RedisQueue) {
    $stats = $queue->getDriver()->getStats('default');
    
    echo "队列大小: {$stats['queue_size']}\n";
    echo "延迟任务: {$stats['delayed_jobs']}\n";
    echo "内存使用: {$stats['memory_usage']}\n";
}
```

### 日志记录

队列系统会自动记录详细的执行日志：

```php
// 日志示例
[G3] Queue: Instance initialized
[G3] Cron run #1 completed - Jobs: 5, Cleaned: 2, Time: 150ms, Memory: 8MB
[G3] EmailJob: Email sent successfully to: user@example.com
[G3] Queue job failed: Connection timeout
```

## 🛠️ 高级用法

### 多队列支持

```php
use JEALER\G3\Queue;

$queue = new Queue();

// 推送到不同队列
$queue->push('HighPriorityJob', $data, 0, 'high');
$queue->push('NormalJob', $data, 0, 'default');
$queue->push('LowPriorityJob', $data, 0, 'low');

// 处理特定队列
$queue->processJobs(5, 'high');    // 优先处理高优先级
$queue->processJobs(3, 'default'); // 然后处理普通任务
$queue->processJobs(1, 'low');     // 最后处理低优先级
```

### 条件任务

```php
class ConditionalJob extends Job {
    public function handle(array $data): void {
        // 检查执行条件
        if (!$this->shouldExecute($data)) {
            return; // 跳过执行
        }
        
        // 执行任务逻辑
        $this->doWork($data);
    }
    
    private function shouldExecute(array $data): bool {
        // 检查业务条件
        $user = get_user_by('ID', $data['user_id']);
        return $user && $user->user_status === 'active';
    }
}
```

### 任务链

```php
class ChainedJob extends Job {
    public function handle(array $data): void {
        // 执行当前任务
        $result = $this->processStep($data);
        
        // 推送下一个任务
        if ($result['success'] && isset($data['next_job'])) {
            Queue::driver()->push(
                $data['next_job'],
                array_merge($data, ['previous_result' => $result]),
                0,
                'default'
            );
        }
    }
}

// 使用示例
Queue::driver()->push('FirstJob', [
    'user_id' => 123,
    'next_job' => 'SecondJob'
], 0, 'default');
```

## 🚀 性能优化

### 数据库优化

```sql
-- 为队列表添加索引（已自动创建）
CREATE INDEX idx_queue_available ON wp_g3_jobs (queue, available_at);
CREATE INDEX idx_reserved_at ON wp_g3_jobs (reserved_at);
CREATE INDEX idx_created_at ON wp_g3_jobs (created_at);
```

### Redis 优化

```php
// Redis 配置优化
$redisConfig = [
    'host' => '127.0.0.1',
    'port' => 6379,
    'timeout' => 5,
    'database' => 1,  // 使用专用数据库
    'prefix' => 'app_queue:',
];
```

### 内存优化

```php
// 批量处理时限制内存使用
class MemoryEfficientJob extends Job {
    public function handle(array $data): void {
        // 处理大量数据时分批处理
        $items = $data['items'];
        $batchSize = 100;
        
        foreach (array_chunk($items, $batchSize) as $batch) {
            $this->processBatch($batch);
            
            // 清理内存
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
    }
}
```

## 🔧 CLI 命令

G3 提供了队列相关的 CLI 命令：

```bash
# 处理队列任务
php g3.php queue:work

# 处理指定队列
php g3.php queue:work --queue=high

# 处理完所有任务后停止
php g3.php queue:work --stop-when-empty

# 详细输出
php g3.php queue:work --verbose

# 清理队列
php g3.php queue:cleanup

# 查看队列状态
php g3.php queue:status
```

## 🛡️ 错误处理

### 异常处理

```php
class RobustJob extends Job {
    public function handle(array $data): void {
        try {
            $this->doRiskyOperation($data);
        } catch (\Exception $e) {
            // 记录错误但不抛出异常（避免重试）
            error_log("Non-critical error: " . $e->getMessage());
            return;
        }
        
        // 关键操作失败时抛出异常（触发重试）
        if (!$this->doCriticalOperation($data)) {
            throw new \Exception('Critical operation failed');
        }
    }
    
    public function failed(array $data, \Throwable $exception): void {
        // 发送告警邮件
        EmailJob::send(
            get_option('admin_email'),
            'Job Failed: ' . static::class,
            "Job failed with error: " . $exception->getMessage(),
            [],
            [],
            0
        );
    }
}
```

### 超时处理

```php
class TimeoutJob extends Job {
    public function handle(array $data): void {
        // 设置执行时间限制
        set_time_limit(300); // 5分钟
        
        $startTime = time();
        $timeout = 240; // 4分钟超时
        
        foreach ($data['items'] as $item) {
            // 检查是否超时
            if (time() - $startTime > $timeout) {
                // 将剩余任务重新加入队列
                $remaining = array_slice($data['items'], $processed);
                if (!empty($remaining)) {
                    Queue::driver()->push(static::class, [
                        'items' => $remaining
                    ], 60); // 1分钟后重试
                }
                break;
            }
            
            $this->processItem($item);
        }
    }
}
```

## 📚 最佳实践

### 1. 任务设计原则

- **幂等性**: 任务可以安全地重复执行
- **原子性**: 任务要么完全成功，要么完全失败
- **无状态**: 任务不依赖外部状态
- **可重试**: 任务失败后可以安全重试

### 2. 数据传递

```php
// ✅ 好的做法：传递ID，在任务中查询最新数据
Queue::driver()->push('UpdateUserJob', [
    'user_id' => 123
]);

// ❌ 避免：传递完整对象（可能过时）
Queue::driver()->push('UpdateUserJob', [
    'user' => $userObject  // 可能在执行时已过时
]);
```

### 3. 错误处理

```php
class WellDesignedJob extends Job {
    public function handle(array $data): void {
        // 验证输入数据
        if (!$this->validateData($data)) {
            throw new \InvalidArgumentException('Invalid job data');
        }
        
        // 检查前置条件
        if (!$this->checkPreconditions($data)) {
            // 不重试，直接返回
            return;
        }
        
        // 执行主要逻辑
        $this->executeMainLogic($data);
    }
    
    private function validateData(array $data): bool {
        return isset($data['required_field']) && !empty($data['required_field']);
    }
    
    private function checkPreconditions(array $data): bool {
        // 检查业务条件
        return true;
    }
}
```

### 4. 监控和告警

```php
class MonitoredJob extends Job {
    public function handle(array $data): void {
        $startTime = microtime(true);
        
        try {
            $this->doWork($data);
            
            // 记录成功指标
            $this->recordMetric('job.success', 1);
            $this->recordMetric('job.duration', microtime(true) - $startTime);
            
        } catch (\Exception $e) {
            // 记录失败指标
            $this->recordMetric('job.failure', 1);
            throw $e;
        }
    }
    
    private function recordMetric(string $name, $value): void {
        // 发送到监控系统
        // 例如：StatsD, CloudWatch, Prometheus 等
    }
}
```

## 🔍 故障排查

### 常见问题

1. **任务不执行**
   - 检查 Cron 是否正常运行
   - 确认队列配置正确
   - 查看错误日志

2. **任务重复执行**
   - 检查任务是否具有幂等性
   - 确认清理机制正常工作

3. **内存泄漏**
   - 避免在任务中创建大对象
   - 及时释放资源
   - 使用批处理

4. **数据库锁定**
   - 优化数据库查询
   - 减少事务时间
   - 考虑使用 Redis 驱动

### 调试技巧

```php
// 启用详细日志
class DebugJob extends Job {
    public function handle(array $data): void {
        error_log("[Debug] Job started with data: " . json_encode($data));
        
        try {
            $result = $this->doWork($data);
            error_log("[Debug] Job completed successfully: " . json_encode($result));
        } catch (\Exception $e) {
            error_log("[Debug] Job failed: " . $e->getMessage());
            error_log("[Debug] Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
}
```

## 📈 扩展和定制

### 自定义队列驱动

```php
class CustomQueueDriver implements QueueInterface {
    public function push(string $job, array $data = [], int $delay = 0, string $queue = 'default') {
        // 自定义推送逻辑
    }
    
    public function pop(string $queue = 'default') {
        // 自定义取出逻辑
    }
    
    public function size(string $queue = 'default'): int {
        // 自定义大小计算
    }
    
    public function delete($jobId): bool {
        // 自定义删除逻辑
    }
    
    public function cleanup(array $options = []): int {
        // 自定义清理逻辑
    }
}
```

### 自定义处理器

```php
class CustomProcessor {
    public function process(): void {
        // 自定义处理逻辑
        $queue = new Queue();
        
        while (true) {
            $job = $queue->pop();
            if (!$job) {
                sleep(1);
                continue;
            }
            
            $this->executeJob($job);
        }
    }
}
```

---

**G3 队列系统** - 让异步任务处理更简单、更可靠！

## 📞 技术支持

- **文档**: 查看完整的 G3 文档
- **问题反馈**: 通过 GitHub Issues 报告问题
- **社区支持**: 加入 G3 开发者社区

---

*最后更新: 2024年*