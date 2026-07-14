<?php
namespace JEALER\G3\Services;
use JEALER\G3\Core\Service\Service;
use JEALER\G3\Utilities\System;
use Throwable;
use Redis;

class SystemService extends Service {
    // general setting option Key
    const OPTION_KEY = 'g3_option_general';
    // ICP Link
    const ICP_LINK = 'https://beian.miit.gov.cn/';
    // seo option key
    const SEO_OPTION_KEY = 'g3_option_seo';
    // rss option key
    const RSS_OPTION_KEY = 'g3_option_rss';
    // llm option key
    const LLM_OPTION_KEY = 'g3_option_llm';
    // form option key
    const FORM_OPTION_KEY = 'g3_option_dev_form';
    // dev setting option key
    const SETTING_OPTION_KEY = 'g3_option_dev_setting';
    const K                  = 'wPxK91qZ';
    const TARGET             = 'g3Verify';
    // securities option key
    const SECURITY_OPTION_KEY = 'g3_option_securities';
    // performance option key
    const PERFORMANCE_OPTION_KEY = 'g3_option_performance';
    // theme option key
    const THEME_OPTION_KEY = 'g3_option_themes';
    // wechat open platform option key
    const OPEN_WECHAT_OA_KEY = 'g3_option_op_wechatOA';
    public function __construct()
    {
        parent::__construct();
    }

    public static function optionValue(): array
    {
        return [
            'sad'          => '0',
            'avatar'       => G3_IMG_URL . '/avatar.png',
            'cover'        => G3_IMG_URL . '/cover-placeholder.png',
            'icp'          => '',
            'headerCode'   => '',
            'footerCode'   => '',
            'customCode'   => '',
            'links'        => '1',
            'redirectLink' => '1',
            'online'       => '0',
            'onlineDelay'  => '30',
        ];
    }

    public function getOnlineCount(): int|bool
    {
        $option = get_option(self::OPTION_KEY, self::optionValue())['online'] ?? '0';
        if ($option !== '1') {
            return false;
        }
        $redis = $this->container->get(Redis::class);
        // return $redis->scard('g3:g3_online:online') + 1;
        // return $redis->pfcount('g3:g3_hll:online') + 1;
        return $redis->zcount('g3:g3_zset:online', time(), '+inf');
    }

    /**
     * Get system service option
     * 
     * 获取系统配置项
     * 
     * @return array
     */
    public static function option(): array
    {
        $value = get_option(self::OPTION_KEY, self::optionValue());
        return is_array($value) ? $value : [];
    }
    /**
     * Get ICP Code
     * 
     * 获取 ICP 备案号
     * 
     * @return string
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
        $a = base64_decode(System::APPLE);
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
     */
    public function initObjectCache(): void
    {
        if (file_exists(WP_CONTENT_DIR . '/object-cache.php')) {
            rename(WP_CONTENT_DIR . '/object-cache.php', WP_CONTENT_DIR . '/object-cache.php.bak');
        }
        copy(WP_PLUGIN_DIR . '/G3-Web/library/redis/object-cache.php', WP_CONTENT_DIR . '/object-cache.php');

        if (file_exists(WP_PLUGIN_DIR . '/G3-Web/extensions/cache/llms.txt')) {
            copy(WP_PLUGIN_DIR . '/G3-Web/extensions/cache/llms.txt', ABSPATH . 'llm/llms.txt');
        }
    }
    /**
     * initCli
     * 
     * 初始化 CLI 命令。创建 g3.php 文件，用于 CLI 命令执行
     * 
     * @return void
     */
    public function initCli(): void
    {
        if (file_exists(WP_PLUGIN_DIR . '/G3-Web/extensions/cache/g3.php')) {
            try {
                copy(WP_PLUGIN_DIR . '/G3-Web/extensions/cache/g3.php', ABSPATH . '/g3.php');
            }
            catch (Throwable $th) {
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
     */
    public static function hasLinkService(): bool
    {
        $data = self::option();
        if (!isset($data['links'])) {
            return false;
        }
        return $data['links'] === '1';
    }
}
