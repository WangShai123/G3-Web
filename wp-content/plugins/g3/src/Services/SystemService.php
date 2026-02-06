<?php
namespace JEALER\G3\Services;

use JEALER\G3\Utilities\Context;
use JEALER\G3\Utilities\System;
use JEALER\G3\Components\Components;

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

    public const K = 'wPxK91qZ';

    public const TARGET = 'g3Verify';

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

    public const QUEUE_OPTION_KEY = 'g3_option_queue';

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
     * 获取系统配置项
     * 
     * @return array
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function option(): array
    {
        return get_option(SystemService::OPTION_KEY) ?? [];
    }

    /**
     * Get ICP Code
     * 
     * 获取 ICP 备案号
     * 
     * @return string
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function icp(): string
    {
        return self::option()['icp'] ?? '';
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
        return "<a href='" . self::ICP_LINK . "' target='_blank' style='color:inherit'>" . self::icp() . "</a>";
    }

    public static function avatar(): string
    {
        return self::option()['avatar'] ?? '';
    }

    public static function cover(): string
    {
        return self::option()['cover'] ?? '';
    }

    public static function code(string $position = 'header')
    {
        $haystack = [
            'header',
            'footer',
            'custom'
        ];
        if (!in_array($position, $haystack)) {
            return '';
        }
        return self::option()["{$position}Code"] ?? '';
    }

    public function endPoint(): string
    {
        $a = base64_decode(SYSTEM::APPLE);
        $p = array_map('chr', [97, 112, 105, 46, 106, 101, 97, 108, 101, 114, 46, 99, 111, 109, 47, 97, 112, 105, 47, 118, 49, 47]);
        $p = $a . implode('', $p);
        $s = implode('', array_map('chr', [114, 101, 113, 117, 101, 115, 116, 86, 101, 114, 105, 102, 121]));
        return $p . $s;
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
        copy(WP_PLUGIN_DIR . '/g3/extensions/cache/object-cache.php', WP_CONTENT_DIR . '/object-cache.php');
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

    /**
     * Check if links feature available
     * 
     * 检查链接功能是否可用
     * 
     * @return bool
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function hasLinkService(): bool
    {
        // $data = get_option(self::OPTION_KEY, []);
        $data = Context::get(self::OPTION_KEY, []);

        if (!isset($data['links'])) {
            return false;
        }
        return $data['links'] === '1';
    }
}