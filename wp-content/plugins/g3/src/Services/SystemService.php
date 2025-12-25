<?php
namespace JEALER\G3\Services;
use JEALER\G3\Utilities\System;
class SystemService {

    /**
     * Option Key
     * 
     * 配置项键名
     * 
     * @var string
     * @since 1.0.0
     * @author Wang Shai
     */
    public const OPTION_KEY = 'g3_option_general';

    /**
     * ICP Link
     * 
     * ICP 备案链接
     * 
     * @var string
     * @since 1.0.0
     * @author Wang Shai
     */
    public const ICP_LINK = 'https://beian.miit.gov.cn/';

    /**
     * SEO Key
     * 
     * SEO 配置项键名
     * 
     * @var string
     * @since 1.0.0
     * @author Wang Shai
     */
    public const SEO_OPTION_KEY = 'g3_option_seo';

    /**
     * RSS Key
     * 
     * RSS 配置项键名
     * 
     * @var string
     * @since 1.0.0
     * @author Wang Shai
     */
    public const RSS_OPTION_KEY = 'g3_option_rss';

    /**
     * Form Key
     * 
     * Form 配置项键名
     * 
     * @var string
     * @since 1.0.0
     * @author Wang Shai
     */
    public const FORM_OPTION_KEY = 'g3_option_dev_form';

    /**
     * Setting Key
     * 
     * Setting 配置项键名
     * 
     * @var string
     * @since 1.0.0
     * @author Wang Shai
     */
    public const SETTING_OPTION_KEY = 'g3_option_dev_setting';

    public const KEY = '5ebec86f4404d2c1';
    public const U   = 'https://api.jealer.com/api/v1/requestVerify';

    /**
     * Security Key
     * 
     * 安全配置项键名
     * 
     * @var string
     * @since 1.0.0
     * @author Wang Shai
     */
    public const SECURITY_OPTION_KEY = 'g3_option_securities';

    /**
     * Theme Key
     * 
     * 主题配置项键名
     * 
     * @var string
     * @since 1.0.0
     * @author Wang Shai
     */
    public const THEME_OPTION_KEY = 'g3_option_themes';

    /**
     * Open Platform Wechat Official Account Key
     * 
     * 开放平台微信公众号Key
     * 
     * @var string
     * @since 1.0.0
     * @author Wang Shai
     */
    public const OPEN_WECHAT_OA_KEY = 'g3_option_op_wechatOA';

