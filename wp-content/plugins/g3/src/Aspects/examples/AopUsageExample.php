<?php

/**
 * AOP Usage Examples
 * AOP 使用示例
 * 
 * 展示如何在实际项目中使用 G3 AOP 系统
 */

require_once __DIR__ . '/../../Aspects.php';
require_once __DIR__ . '/../../Attributes/Aspects.php';

use JEALER\G3\Aspects\Aspects as Aop;
use JEALER\G3\Attributes\Aspects as AopAttr;

/**
 * 示例 1: 用户服务类 - 使用配置文件方式
 */
class UserService {
    private array $users = [];

    public function createUser(array $userData): int
    {
        $userId               = count($this->users) + 1;
        $this->users[$userId] = $userData;
        return $userId;
    }

    public function updateUser(int $userId, array $userData): bool
    {
        if (!isset($this->users[$userId])) {
            throw new Exception("用户不存在: {$userId}");
        }

        $this->users[$userId] = array_merge($this->users[$userId], $userData);
        return true;
    }

    public function deleteUser(int $userId): bool
    {
        if (!isset($this->users[$userId])) {
            return false;
        }

        unset($this->users[$userId]);
        return true;
    }

    public function getUser(int $userId): ?array
    {
        return $this->users[$userId] ?? null;
    }
}

/**
 * 示例 2: 订单服务类 - 使用注解方式
 */
#[AopAttr(
    'method',
    'before',
    '*',
function ($target, $method, $args) {
    echo "[LOG] 调用订单服务方法: {$method}\n";
    }
)]
class OrderService {
    private array $orders = [];

    #[AopAttr(
        'method',
        'before',
        function ($target, $method, $args) {
            echo "[AUDIT] 创建订单，数据: " . json_encode($args[0]) . "\n";
            }
    )]
    #[AopAttr(
        'method',
        'after',
        function ($target, $method, $args, $result) {
            echo "[AUDIT] 订单创建完成，ID: {$result}\n";
            }
    )]
    public function createOrder(array $orderData): int
    {
        $orderId                 = count($this->orders) + 1;
        $orderData['created_at'] = date('Y-m-d H:i:s');
        $orderData['status']     = 'pending';
        $this->orders[$orderId]  = $orderData;
        return $orderId;
    }

    #[AopAttr(
        'method',
        'before',
        function ($target, $method, $args) {
            echo "[SECURITY] 检查订单访问权限，订单ID: {$args[0]}\n";
            }
    )]
    public function getOrder(int $orderId): ?array
    {
        return $this->orders[$orderId] ?? null;
    }

    public function updateOrderStatus(int $orderId, string $status): bool
    {
        if (!isset($this->orders[$orderId])) {
            return false;
        }

        $this->orders[$orderId]['status']     = $status;
        $this->orders[$orderId]['updated_at'] = date('Y-m-d H:i:s');
        return true;
    }
}

/**
 * 示例 3: 缓存服务类 - 属性拦截示例
 */
class CacheService {
    private array $cache = [];
    private int $hitCount = 0;
    private int $missCount = 0;

    public function get(string $key)
    {
        if (isset($this->cache[$key])) {
            $this->hitCount++;
            return $this->cache[$key];
        }

        $this->missCount++;
        return null;
    }

    public function set(string $key, $value, int $ttl = 3600): void
    {
        $this->cache[$key] = [
            'value'   => $value,
            'expires' => time() + $ttl
        ];
    }

    public function getStats(): array
    {
        return [
            'hits'     => $this->hitCount,
            'misses'   => $this->missCount,
            'hit_rate' => $this->hitCount / ($this->hitCount + $this->missCount) * 100
        ];
    }
}

/**
 * 示例 4: 数据库模型类 - 复杂切面场景
 */
#[AopAttr(
    'method',
    'before',
    'save*',
function ($target, $method, $args) {
    echo "[DB] 准备保存数据到数据库\n";
    }
)]
#[AopAttr(
    'method',
    'after',
    'save*',
function ($target, $method, $args, $result) {
    echo "[DB] 数据保存完成，结果: " . ($result ? '成功' : '失败') . "\n";
    }
)]
class ProductModel {
    private array $products = [];

    #[AopAttr(
        'method',
        'before',
        function ($target, $method, $args) {
            // 数据验证
            $data = $args[0];
                if (empty($data['name'])) {
                throw new Exception('产品名称不能为空');
                }
                if (empty($data['price']) || $data['price'] <= 0) {
                throw new Exception('产品价格必须大于0');
                }
            }
    )]
    public function saveProduct(array $productData): bool
    {
        $productId                  = $productData['id'] ?? (count($this->products) + 1);
        $this->products[$productId] = $productData;
        return true;
    }

    public function saveCategory(array $categoryData): bool
    {
        // 这个方法也会被 save* 通配符匹配
        echo "保存分类: " . $categoryData['name'] . "\n";
        return true;
    }

    public function getProduct(int $productId): ?array
    {
        return $this->products[$productId] ?? null;
    }
}

/**
 * 创建 AOP 配置示例
 */
