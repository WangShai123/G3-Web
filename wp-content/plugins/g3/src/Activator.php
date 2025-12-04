<?php
namespace JEALER\G3;

class Activator {
    public static $instance = null;
    public function __construct()
    {
        $this->init();
    }

    /**
     * activate
     * 
     * 插件激活。执行初始化数据库表、注册重写规则等
     * 
     * @return Activator
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function activate(): Activator
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * init
     * 
     * 插件初始化。检查依赖、初始化数据库表、注册重写规则等
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    private function init(): void
    {
        self::checkDependencies();

        $this->initTables();
        $this->registerRewrites();

        $this->initCli();
        $this->initObjectCache();

        $this->param();
    }

    /**
     * registerRewrites
     * 
     * 注册重写规则。刷新重写规则
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    private function registerRewrites(): void
    {
        Rewrite::flushRewriteRules();
    }

    /** @deprecated check PHP version by composer instead. */
    private function checkPHP(): void
    {
        if (version_compare(phpversion(), '8.3', '<')) {
            deactivate_plugins(plugin_basename(G3_PLUGIN_FILE));
            wp_die(
                __('G3-Web requires PHP 8.3+. Please upgrade your PHP version.', 'G3'),
                __('Failed to active G3 Web Plugin!', 'G3'),
                ['back_link' => true]
            );
        }
    }

    private function checkWordPress(): void
    {
        global $wp_version;
        if (version_compare($wp_version, '6.5', '<')) {
            deactivate_plugins(plugin_basename(G3_PLUGIN_FILE));
            wp_die(
                __('G3-Web requires WordPress 6.5+. Please upgrade your WordPress version.', 'G3'),
                __('Failed to active G3 Web Plugin!', 'G3'),
                ['back_link' => true]
            );
        }
    }

    /**
     * dependencies check:
     * - PHP cURL
     * - PHP OpenSSL
     * - PHP SimpleXML
     * - PHP fileinfo
     * - PHP Jealer
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function checkDependencies(): void
    {
        $dependencies = [
            'curl'      => 'PHP cURL extension',
            'openssl'   => 'PHP OpenSSL extension',
            'simplexml' => 'PHP SimpleXML extension',
            'fileinfo'  => 'PHP fileinfo extension',
            // 'jealer'    => 'JEALER PHP extension',
            'redis'     => 'PHP Redis extension'
        ];

        $phpVersion = phpversion();
        $phpVersion = substr($phpVersion, 0, 3);
        $phpVersion = str_replace('.', '', $phpVersion);

        /**
         * @todo: add more version support for jealer.so while new version released
         */
        $path = rtrim(WP_PLUGIN_DIR, '/') . '/g3/extension/' . $phpVersion . '/jealer.so';

        $missingDependencies = [];
        foreach ($dependencies as $key => $value) {
            if (!extension_loaded($key)) {
                $missingDependencies[$key] = $value;
            }
        }
        if (!empty($missingDependencies)) {
            deactivate_plugins(plugin_basename(G3_PLUGIN_FILE));
            foreach ($missingDependencies as $key => $value) {
                if ($key === 'jealer') {
                    wp_die(
                        sprintf(
                            __('<h3>Failed to active G3-Web plugin!</h3>G3-Web requires JEALER PHP extension.<br>Please add the config below in your <b>php.ini</b> file and restart PHP server:<br><b>extension = %s</b>', 'G3'),
                            $path
                        ),
                        __('Failed to active G3-Web plugin!', 'G3'),
                        ['back_link' => true]
                    );
                } else {
                    wp_die(
                        sprintf(
                            __('<h3>Failed to active G3-Web plugin!</h3>G3-Web requires <b>%s</b>.<br>Please install it, then config it in your <strong>php.ini</strong> file and restart PHP server.', 'G3'),
                            $value
                        ),
                        __('Failed to active G3-Web plugin!', 'G3'),
                        ['back_link' => true]
                    );
                }
            }
        }
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
    private function initCli(): void
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
    /**
     * initObjectCache
     * 
     * 初始化对象缓存。创建 object-cache.php 文件，用于对象缓存
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    private function initObjectCache(): void
    {
        if (file_exists(WP_CONTENT_DIR . '/object-cache.php')) {
            rename(WP_CONTENT_DIR . '/object-cache.php', WP_CONTENT_DIR . '/object-cache.php.bak');
        }
        copy(WP_PLUGIN_DIR . '/g3/extensions/object-cache.php', WP_CONTENT_DIR . '/object-cache.php');
    }
    /**
     * initTables
     * 
     * 初始化数据库表。创建订单表、订单商品关联表、订单费用关联表、订单账单关联表、订单收货地址关联表等
     * 
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public function initTables(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        // $engine = $wpdb->get_engine_string();

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
                UNIQUE KEY `order_number` (`order_number`),
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
         * Wechat MP Menus
         */
        $tableName  = $wpdb->prefix . 'g3_wechat_mp_menus';
        $tableExist = $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName;
        if (!$tableExist) {
            $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `parent` int(11) NOT NULL,
                `name` varchar(128) NOT NULL,
                `sort` tinyint(4) NOT NULL,
                `type` tinyint(4) NOT NULL,
                `value` varchar(255) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `parent_id` (`parent_id`)
            ) ENGINE=InnoDB $charset COMMENT='wechat MP table';";

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

    private function param(): void
    {
        add_filter('wp_redirect', function ($location) {
            return add_query_arg('g3-activated', '1', $location);
        });
    }
}