    /**
     * Get ICP Code
     * 
     * 获取 ICP 备案号
     * 
     * @return string
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function getIcp(): string
    {
        $option = get_option(self::OPTION_KEY);
        return $option['icp'] ?? '';
    }

    /**
     * Get ICP HTML
     * 
     * 获取 ICP HTML
     * 
     * @return string
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function icpHtml(): string
    {
        return "<a href='" . self::ICP_LINK . "' target='_blank' style='color:inherit'>" . self::getIcp() . "</a>";
    }

    public static function endPoint(): string
    {
        $a = base64_decode(SYSTEM::A);
        $p = array_map('chr', [97, 112, 105, 46, 106, 101, 97, 108, 101, 114, 46, 99, 111, 109, 47, 97, 112, 105, 47, 118, 49, 47]);
        $p = $a . implode('', $p);
        $s = implode('', array_map('chr', [114, 101, 113, 117, 101, 115, 116, 86, 101, 114, 105, 102, 121]));
        return $p . $s;
    }

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
         * Swiper Table
         * 
         * 轮播图表: 存储轮播图信息，如轮播图标题、轮播图链接、轮播图目标（新窗口/当前窗口）、轮播图媒体（图片或视频）、轮播图发布位置、轮播图排序、轮播图状态等
         * 
         * @var string $tableName 轮播图表名
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_swipers';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'id',
                `title` varchar(255) NOT NULL COMMENT 'Title',
                `link` varchar(255) NOT NULL COMMENT 'Link URL',
                `target` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Target 0 self 1 blank',
                `media` varchar(255) NOT NULL COMMENT 'Image URL',
                `location` varchar(255) NOT NULL COMMENT 'Location',
                `sort` int(11) NOT NULL DEFAULT '1' COMMENT 'Sort Order',
                `status` tinyint(4) NOT NULL COMMENT 'Status 1 online 0 offline',
                `user` int(11) NOT NULL COMMENT 'user id',
                `created` datetime NOT NULL COMMENT 'create time',
                `updated` datetime NOT NULL COMMENT 'update time',
                PRIMARY KEY (`id`),
                KEY `location` (`location`)
            ) ENGINE=InnoDB $charset COMMENT='swipers table';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * SKU Table
         * 
         * SKU 表: 存储商品 SKU 信息，如商品 ID、SKU 编码、属性、价格、库存、状态、创建时间、更新时间等
         * 
         * @var string $tableName SKU table name: g3_sku
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_sku';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `product_id` BIGINT UNSIGNED NOT NULL,
                `code` VARCHAR(64) NOT NULL,
                `type` tinyint(4) DEFAULT NULL COMMENT '1: 实物商品, 2: 虚拟商品, 3: 会员商品',
                `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `regular_price` DECIMAL(12,2) DEFAULT NULL,
                `sale_price` DECIMAL(12,2) DEFAULT NULL,
                `attributes` TEXT NOT NULL COMMENT 'JSON Data',
                `stock` INT NOT NULL DEFAULT 0,
                `status` tinyint(1) DEFAULT 1 COMMENT '1: online, 0: offline',
                `created` DATETIME NOT NULL COMMENT 'created time',
                `updated` DATETIME NOT NULL COMMENT 'updated time',
                PRIMARY KEY (`id`),
                INDEX (`product_id`)
            ) ENGINE=InnoDB $charset COMMENT='sku table';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * Product Attributes Table
         * 
         * 商品属性表。存储商品属性信息，如商品 ID、属性名称、属性选项等
         * 
         * @var string $tableName 商品属性表名
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_product_attributes';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `product_id` BIGINT UNSIGNED NOT NULL,
                `name` VARCHAR(60) NOT NULL COMMENT 'Attribute name',
                `options` TEXT NOT NULL COMMENT 'JSON Data',
                PRIMARY KEY (`id`),
                INDEX (`product_id`)
            ) ENGINE=InnoDB $charset COMMENT='product attributes table';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * Order Table
         * 
         * 订单表。存储订单信息，如订单号、买家用户id、订单来源、订单类型、订单状态、订单金额、支付方式、创建时间、更新时间等
         * 
         * @var string $tableName 订单表名
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_orders';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '自增主键 id',
                `order_number` varchar(32) NOT NULL COMMENT '订单号',
                `user_id` int(11) NOT NULL COMMENT '买家用户id',
                `order_source` tinyint(4) NOT NULL COMMENT '订单来源',
                `order_type` tinyint(4) NOT NULL COMMENT '订单类型',
                `order_status` tinyint(4) NOT NULL COMMENT '订单状态',
                `order_amount` decimal(10,2) NOT NULL COMMENT '订单金额',
                `pay_method` tinyint(4) NULL COMMENT '支付方式',
                `create_time` timestamp NOT NULL COMMENT '创建时间',
                `update_time` timestamp NULL COMMENT '更新时间',
                `product_id` int(16) NULL COMMENT '订单商品关联表 id',
                `cost_id` int(16) NULL COMMENT '订单费用关联表 id',
                `bill_id` int(16) NULL COMMENT '订单账单关联表 id',
                `address_id` int(16) NULL COMMENT '订单收货地址关联表 id',
                `delivery_method` tinyint(4) NULL COMMENT '配送方式',
                `logistics_number` varchar(32) NULL COMMENT '物流单号',
                `buyer_notes` varchar(255) NULL COMMENT '买家备注',
                `seller_notes` varchar(255) NULL COMMENT '卖家备注',
                `seller_id` int(11) NOT NULL COMMENT '卖家用户id',
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_order_number` (`order_number`),
                KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB $charset COMMENT='orders table';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        // 订单商品表(快照) jl_order_products

        // 订单费用表 jl_order_costs

        // 收货地址表 jl_addresses

        // 物流信息表 jl_logistics 待定

        // 账单表 jl_bills

        // 日志表 jl_logs

        /**
         * Wechat Official Account Menus Table
         * 
         * 微信公众号菜单表。存储微信公众号菜单信息，如菜单id、父菜单id、菜单名称、菜单排序、菜单类型、菜单值等
         * 
         * @var string $tableName 微信公众号菜单表名
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_wechat_oa_menus';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `parent` int(11) NOT NULL,
                `name` varchar(128) NOT NULL,
                `sort` tinyint(4) NOT NULL,
                `type` tinyint(4) NOT NULL,
                `value` varchar(255) NOT NULL,
                `app_id` varchar(64) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `parent` (`parent`)
            ) ENGINE=InnoDB $charset COMMENT='wechat OA menus table';";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * Wechat Official Account Messages Table
         * 
         * 微信公众号消息表。
         *  - id: 消息表字段id
         *  - msgid: 微信消息id
         *  - openid: 用户唯一标识
         *  - nickname: 用户昵称（可选）
         *  - type: 消息类型: text/image/voice/video/location/link/event等
         *  - content: 消息内容: 文本内容/事件描述/媒体ID/地理位置信息/链接信息等
         *  - created: 消息创建时间 UTC 时间
         * 
         * @var string $tableName 微信公众号消息表名
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_wechat_oa_messages';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `msgid` varchar(64) NOT NULL COMMENT '微信消息ID',
                `openid` varchar(64) NOT NULL COMMENT '用户唯一标识',
                `nickname` varchar(128) NOT NULL COMMENT '用户昵称',
                `type` varchar(32) NOT NULL COMMENT '消息类型',
                `content` longtext NOT NULL COMMENT '消息内容',
                `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '消息接收时间 UTC',
                PRIMARY KEY (`id`),
                KEY `openid` (`openid`),
                KEY `type` (`type`),
                KEY `created` (`created`)
            ) ENGINE=InnoDB $charset COMMENT='wechat OA messages table';";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * Wechat Official Account Reply Table
         * 
         * 微信公众号应答表。
         *  - id: 应答表字段id
         *  - type: 应答类型: text/image/voice/video/location/link/event等
         *  - content: 应答内容
         *  - status: 应答状态: 1: enabled, 0: disabled, default: 1
         *  - created: 创建时间 UTC 时间
         *  - updated: 更新时间 UTC 时间
         * 
         * @var string $tableName 微信公众号回复表名
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_wechat_oa_reply';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `type` varchar(32) NOT NULL COMMENT '应答类型',
                `content` longtext NOT NULL COMMENT '应答内容',
                `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '应答状态',
                `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间 UTC',
                `updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间 UTC',
                PRIMARY KEY (`id`),
                KEY `status` (`status`)
            ) ENGINE=InnoDB $charset COMMENT='wechat OA reply table';";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * Wechat Official Account Reply Keyword Table
         * 
         * 微信公众号应答关键词关联表。
         *  - reply_id: 关联的应答id
         *  - keyword: 触发关键词
         * 
         * @var string $tableName 微信公众号应答关键词表名
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_wechat_oa_reply_keyword';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `reply_id` bigint(20) UNSIGNED NOT NULL COMMENT '关联的应答id',
                `keyword` varchar(100) NOT NULL COMMENT '关键词',
                PRIMARY KEY (`reply_id`, `keyword`),
                UNIQUE KEY `unique_keyword` (`keyword`)
            ) ENGINE=InnoDB $charset COMMENT='wechat OA reply keyword table';";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * Ad Table
         * 
         * 广告表。存储广告信息，如广告标题、广告描述、广告媒体（图片或视频）、广告发布用户id、广告开始时间、广告结束时间、广告发布位置、广告订单号、广告链接、广告状态等
         * 
         * @var string $tableName 广告表名
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_ads';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `title` varchar(128) NOT NULL,
                `description` varchar(255) NOT NULL,
                `media` varchar(255) NOT NULL,
                `user` int(11) NOT NULL,
                `start_time` datetime NOT NULL,
                `end_time` datetime NOT NULL,
                `location` varchar(32) NOT NULL,
                `order_number` varchar(16) NULL DEFAULT '',
                `link` varchar(255) NOT NULL,
                `status` varchar(16) NOT NULL DEFAULT '',
                PRIMARY KEY (`id`),
                KEY `location` (`location`)
            ) ENGINE=InnoDB $charset COMMENT='ads table';";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }

        /**
         * Invite Code Table
         * 
         * 邀请码表。存储邀请码信息，如邀请码、有效期、创建类型（生成/用户购买）、过期时间、状态（未使用/已使用）、创建用户id、创建时间、被邀请用户id、使用时间等
         * 
         * @var string $tableName 邀请码表名
         * @since 1.0.0
         * @author Wang Shai
         */
        $tableName  = $wpdb->prefix . 'g3_invite_codes';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'id',
                `code` varchar(50) NOT NULL COMMENT 'invite code',
                `valid_period` int(11) NOT NULL COMMENT 'valid period',
                `create_type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0 generate 1 user buy',
                `expiry_time` timestamp NOT NULL COMMENT 'expiry time',
                `status` tinyint(4) NOT NULL COMMENT 'status 0 unused 1 used',
                `creator_id` int(11) NOT NULL COMMENT 'create user id',
                `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'create time',
                `invitee_id` int(11) NULL COMMENT 'invitee user id',
                `used_time` timestamp NULL COMMENT 'used time',
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_code` (`code`),
                KEY `index_code` (`code`),
                KEY `creator_id` (`creator_id`)
            ) ENGINE=InnoDB $charset COMMENT='invite codes table';";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * initObjectCache
     * 
     * 初始化对象缓存。创建 object-cache.php 文件，用于对象缓存
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function initObjectCache(): void
    {
        if (file_exists(WP_CONTENT_DIR . '/object-cache.php')) {
            rename(WP_CONTENT_DIR . '/object-cache.php', WP_CONTENT_DIR . '/object-cache.php.bak');
        }
        copy(WP_PLUGIN_DIR . '/g3/extensions/object-cache.php', WP_CONTENT_DIR . '/object-cache.php');
    }

    /**
     * initCli
     * 
     * 初始化 CLI 命令。创建 g3.php 文件，用于 CLI 命令执行
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function initCli(): void
    {
        if (file_exists(WP_PLUGIN_DIR . '/g3/extensions/g3.php')) {
            try {
                copy(WP_PLUGIN_DIR . '/g3/extensions/g3.php', ABSPATH . '/g3.php');
            }
            catch (\Throwable $th) {
                throw $th;
            }
        }
    }
}