<?php
namespace JEALER\G3\Services;
use WP_Error;
use Exception;
use wpdb;

/**
 * Orders Service
 * 
 * 订单服务
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class OrdersService {

    /**
     * order table
     * 
     * 订单表
     * 
     * @var string
     */
    const TABLE = 'g3_orders';

    /**
     * order address table
     * 
     * 订单地址表
     * 
     * @var string
     */
    const ADDRESS_TABLE = 'g3_order_address';

    /**
     * order delivery table
     * 
     * 订单配送表
     * 
     * @var string
     */
    const DELIVERY_TABLE = 'g3_order_delivery';

    /**
     * order items table
     * 
     * 订单明细表
     * 
     * @var string
     */
    const ITEMS_TABLE = 'g3_order_items';

    /**
     * order cache group
     * 
     * 订单缓存组
     * 
     * @var string
     */
    const CACHE_GROUP = 'g3_orders';

    const ORDER_CODE_CACHE_GROUP    = 'code';
    const ORDER_ITEMS_CACHE_GROUP   = 'items';
    const ORDER_ADDRESS_CACHE_GROUP = 'address';

    /**
     * Redis lock key prefix for order code generation
     * 
     * 订单号生成 Redis 锁键前缀
     * 
     * @var string
     */
    const LOCK_PREFIX = 'lock:';

    /**
     * Redis sequence key prefix for daily order count
     * 
     * 当日订单计数 Redis 键前缀
     * 
     * @var string
     */
    const SEQ_PREFIX = 'seq:';

    /**
     * Lock expiration time in seconds
     * 
     * 锁过期时间（秒）
     * 
     * @var int
     */
    const LOCK_EXPIRE = 10;

    /**
     * Max retry attempts for lock acquisition
     * 
     * 锁获取最大重试次数
     * 
     * @var int
     */
    const MAX_RETRY = 3;

    /**
     * WordPress database object
     * 
     * @var wpdb
     */
    private wpdb $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public static function renderSource(int $id)
    {
        return match ($id) {
            1       => __('Web Order', 'G3'),
            2       => __('Manual Orders', 'G3'),
            3       => __('MobileApp Order', 'G3'),
            4       => __('Tiktok Order', 'G3'),
            default => __('Unknown')
        };
    }
    public static function renderType(int $id)
    {
        return match ($id) {
            1       => __('Product Order', 'G3'),
            2       => __('Tip Order', 'G3'),
            3       => __('Donate Order', 'G3'),
            4       => __('Membership Order', 'G3'),
            5       => __('Recharge Order', 'G3'),
            default => __('Unknown')
        };
    }
    public static function renderStatus(int $id)
    {
        return match ($id) {
            0       => __('Deleted', 'G3'),
            1       => __('Pending Payment', 'G3'),
            2       => __('Paid', 'G3'),
            3       => __('Shipped', 'G3'),
            4       => __('Completed', 'G3'),
            5       => __('Cancelled', 'G3'),
            6       => __('Refunded', 'G3'),
            default => __('Unknown')
        };
    }

    public function deleteOrderById(int $id): int|bool
    {
        $result = $this->wpdb->delete($this->wpdb->prefix . self::TABLE, ['id' => $id]);
        if ($result !== false) {
            $this->clearOrderCache($id);
        }
        return $result;
    }
    public function deleteOrderByCode(string $code): int|bool
    {
        $result = $this->wpdb->delete($this->wpdb->prefix . self::TABLE, ['order_code' => $code]);
        if ($result !== false) {
            $this->clearOrderCache($code);
        }
        return $result;
    }

    public function deleteOrderItems(int $orderId): bool|WP_Error
    {
        $this->wpdb->query('START TRANSACTION');

        try {
            $itemsResult = $this->wpdb->delete($this->wpdb->prefix . self::ITEMS_TABLE, ['order_id' => $orderId]);
            if ($itemsResult === false) {
                throw new Exception($this->wpdb->last_error);
            }

            $addressResult = $this->deleteOrderAddress($orderId);
            if ($addressResult === false) {
                throw new Exception($this->wpdb->last_error);
            }

            $deliveryResult = $this->deleteOrderDelivery($orderId);
            if ($deliveryResult === false) {
                throw new Exception($this->wpdb->last_error);
            }

            $this->wpdb->query('COMMIT');
            $this->clearOrderCache($orderId);
            return true;
        }
        catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            error_log(sprintf(
                '[G3 Orders] Delete order related data failed: %s. Order ID: %d',
                $e->getMessage(),
                $orderId
            ));

            return new WP_Error(
                500,
                sprintf(__('Failed to delete order related data: %s.', 'G3'), $e->getMessage()),
                ['status' => 500]
            );
        }
    }

    public function deleteOrderAddress(int $orderId): int|false
    {
        return $this->wpdb->delete($this->wpdb->prefix . self::ADDRESS_TABLE, ['order_id' => $orderId]);
    }

    public function deleteOrderDelivery(int $orderId): int|false
    {
        return $this->wpdb->delete($this->wpdb->prefix . self::DELIVERY_TABLE, ['order_id' => $orderId]);
    }

    public function trashOrder(int $orderId)
    {
        return $this->updateOrderStatus($orderId, 0);
    }

    public function closeOrder(int $orderId)
    {
        return $this->updateOrderStatus($orderId, 5);
    }

    /**
     * Create order code with Redis distributed lock
     * 
     * 创建订单号（使用 Redis 分布式锁）
     * 
     * Order code format: DateCode(14) + SourceCode + TypeCode + SequenceCode
     * 
     * Example: 202601011212121200000002
     * 
     * @param int $source The order source code
     * @param int $type The order type code
     * @param int $start The starting value for the sequence number
     * @param int $baseLength The base length of the sequence number
     * @return string|WP_Error The generated order code or WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    public function createOrderCode(int $source, int $type, int $start = 0, int $baseLength = 8): string|WP_Error
    {
        // Generate date code (14 digits: YmdHis)
        $dateCode = date('YmdHis');
        $todayKey = date('Ymd');

        // Build Redis keys
        $lockKey = self::LOCK_PREFIX . $todayKey;
        $seqKey  = self::SEQ_PREFIX . $todayKey;

        // Acquire Redis distributed lock with retry mechanism
        // 获取 Redis 分布式锁（带重试机制）
        $lockToken = $this->generateLockToken();
        $acquired  = false;

        for ($attempt = 0; $attempt < self::MAX_RETRY; $attempt++) {
            $acquired = wp_cache_add($lockKey, $lockToken, self::CACHE_GROUP, self::LOCK_EXPIRE);

            if ($acquired) {
                break;
            }

            // Exponential backoff: 10ms, 20ms, 40ms 指数退避
            usleep(10000 * pow(2, $attempt));
        }

        if (!$acquired) {
            return new WP_Error(
                'order_lock_failed',
                // 多次尝试后仍无法获取订单号锁。
                __('Failed to acquire order code lock after multiple attempts.', 'G3'),
                ['status' => 503, 'attempts' => self::MAX_RETRY]
            );
        }

        try {
            // Increment sequence number atomically using Redis
            // 使用 Redis 原子递增流水号
            $sequenceNumber = wp_cache_incr($seqKey, 1, self::CACHE_GROUP, 0, 86400);

            if (is_wp_error($sequenceNumber)) {
                return $sequenceNumber;
            }

            // Calculate final sequence: start + increment - 1 (first increment returns 1)
            // 计算最终流水号：起始值 + 增量 - 1（首次递增返回 1）
            $finalSequence = $start + $sequenceNumber - 1;

            // Generate sequence code with zero padding
            // 生成补零流水码
            $sequenceCode = str_pad((string) $finalSequence, $baseLength, '0', STR_PAD_LEFT);

            // Concatenate order code
            $orderCode = $dateCode . $source . $type . $sequenceCode;

            return $orderCode;

        }
        finally {
            // Release lock only if we own it (prevent releasing other process's lock)
            // 仅当拥有锁时释放（防止释放其他进程的锁）
            $currentToken = wp_cache_get($lockKey, self::CACHE_GROUP);
            if ($currentToken === $lockToken) {
                wp_cache_delete($lockKey, self::CACHE_GROUP);
            }
        }
    }

    /**
     * Generate unique lock token
     * 
     * 生成唯一锁令牌
     * 
     * @return string Unique lock token
     * @since 1.0.0
     * @author Wang Shai
     */
    private function generateLockToken(): string
    {
        return uniqid('lock_' . getmypid() . '_', true) . '_' . microtime(true);
    }

    /**
     * Create order with database transaction support
     * 
     * 创建订单（支持数据库事务）
     * 
     * @param array $data Order data including buyer_id, order_source, order_type, total_amount, final_amount, etc.
     * @param array $items Order items array
     * @param array $address Order address data
     * @return int|WP_Error Order ID or WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    public function createOrder(array $data, array $items = [], array $address = []): int|WP_Error
    {
        // Validate required fields
        $validationResult = $this->validateOrderData($data);
        if (is_wp_error($validationResult)) {
            return $validationResult;
        }

        // Generate order code with distributed lock 分布式锁
        $orderCode = $this->createOrderCode(
            (int) $data['order_source'],
            (int) $data['order_type'],
            0,
            8
        );

        if (is_wp_error($orderCode)) {
            return $orderCode;
        }

        // Start database transaction
        $this->wpdb->query('START TRANSACTION');

        try {
            // Insert main order record
            $orderId = $this->insertOrderRecord($orderCode, $data);

            if (is_wp_error($orderId)) {
                throw new Exception($orderId->get_error_message());
            }

            // Insert order items if provided
            if (!empty($items)) {
                $itemsResult = $this->insertOrderItems($orderId, $items);
                if (is_wp_error($itemsResult)) {
                    throw new Exception($itemsResult->get_error_message());
                }
            }

            // Insert order address if provided
            if (!empty($address)) {
                $addressId = $this->insertOrderAddress($orderId, $address);
                if (is_wp_error($addressId)) {
                    throw new Exception($addressId->get_error_message());
                }

                // Update order with address_id
                $this->wpdb->update(
                    $this->wpdb->prefix . self::TABLE,
                    ['address_id' => $addressId],
                    ['id' => $orderId]
                );
            }

            // Commit transaction
            $this->wpdb->query('COMMIT');

            // Clear order cache (if any)
            $this->clearOrderCache($orderId);

            return $orderId;

        }
        catch (Exception $e) {
            // Rollback transaction on error
            $this->wpdb->query('ROLLBACK');

            // Log error for debugging
            error_log(sprintf(
                '[G3 Orders] Create order failed: %s. Order code: %s. Data: %s',
                $e->getMessage(),
                $orderCode ?? 'N/A',
                json_encode($data)
            ));

            return new WP_Error(
                'order_create_failed',
                sprintf(__('Failed to create order: %s.', 'G3'), $e->getMessage()),
                ['status' => 500]
            );
        }
    }

    /**
     * Validate order data
     * 
     * 验证订单数据
     * 
     * @param array $data Order data
     * @return true|WP_Error True or WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    private function validateOrderData(array $data): bool|WP_Error
    {
        $requiredFields = [
            'buyer_id'     => __('Buyer ID', 'G3'),
            'order_source' => __('Order Source', 'G3'),
            'order_type'   => __('Order Type', 'G3'),
            'total_amount' => __('Total Amount', 'G3'),
            'final_amount' => __('Final Amount', 'G3'),
        ];

        foreach ($requiredFields as $field => $label) {
            if (!isset($data[$field]) || $data[$field] === '') {
                return new WP_Error(
                    'order_missing_field',
                    // 缺少必填字段：%s（%s）。
                    sprintf(__('Missing required field: %s (%s).', 'G3'), $field, $label),
                    ['status' => 400, 'field' => $field]
                );
            }
        }

        // Validate numeric fields
        $numericFields = ['buyer_id', 'order_source', 'order_type', 'total_amount', 'final_amount'];
        foreach ($numericFields as $field) {
            if (!is_numeric($data[$field])) {
                return new WP_Error(
                    'order_invalid_field',
                    // 字段值无效：%s 必须为数值。
                    sprintf(__('Invalid field value: %s must be numeric.', 'G3'), $field),
                    ['status' => 400, 'field' => $field]
                );
            }
        }

        // Validate amount consistency
        if ((float) $data['final_amount'] > (float) $data['total_amount']) {
            return new WP_Error(
                'order_invalid_amount',
                // 实际应付金额不能大于订单总额。
                __('Final amount cannot exceed total amount.', 'G3'),
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Insert order record
     * 
     * 插入订单记录
     * 
     * @param string $orderCode
     * @param array $data Order data
     * @return int|WP_Error Order ID or WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    private function insertOrderRecord(string $orderCode, array $data): int|WP_Error
    {
        $tableName = $this->wpdb->prefix . self::TABLE;

        $orderData = [
            'order_code'        => $orderCode,
            'buyer_id'          => (int) ($data['buyer_id'] ?? 0),
            'seller_id'         => (int) ($data['seller_id'] ?? 0),
            'order_source'      => (int) ($data['order_source'] ?? 1),
            'order_type'        => (int) ($data['order_type'] ?? 0),
            'order_status'      => (int) ($data['order_status'] ?? 1),
            'payment_status'    => (int) ($data['payment_status'] ?? 0),
            'total_amount'      => (float) $data['total_amount'],
            'discount_amount'   => (float) ($data['discount_amount'] ?? 0.00),
            'final_amount'      => (float) $data['final_amount'],
            'paid_amount'       => (float) ($data['paid_amount'] ?? 0.00),
            'coupon_id'         => !empty($data['coupon_id']) ? (int) $data['coupon_id'] : null,
            'referrer_id'       => !empty($data['referrer_id']) ? (int) $data['referrer_id'] : null,
            'commission_status' => isset($data['commission_status']) ? (int) $data['commission_status'] : null,
            'wallet_used'       => (float) ($data['wallet_used'] ?? 0.00),
            'third_party_order' => !empty($data['third_party_order']) ? (string) $data['third_party_order'] : null,
            'buyer_remark'      => !empty($data['buyer_remark']) ? (string) $data['buyer_remark'] : null,
            'seller_remark'     => !empty($data['seller_remark']) ? (string) $data['seller_remark'] : null,
        ];

        $result = $this->wpdb->insert($tableName, $orderData);

        if ($result === false) {
            return new WP_Error(
                'order_insert_failed',
                sprintf(__('Failed to insert order: %s. 插入订单失败：%s。', 'g3'), $this->wpdb->last_error),
                ['status' => 500]
            );
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Insert order items
     * 
     * 插入订单明细
     * 
     * @param int $orderId Order ID
     * @param array $items Order items data
     * @return true|WP_Error True or WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    private function insertOrderItems(int $orderId, array $items): bool|WP_Error
    {
        $tableName = $this->wpdb->prefix . self::ITEMS_TABLE;

        foreach ($items as $index => $item) {
            // Validate item data
            if (empty($item['product_id']) || empty($item['sku_id']) || !isset($item['unit_price'])) {
                return new WP_Error(
                    'order_item_invalid',
                    // 第 %d 项明细数据无效。
                    sprintf(__('Invalid item data at index %d.', 'G3'), $index),
                    ['status' => 400, 'index' => $index]
                );
            }

            $itemData = [
                'order_id'      => $orderId,
                'product_id'    => (int) $item['product_id'],
                'sku_id'        => (int) $item['sku_id'],
                'product_title' => !empty($item['product_title']) ? (string) $item['product_title'] : null,
                'product_image' => !empty($item['product_image']) ? (string) $item['product_image'] : null,
                'spec_info'     => !empty($item['spec_info']) ? maybe_serialize($item['spec_info']) : null,
                'quantity'      => (int) ($item['quantity'] ?? 1),
                'unit_price'    => (float) $item['unit_price'],
                'total_price'   => (float) ($item['total_price'] ?? (float) $item['unit_price'] * ($item['quantity'] ?? 1)),
            ];

            $result = $this->wpdb->insert($tableName, $itemData);

            if ($result === false) {
                return new WP_Error(
                    'order_item_insert_failed',
                    // 插入订单明细失败：%s。
                    sprintf(__('Failed to insert order item: %s.', 'G3'), $this->wpdb->last_error),
                    ['status' => 500, 'index' => $index]
                );
            }
        }

        return true;
    }

    /**
     * Insert order address
     * 
     * 插入订单地址
     * 
     * @param int $orderId Order ID
     * @param array $address Address data
     * @return int|WP_Error Address ID or WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    private function insertOrderAddress(int $orderId, array $address): int|WP_Error
    {
        $tableName = $this->wpdb->prefix . self::ADDRESS_TABLE;

        $addressData = [
            'order_id' => $orderId,
            'user_id'  => !empty($address['user_id']) ? (int) $address['user_id'] : null,
            'name'     => !empty($address['name']) ? (string) $address['name'] : null,
            'phone'    => !empty($address['phone']) ? (string) $address['phone'] : null,
            'country'  => !empty($address['country']) ? (string) $address['country'] : null,
            'province' => !empty($address['province']) ? (string) $address['province'] : null,
            'city'     => !empty($address['city']) ? (string) $address['city'] : null,
            'district' => !empty($address['district']) ? (string) $address['district'] : null,
            'address'  => !empty($address['address']) ? (string) $address['address'] : null,
            'postcode' => !empty($address['postcode']) ? (string) $address['postcode'] : null,
        ];

        $result = $this->wpdb->insert($tableName, $addressData);

        if ($result === false) {
            return new WP_Error(
                'order_address_insert_failed',
                // 插入订单地址失败：%s。
                sprintf(__('Failed to insert order address: %s.', 'G3'), $this->wpdb->last_error),
                ['status' => 500]
            );
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Clear order cache
     * 
     * 清除订单缓存
     * 
     * @param string $orderCode
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    private function clearOrderCache(string $orderCode): void
    {
        wp_cache_delete($orderCode, self::CACHE_GROUP);
    }

    /**
     * Get order by ID
     * 
     * 根据 ID 获取订单
     * 
     * @param int $orderId
     * @param bool $useCache Whether to use cache
     * @return array|WP_Error Order data or WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    public function getOrderById(int $orderId, bool $useCache = true): array|WP_Error
    {
        if ($useCache) {
            $cached = wp_cache_get($orderId, self::CACHE_GROUP);
            if ($cached !== false) {
                return $cached;
            }
        }

        $tableName = $this->wpdb->prefix . self::TABLE;

        $order = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$tableName} WHERE id = %d", $orderId),
            ARRAY_A
        );

        if (!$order) {
            return new WP_Error(
                'order_not_found',
                __('Order not found.', 'G3'),
                ['status' => 404]
            );
        }

        wp_cache_set($orderId, $order, self::CACHE_GROUP, HOUR_IN_SECONDS);

        return $order;
    }

    /**
     * Get order data by order code
     * 
     * 根据订单号获取订单
     * 
     * @param string $orderCode Order code
     * @return array|WP_Error Order data or WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    public function getOrderByCode(string $orderCode): array|WP_Error
    {
        $orderId = $this->getOrderIdByCode($orderCode);
        if (is_wp_error($orderId)) {
            return $orderId;
        }
        return $this->getOrderById($orderId, false);
    }

    public function getOrderIdByCode(string $orderCode): int|WP_Error
    {
        $group  = self::CACHE_GROUP . ':' . self::ORDER_CODE_CACHE_GROUP;
        $cached = wp_cache_get($orderCode, $group);
        if ($cached !== false) {
            return $cached;
        }

        $tableName = $this->wpdb->prefix . self::TABLE;

        $orderId = $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT id FROM {$tableName} WHERE order_code = %s", $orderCode)
        );

        if (!$orderId) {
            return new WP_Error(
                'order_not_found',
                __('Order not found.', 'G3'),
                ['status' => 404]
            );
        }

        wp_cache_set($orderCode, $orderId, $group, HOUR_IN_SECONDS);

        return (int) $orderId;
    }

    /**
     * Update order status
     * 
     * 更新订单状态
     * 
     * @param int $orderId
     * @param int $status
     * @return int|false
     * @since 1.0.0
     * @author Wang Shai
     */
    public function updateOrderStatus(int $orderId, int $status): int|false
    {
        $tableName = $this->wpdb->prefix . self::TABLE;

        $result = $this->wpdb->update(
            $tableName,
            ['order_status' => $status],
            ['id' => $orderId]
        );

        if ($result !== false) {
            $this->clearOrderCache($orderId);
        }

        return $result;
    }

    /**
     * Get order items by order ID
     * 
     * 根据订单 ID 获取订单明细
     * 
     * @param int $orderId Order ID
     * @return array|WP_Error Order items array or WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    public function getOrderItems(int $orderId): array|WP_Error
    {
        $tableName = $this->wpdb->prefix . self::ITEMS_TABLE;

        $items = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$tableName} WHERE order_id = %d", $orderId),
            ARRAY_A
        );

        if ($items === null) {
            return new WP_Error(
                'order_items_query_failed',
                // 查询订单明细失败：%s。
                sprintf(__('Failed to query order items: %s.', 'G3'), $this->wpdb->last_error),
                ['status' => 500]
            );
        }

        return $items;
    }

    /**
     * Get order address by order ID
     * 
     * 根据订单 ID 获取订单地址
     * 
     * @param int $orderId Order ID
     * @return array|WP_Error Order address data or WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    public function getOrderAddress(int $orderId): array|WP_Error
    {
        $tableName = $this->wpdb->prefix . self::ADDRESS_TABLE;

        $address = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$tableName} WHERE order_id = %d", $orderId),
            ARRAY_A
        );

        if (!$address) {
            return new WP_Error(
                'order_address_not_found',
                // 订单地址不存在。
                __('Order address not found.', 'G3'),
                ['status' => 404]
            );
        }

        return $address;
    }
}
