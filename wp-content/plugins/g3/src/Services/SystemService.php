<?php
namespace JEALER\G3\Services;
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
     * Open Platform Wechat MP Key
     * 
     * 开放平台微信公众号Key
     * 
     * @var string
     * @since 1.0.0
     * @author Wang Shai
     */
    public const OPEN_MP_KEY = 'g3_option_op_wechatMP';

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
}