function createAopConfig(): void
{
    $configDir = WP_PLUGIN_DIR . '/g3/config';
    if (!is_dir($configDir)) {
        mkdir($configDir, 0755, true);
    }

    $config = [
        // 用户服务日志记录
        [
            'type'     => 'method',
            'class'    => 'UserService',
            'method'   => '*',
            'advice'   => 'before',
            'callback' => function ($target, $method, $args) {
                echo "[USER_LOG] 调用方法: {$method}，参数: " . json_encode($args) . "\n";
            }
        ],

        // 用户服务异常处理
        [
            'type'     => 'method',
            'class'    => 'UserService',
            'method'   => '*',
            'advice'   => 'after_throw',
            'callback' => function ($target, $method, $args, $exception) {
                echo "[ERROR] 用户服务异常: {$method} - " . $exception->getMessage() . "\n";
            }
        ],

        // 缓存服务性能监控
        [
            'type'     => 'method',
            'class'    => 'CacheService',
            'method'   => 'get',
            'advice'   => 'before',
            'callback' => function ($target, $method, $args) {
                $GLOBALS['cache_start_time'] = microtime(true);
            }
        ],
        [
            'type'     => 'method',
            'class'    => 'CacheService',
            'method'   => 'get',
            'advice'   => 'after',
            'callback' => function ($target, $method, $args, $result) {
                $duration = microtime(true) - $GLOBALS['cache_start_time'];
                $status   = $result ? 'HIT' : 'MISS';
                echo "[CACHE] {$status} - Key: {$args[0]}, Duration: " . number_format($duration * 1000, 2) . "ms\n";
            }
        ],

        // 属性访问监控
        [
            'type'     => 'property',
            'class'    => 'CacheService',
            'prop'     => '*Count',
            'advice'   => 'after_set',
            'callback' => function ($target, $prop, $value) {
                echo "[STATS] 统计更新: {$prop} = {$value}\n";
            }
        ]
    ];

    $configFile = $configDir . '/aop.php';
    file_put_contents($configFile, '<?php return ' . var_export($config, true) . ';');

    echo "✓ AOP 配置文件已创建: {$configFile}\n\n";
}

/**
 * 运行示例
 */
function runExamples(): void
{
    echo "G3 AOP 使用示例\n";
    echo "===============\n\n";

    // 创建配置文件
    createAopConfig();

    // 初始化 AOP
    $aop = Aop::run();

    echo "示例 1: 用户服务（配置文件方式）\n";
    echo "--------------------------------\n";

    $userService = $aop->create(UserService::class);

    // 创建用户
    $userId = $userService->createUser([
        'name'  => '张三',
        'email' => 'zhangsan@example.com',
        'age'   => 25
    ]);

    // 更新用户
    $userService->updateUser($userId, ['age' => 26]);

    // 获取用户
    $user = $userService->getUser($userId);
    echo "获取用户信息: " . json_encode($user) . "\n";

    // 测试异常处理
    try {
        $userService->updateUser(999, ['name' => '不存在的用户']);
    }
    catch (Exception $e) {
        // 异常会被 AOP 拦截并记录
    }

    echo "\n";

    echo "示例 2: 订单服务（注解方式）\n";
    echo "----------------------------\n";

    $orderService = $aop->create(OrderService::class);

    // 创建订单
    $orderId = $orderService->createOrder([
        'user_id'    => $userId,
        'product_id' => 1,
        'quantity'   => 2,
        'total'      => 199.99
    ]);

    // 获取订单
    $order = $orderService->getOrder($orderId);
    echo "订单信息: " . json_encode($order) . "\n";

    // 更新订单状态
    $orderService->updateOrderStatus($orderId, 'paid');

    echo "\n";

    echo "示例 3: 缓存服务（性能监控）\n";
    echo "----------------------------\n";

    $cacheService = $aop->create(CacheService::class);

    // 设置缓存
    $cacheService->set('user:' . $userId, $user);
    $cacheService->set('order:' . $orderId, $order);

    // 获取缓存（命中）
    $cachedUser = $cacheService->get('user:' . $userId);

    // 获取缓存（未命中）
    $cachedProduct = $cacheService->get('product:1');

    // 查看统计
    $stats = $cacheService->getStats();
    echo "缓存统计: " . json_encode($stats) . "\n";

    echo "\n";

    echo "示例 4: 产品模型（数据验证）\n";
    echo "----------------------------\n";

    $productModel = $aop->create(ProductModel::class);

    // 保存有效产品
    $productModel->saveProduct([
        'name'     => 'iPhone 15',
        'price'    => 5999.00,
        'category' => 'electronics'
    ]);

    // 保存分类（也会被 save* 匹配）
    $productModel->saveCategory([
        'name'        => '电子产品',
        'description' => '各种电子设备'
    ]);

    // 尝试保存无效产品（会触发验证异常）
    try {
        $productModel->saveProduct([
            'name'  => '',  // 空名称
            'price' => 0   // 无效价格
        ]);
    }
    catch (Exception $e) {
        echo "验证失败: " . $e->getMessage() . "\n";
    }

    echo "\n";

    echo "示例完成！\n";
    echo "==========\n";
    echo "通过以上示例，你可以看到 AOP 如何在不修改原有代码的情况下：\n";
    echo "1. 添加日志记录\n";
    echo "2. 实现性能监控\n";
    echo "3. 进行数据验证\n";
    echo "4. 处理异常情况\n";
    echo "5. 实现审计功能\n";
    echo "6. 添加安全检查\n";

    // 清理配置文件
    $configFile = WP_PLUGIN_DIR . '/g3/config/aop.php';
    if (file_exists($configFile)) {
        unlink($configFile);
    }
}

// 模拟 WordPress 环境
if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', dirname(__DIR__, 3));
}

// 运行示例
if (php_sapi_name() === 'cli') {
    runExamples();
} else {
    echo "请在命令行环境下运行此示例\n";
}