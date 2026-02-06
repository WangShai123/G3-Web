<?php

namespace JEALER\G3\Services;

class DBService {

    /**
     * Init Tables
     * 
     * 初始化数据库表
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function initTables()
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        /**
         * 0
         * 
         * Queue Jobs Table
         * 
         * 队列任务表
         *  - id: 主键, 任务ID
         *  - queue: 队列名称
         *  - payload: 任务数据
         *  - attempts: 尝试次数
         *  - reserved_at: 锁定时间
         *  - available_at: 可用时间
         *  - created_at: 创建时间
         *  - updated_at: 更新时间
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_queue_jobs';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `queue` varchar(255) NOT NULL DEFAULT 'default',
                `payload` longtext NOT NULL,
                `attempts` tinyint(4) UNSIGNED NOT NULL DEFAULT 0,
                `reserved_at` DATETIME NULL DEFAULT NULL,
                `available_at` DATETIME NOT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_queue_available (`queue`, `available_at`),
                KEY idx_reserved_at (`reserved_at`),
                KEY idx_created_at (`created_at`)
            ) ENGINE=InnoDB $charset COMMENT='queue jobs';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 1
         * 
         * Log Table
         * 
         * 日志表
         *  - id: 主键
         *  - type: 日志类型
         *  - level: 日志级别
         *  - module: 业务模块名称
         *  - message: 日志信息
         *  - user_id: 用户ID
         *  - ip_address: IP地址
         *  - meta: 日志元数据
         *  - created_at: 创建时间
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_logs';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `type` varchar(64) NOT NULL,
                `level` varchar(64) NOT NULL DEFAULT 'info',
                `module` varchar(64) DEFAULT NULL,
                `message` text NOT NULL,
                `user_id` int(11) DEFAULT NULL,
                `ip_address` varchar(64) DEFAULT NULL,
                `meta` MEDIUMTEXT DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `type` (`type`),
                KEY `level` (`level`),
                KEY `module` (`module`),
                KEY `created_at` (`created_at`)
            ) ENGINE=InnoDB $charset COMMENT='logs';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 2
         * 
         * User Wallet Table
         * 
         * 用户钱包表
         *  - user_id: 用户ID
         *  - balance: 账户余额
         *  - frozen_amount: 冻结金额
         *  - total_recharge: 总充值金额
         *  - total_withdraw: 总提现金额
         *  - created_at: 创建时间
         *  - updated_at: 更新时间
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_user_wallet';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `user_id` BIGINT UNSIGNED NOT NULL,
                `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `frozen_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `total_recharge` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `total_withdraw` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`user_id`),
                KEY `idx_balance` (`balance`)
            ) ENGINE=InnoDB $charset COMMENT='user wallet';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 3
         * 
         * Swiper Table
         * 
         * 轮播图表
         *  - id: 主键
         *  - title: 标题
         *  - link: 链接
         *  - target: 打开方式, 0: self, 1: blank
         *  - media: 媒体文件网络地址
         *  - location: 位置
         *  - sort: 排序
         *  - status: 状态, 0: offline, 1: online
         *  - user: 创建人
         *  - created_at: 创建时间
         *  - updated_at: 更新时间
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_swipers';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `title` varchar(255) NOT NULL,
                `link` varchar(255) NOT NULL,
                `target` tinyint(4) NOT NULL DEFAULT '0',
                `media` varchar(255) NOT NULL,
                `location` varchar(255) NOT NULL,
                `sort` int(11) NOT NULL DEFAULT '1',
                `status` tinyint(4) NOT NULL,
                `user_id` int(11) NOT NULL,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `location` (`location`)
            ) ENGINE=InnoDB $charset COMMENT='swipers';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 4
         * 
         * SKU Table
         * 
         * 库存管理表
         *  - id: 主键
         *  - product_id: 商品ID
         *  - sku_code: SKU编码
         *  - cost_price: 成本价
         *  - regular_price: 指导价
         *  - price: 售价
         *  - currency: 货币
         *  - sold: 已售数量
         *  - type: 库存类型, 1: general, 2: digital, 3: membership
         *  - stock: 库存数量
         *  - track: 是否跟踪库存
         *  - status: 状态, 0: inactive, 1: active
         *  - weight: 重量
         *  - size: 尺寸
         *  - unit: 单位
         *  - content: 库存实际内容
         *  - created_at: 创建时间
         *  - updated_at: 更新时间
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_sku';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                -- base data
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `product_id` BIGINT UNSIGNED NOT NULL,
                `sku_code` VARCHAR(64) NOT NULL,

                -- price & sold
                `cost_price` DECIMAL(12,2) DEFAULT NULL,
                `regular_price` DECIMAL(12,2) DEFAULT NULL,
                `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `currency` VARCHAR(8) NOT NULL DEFAULT 'CNY',
                `sold` int(11) NOT NULL DEFAULT 0,

                -- stock controll
                `type` tinyint NOT NULL DEFAULT 1,
                `stock` INT NOT NULL DEFAULT 0,
                `track` BOOLEAN NOT NULL DEFAULT TRUE,
                `status` tinyint(1) DEFAULT 1,

                -- physical attributes
                `weight` DECIMAL(12,2) DEFAULT NULL,
                `size` DECIMAL(12,2) DEFAULT NULL,
                `unit` VARCHAR(16) DEFAULT NULL,

                -- content: 存 URL / 序列化配置
                `content` TEXT,

                -- time
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                PRIMARY KEY (`id`),
                KEY `idx_product_id` (`product_id`),
                KEY `idx_sku_code` (`sku_code`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB $charset COMMENT='sku';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 5
         * 
         * Global Product Specifications Table
         * 
         * 全局商品规格表
         *  - id: 主键
         *  - name: 规格名称, 如: 颜色
         *  - key: 规格键名, 如: color
         *  - is_global: 是否全局规格, 0: 不是, 1: 是
         *  - scope: 使用范围, 0: all, 1: product, 2: category, 3: tag, 4: brand
         *  - owner_ids: 归属 IDs，当非全局规格时
         *  - status: 状态, 0: 禁用, 1: 启用
         *  - sort: 排序
         *  - created: 创建时间
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_specs';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `product_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `name` VARCHAR(100) NOT NULL,
                `key` VARCHAR(32) NOT NULL,
                `is_global` TINYINT(1) NOT NULL DEFAULT 1,
                `scope` TINYINT NOT NULL DEFAULT 0,
                `owner_ids` text NOT NULL DEFAULT '',
                `status` TINYINT NOT NULL DEFAULT 1,
                `sort` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_key` (`key`),
                UNIQUE KEY `uniq_name_key` (`name`, `key`),
                KEY `idx_is_global` (`is_global`),
                KEY `idx_scope` (`scope`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB $charset COMMENT='global product specifications';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 6
         * 
         * Global Specifications Options Table
         * 
         * 全局规格选项表，存储可复用的规格选项信息
         * - id: 主键
         * - spec_id: 关联规格表ID
         * - name: 选项名称, 如: 红色, XL, 棉质
         * - key: 选项标识 (用于系统内部使用)
         * - sort: 排序
         * - status: 状态, 1: 启用, 0: 禁用
         * - created_at: 创建时间
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_specs_options';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `spec_id` BIGINT UNSIGNED NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `key` VARCHAR(100) NOT NULL,
                `sort` INT DEFAULT 0,
                `status` TINYINT(1) DEFAULT 1,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_spec_id_name` (`spec_id`, `name`),
                KEY `idx_spec_id` (`spec_id`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB $charset COMMENT='global specification options';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 7
         * 
         * Product-Specifications Relations Table
         * 
         * 产品与规格的关联表，记录哪些规格应用于哪个产品
         * - id: 主键
         * - product_id: 商品ID
         * - spec_id: 规格ID
         * - required: 是否为必填规格, 0: 不必填, 1: 必填
         * - sort: 排序
         * - created_at: 创建时间
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_product_specs';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `product_id` BIGINT UNSIGNED NOT NULL,
                `spec_id` BIGINT UNSIGNED NOT NULL,
                `required` TINYINT(1) NOT NULL DEFAULT 0,
                `sort` INT DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_product_spec` (`product_id`, `spec_id`),
                KEY `idx_product_id` (`product_id`),
                KEY `idx_spec_id` (`spec_id`)
            ) ENGINE=InnoDB $charset COMMENT='product-specifications relations';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 8
         * 
         * SKU Specs Options Relations Table
         * 
         * 库存规格选项关系表
         *  - id: 主键
         *  - sku_id: SKU ID
         *  - spec_option_id: 规格选项 ID
         *  - created_at: 创建时间
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_sku_specs_relations';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `sku_id` BIGINT UNSIGNED NOT NULL,
                `spec_option_id` BIGINT UNSIGNED NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_sku_spec_option` (`sku_id`, `spec_option_id`),
                KEY `idx_sku_id` (`sku_id`),
                KEY `idx_spec_option_id` (`spec_option_id`)
            ) ENGINE=InnoDB $charset COMMENT='SKU-specification options relations';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 9
         * 
         * Order Table
         * 
         * 订单表
         *  - id: 主键
         *  - order_code: 订单编号
         *  - buyer_id: 买家用户ID
         *  - seller_id: 卖家用户ID
         *  - order_source: 订单来源, 0: 网页订单, 1: 抖音订单
         *  - order_type: 订单类型（对应库存管理类型）, 0: general, 1: digital, 2: membership
         *  - order_status: 订单状态, 0: pending, 1: paid, 2: processing, 3: completed, 4: cancelled, 5: refunded
         *  - total_amount: 订单总额
         *  - discount_amount: 优惠金额
         *  - final_amount: 实际应付金额
         *  - paid_amount: 已支付金额
         *  - coupon_id: 关联 优惠券ID
         *  - referrer_id: 关联 推荐人ID
         *  - commission_status: 佣金状态, 0: pending, 1: settled, 2: cancelled
         *  - wallet_used: 使用钱包余额支付的金额（若为 0 则未使用）
         *  - thirdparty_order: 第三方订单号
         *  - address_id: 关联地址ID
         *  - payment_status: 支付状态，
         *  - delivery_method: 配送方式
         *  - delivery_code: 物流编码
         *  - delivery_company: 物流公司
         *  - buyer_remark: 买家留言
         *  - seller_remark: 卖家留言
         *  - created_at: 创建时间
         *  - completed_at: 完成时间
         *  - updated_at: 更新时间
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_orders';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                -- 核心标识
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_code` varchar(32) NOT NULL,
                -- 用户关系
                `buyer_id` int(11) NOT NULL,
                `seller_id` int(11) NOT NULL DEFAULT 0,
                -- 业务分类
                `order_source` tinyint(4) NOT NULL,
                `order_type` tinyint(4) NOT NULL,
                -- 状态机
                `order_status` tinyint(4) NOT NULL,
                `payment_status` tinyint(4) DEFAULT NULL,
                -- 金额体系
                `total_amount` decimal(10,2) NOT NULL,
                `discount_amount` decimal(10,2) NOT NULL,
                `final_amount` decimal(10,2) NOT NULL,
                `paid_amount` decimal(10,2) NOT NULL,
                -- 营销分销
                `coupon_id` BIGINT UNSIGNED DEFAULT NULL,
                `referrer_id` BIGINT UNSIGNED DEFAULT NULL,
                `commission_status` tinyint(4) DEFAULT NULL,
                -- 余额支付快照
                `wallet_used` decimal(10,2) DEFAULT 0.00,
                -- 对账快照
                `thirdparty_order` varchar(100) DEFAULT NULL,
                -- 地址快照
                `address_id` BIGINT UNSIGNED DEFAULT NULL,
                -- 物流信息
                `delivery_id` BIGINT UNSIGNED DEFAULT NULL,
                -- 交互信息
                `buyer_remark` varchar(255) DEFAULT NULL,
                `seller_remark` varchar(255) DEFAULT NULL,
                -- 时间轴
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `completed_at` DATETIME DEFAULT NULL,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_order_code` (`order_code`),
                UNIQUE KEY `uniq_thirdparty_order` (`thirdparty_order`),
                KEY `idx_buyer_id` (`buyer_id`),
                KEY `idx_seller_id` (`seller_id`),
                KEY `idx_order_status` (`order_status`),
                KEY `idx_payment_status` (`payment_status`),
                KEY `idx_delivery_id` (`delivery_id`),
                KEY `idx_created_at` (`created_at`),
                KEY `idx_completed_at` (`completed_at`),
                KEY `idx_buyer_status` (`buyer_id`, `order_status`),
                KEY `idx_status_created` (`order_status`, `created_at`)
            ) ENGINE=InnoDB $charset COMMENT='orders';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 10
         * 
         * Order Items Table
         * 
         * 订单明细表
         *  - id: 订单明细ID
         *  - order_id: 订单ID
         *  - product_id: 商品ID
         *  - sku_id: 商品SKU ID
         *  - quantity: 购买数量
         *  - unit_price: 单价
         *  - total_price: 总价
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_order_items';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_id` BIGINT NOT NULL,
                `product_id` BIGINT NOT NULL,
                `sku_id` BIGINT NOT NULL,
                -- 快照 对账
                `product_title` VARCHAR(255) DEFAULT NULL,
                `product_image` VARCHAR(255) DEFAULT NULL,
                `sku_name` VARCHAR(255) DEFAULT NULL,
                `unit_price` DECIMAL(10,2) NOT NULL,
                `quantity` INT NOT NULL DEFAULT 1,
                `total_price` DECIMAL(10,2) NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_order_id` (`order_id`),
                KEY `idx_sku_id` (`sku_id`),
                KEY `idx_order_sku` (`order_id`, `sku_id`)
            ) ENGINE=InnoDB $charset COMMENT='order items';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 11
         * 
         * Order Address Table
         * 
         * 订单地址表
         *  - id: 主键
         *  - order_id: 订单ID
         *  - user_id: 用户ID
         *  - name: 收货人姓名
         *  - phone: 手机号
         *  - country: 国家
         *  - province: 省份
         *  - city: 城市
         *  - district: 区域
         *  - address: 详细地址
         *  - postcode: 邮编
         *  - created_at: 创建时间
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_order_address';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_id` BIGINT DEFAULT NULL,
                `user_id` BIGINT DEFAULT NULL,
                `name` VARCHAR(100) DEFAULT NULL,
                `phone` VARCHAR(20) DEFAULT NULL,
                `country` VARCHAR(50) DEFAULT NULL,
                `province` VARCHAR(50) DEFAULT NULL,
                `city` VARCHAR(50) DEFAULT NULL,
                `district` VARCHAR(50) DEFAULT NULL,
                `address` VARCHAR(255) DEFAULT NULL,
                `postcode` VARCHAR(10) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `idx_order_id` (`order_id`),
                KEY `idx_user_id` (`user_id`)
            ) ENGINE=InnoDB $charset COMMENT='order address';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 12
         * 
         * Order Delivery Table
         * 
         * 订单物流配送表
         *  - id: 主键
         *  - order_id: 关联订单ID
         *  - method: 配送方式
         *  - code: 物流单号编码
         *  - company: 物流公司名称
         *  - shipped_at: 配送状态
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_order_delivery';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_id` BIGINT UNSIGNED DEFAULT NULL,
                `method` tinyint(4) DEFAULT NULL,
                `code` varchar(64) DEFAULT NULL,
                `company` varchar(64) DEFAULT NULL,
                `shipped_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY uniq_order_delivery (order_id, method),
                KEY `idx_code` (`code`),
                KEY `idx_order_id` (`order_id`)
            ) ENGINE=InnoDB $charset COMMENT='order delivery';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 13
         * 
         * Fund Flow Table
         * 
         * 资金流水表
         *  - id: 主键，流水ID
         *  - user_id: 用户ID
         *  - order_id: 订单ID
         *  - type: 流水类型, 0: recharge, 1: order, 2: refund, 3: withdraw, 4: commission, 5: transfer
         *  - amount: 变动金额（正数代表进账，负数代表出账）
         *  - before_balance: 变动前余额
         *  - after_balance: 变动后余额
         *  - remark: 备注
         *  - created_at: 创建时间
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_fund_flow';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` BIGINT NOT NULL,
                `order_id` BIGINT DEFAULT NULL,
                `type` tinyint(4) NOT NULL,
                `amount` decimal(10,2) NOT NULL,
                `before_balance` decimal(10,2) NOT NULL,
                `after_balance` decimal(10,2) NOT NULL,
                `remark` varchar(255) DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_type` (`type`)
            ) ENGINE=InnoDB $charset COMMENT='fund flow';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 14
         * 
         * Payment Log Table
         * 
         * 支付记录表
         *  - id: 主键
         *  - order_id: 订单ID
         *  - user_id: 用户ID
         *  - pay_amount: 支付金额
         *  - pay_method: 支付方式, 0: 余额, 1: 微信, 2: 支付宝
         *  - transaction_id: 第三方支付流水号
         *  - status: 支付状态, 0: pending, 1: success, 2: failed, 3: refunded
         *  - paid_at: 支付成功时间
         *  - raw_response: 原始回调数据（用于对账，JSON格式存储）
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_payment_log';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_id` BIGINT DEFAULT NULL,
                `user_id` BIGINT NOT NULL,
                `pay_amount` DECIMAL(10,2) NOT NULL,
                `pay_method` tinyint(4) NOT NULL,
                `transaction_id` varchar(255) DEFAULT NULL,
                `status` tinyint(4) NOT NULL,
                `paid_at` datetime DEFAULT NULL,
                `raw_response` longtext,
                PRIMARY KEY (`id`),
                KEY `idx_user_id` (`user_id`),
                UNIQUE KEY `idx_order_id` (`order_id`),
                UNIQUE KEY `idx_transaction_id` (`transaction_id`)
            ) ENGINE=InnoDB $charset COMMENT='payment log';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 15
         * 
         * Withdraw Apply Table
         * 
         * 提现申请表
         *  - id: 主键
         *  - user_id: 用户ID
         *  - amount: 申请提现金额
         *  - fee: 手续费
         *  - actual_amount: 实际到账金额
         *  - account_type: 提现账户类型
         *  - account_info: 提现账户信息（JSON存储，如卡号后四位、支付宝账号）需加密存储
         *  - status: 状态, 0: pending, 1: approved, 2: rejected, 3: paid
         *  - admin_remark: 管理员备注
         *  - applied_at: 申请时间
         *  - processed_at: 处理时间
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_withdraw_apply';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` BIGINT NOT NULL,
                `amount` DECIMAL(10,2) NOT NULL,
                `fee` DECIMAL(10,2) NOT NULL,
                `actual_amount` DECIMAL(10,2) NOT NULL,
                `account_type` tinyint(4) NOT NULL,
                `account_info` longtext NOT NULL,
                `status` tinyint(4) NOT NULL,
                `admin_remark` varchar(255) DEFAULT NULL,
                `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `processed_at` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB $charset COMMENT='withdraw apply';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 16
         * 
         * Distribution Relation Table
         * 
         * 分销与佣金表
         *  - id: 主键
         *  - user_id: 下级用户 ID
         *  - parent_id: 上级用户 ID（分销商）
         *  - root_id: 顶级用户 ID（防止多级分销过深，记录源头）
         *  - level: 关系层级（如一级分销、二级分销）
         *  - created_at: 创建时间
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_distribution_relation';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` BIGINT NOT NULL,
                `parent_id` BIGINT NOT NULL,
                `root_id` BIGINT NOT NULL,
                `level` tinyint(4) NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_user_id` (`user_id`)
            ) ENGINE=InnoDB $charset COMMENT='distribution relation';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 17
         * 
         * Commission Log Table
         * 
         * 佣金记录表
         *  - id: 主键
         *  - order_id: 关联订单ID
         *  - user_id: 获得佣金的用户（分销商）ID
         *  - parent_id: 上级分销商（如果是二级分销）ID
         *  - amount: 佣金金额
         *  - type: 佣金类型, 1: level1, 2: level2
         *  - status: 状态, 0: pending, 1: settled, 2: locked, 3: cancelled
         *  - triggered_at: 触发时间（订单完成时间）
         *  - settled_at: 结算时间
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_commission_log';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_id` BIGINT UNSIGNED NOT NULL,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `parent_id` BIGINT UNSIGNED NOT NULL,
                `amount` DECIMAL(10, 2) NOT NULL,
                `type` tinyint(4) NOT NULL,
                `status` tinyint(4) NOT NULL,
                `triggered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `settled_at` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_parent_id` (`parent_id`),
                KEY `idx_order_id` (`order_id`)
            ) ENGINE=InnoDB $charset COMMENT='commission log';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 18
         * 
         * coupon table
         * 
         * 营销与优惠券表
         *  - id: 主键, 优惠券ID
         *  - title: 优惠券名称
         *  - type: 优惠券类型, 0: fixed 满减, 1: percent 折扣
         *  - value: 优惠券值（如满100减10，则存10）
         *  - min_amount: 优惠券使用最低金额
         *  - total_count: 优惠券总发行量
         *  - used_count: 已领取数量
         *  - start_time: 有效开始时间
         *  - end_time: 有效结束时间
         *  - product_scope: 优惠券使用范围, 0: all 全部商品, 2: single 指定商品, 3: category 指定分类, 4: brand 指定品牌
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_coupon';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(255) NOT NULL,
                `type` tinyint(4) NOT NULL,
                `value` decimal(10,2) NOT NULL,
                `min_amount` decimal(10,2) NOT NULL,
                `total_count` int(11) NOT NULL,
                `used_count` int(11) NOT NULL,
                `start_time` DATETIME NOT NULL,
                `end_time` DATETIME NOT NULL,
                `product_scope` tinyint(4) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_type` (`type`)
            ) ENGINE=InnoDB $charset COMMENT='coupons';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 19
         * 
         * User Coupon Table
         * 
         * 用户优惠券表
         *  - id: 主键
         *  - user_id: 用户ID
         *  - coupon_id: 优惠券ID
         *  - status: 状态
         *  - received_at: 领取时间
         *  - used_at: 使用时间
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_user_coupon';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `coupon_id` BIGINT UNSIGNED NOT NULL,
                `status` TINYINT(4) NOT NULL,
                `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `used_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB $charset COMMENT='user coupons';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 20
         * 
         * Wechat Official Account Menus Table
         * 
         * 微信公众号菜单表
         *  - id: 主键
         *  - parent: 父级菜单ID
         *  - name: 菜单名称
         *  - sort: 菜单排序
         *  - type: 菜单类型
         *  - value: 菜单值
         *  - app_id: 小程序应用ID
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_wechat_oa_menus';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `parent` int(11) NOT NULL,
                `name` varchar(128) NOT NULL,
                `sort` tinyint(4) NOT NULL,
                `type` tinyint(4) NOT NULL,
                `value` varchar(255) NOT NULL,
                `app_id` varchar(64) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_parent` (`parent`)
            ) ENGINE=InnoDB $charset COMMENT='wechat OA menus';";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 21
         * 
         * Wechat Official Account Messages Table
         * 
         * 微信公众号消息表
         *  - id: 消息表字段id
         *  - msgid: 微信消息id
         *  - openid: 用户唯一标识
         *  - nickname: 用户昵称（可选）
         *  - type: 消息类型: text/image/voice/video/location/link/event等
         *  - content: 消息内容: 文本内容/事件描述/媒体ID/地理位置信息/链接信息等
         *  - created_at: 消息创建时间 UTC 时间
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_wechat_oa_messages';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `msgid` varchar(64) NOT NULL,
                `openid` varchar(64) NOT NULL,
                `nickname` varchar(128) NOT NULL,
                `type` varchar(32) NOT NULL,
                `content` longtext NOT NULL,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_openid` (`openid`),
                KEY `idx_type` (`type`),
                KEY `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB $charset COMMENT='wechat OA messages';";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 22
         * 
         * Wechat Official Account Reply Table
         * 
         * 微信公众号应答表
         *  - id: 应答表字段id
         *  - type: 应答类型: text/image/voice/video/location/link/event等
         *  - content: 应答内容
         *  - status: 应答状态: 1: enabled, 0: disabled, default: 1
         *  - created_at: 创建时间 UTC 时间
         *  - updated_at: 更新时间 UTC 时间
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_wechat_oa_reply';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `type` varchar(32) NOT NULL,
                `content` longtext NOT NULL,
                `status` tinyint(4) NOT NULL DEFAULT 1,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB $charset COMMENT='wechat OA reply';";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 23
         * 
         * Wechat Official Account Reply Keyword Table
         * 
         * 微信公众号应答关键词关联表
         *  - reply_id: 关联的应答id
         *  - keyword: 触发关键词
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_wechat_oa_reply_keyword';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `reply_id` BIGINT UNSIGNED NOT NULL,
                `keyword` varchar(100) NOT NULL,
                PRIMARY KEY (`reply_id`, `keyword`),
                UNIQUE KEY `unique_keyword` (`keyword`)
            ) ENGINE=InnoDB $charset COMMENT='wechat OA reply keyword';";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 24
         * 
         * Ad Table
         * 
         * 广告表
         *  - id: 主键, 广告ID
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_ads';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `order_id` BIGINT UNSIGNED DEFAULT NULL,
                `status` tinyint(4) NOT NULL,
                `title` varchar(128) NOT NULL,
                `description` varchar(255) NOT NULL,
                `media` varchar(255) NOT NULL,
                `start_time` datetime NOT NULL,
                `end_time` datetime NOT NULL,
                `location` varchar(32) NOT NULL,
                `link` varchar(255) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_location` (`location`),
                KEY `idx_start_time` (`start_time`),
                KEY `idx_end_time` (`end_time`)
            ) ENGINE=InnoDB $charset COMMENT='ads';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * 25
         * 
         * Invite Code Table
         * 
         * 邀请码表。存储邀请码信息，如邀请码、有效期、创建类型（生成/用户购买）、过期时间、状态（未使用/已使用）、创建用户id、创建时间、被邀请用户id、使用时间等
         *  - id: 主键
         *  - code: 邀请码
         *  - source: 来源，0: 系统生成, 2: 用户购买
         *  - start_time: 生效时间
         *  - end_time: 失效时间
         *  - status: 状态，0: 未使用, 1: 已使用
         *  - creator_id: 创建用户id
         *  - invitee_id: 被邀请用户id
         *  - created_at: 创建时间
         *  - used_at: 使用时间
         * 
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_invite_codes';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `code` varchar(64) NOT NULL,
                `source` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0 generate 1 user buy',
                `start_time` DATETIME NOT NULL,
                `end_time` DATETIME NOT NULL,
                `status` tinyint(4) NOT NULL COMMENT 'status 0 unused 1 used',
                `creator_id` BIGINT UNSIGNED DEFAULT NULL,
                `invitee_id` BIGINT UNSIGNED DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `used_at` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_code` (`code`),
                KEY `idx_source` (`source`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB $charset COMMENT='invite codes';";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
}