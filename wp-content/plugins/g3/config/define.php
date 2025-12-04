<?php
/**
 * 
 * Define the CONSTANTS
 *  - 1. WordPress核心目录
 *  - 2. G3核心目录和URL
 *  - 3. G3核心常量
 *  - 4. G3当前主题目录和URL（如果启用了子主题，则返回子主题）
 * 
 * @since 1.0.0
 * @author Wang Shai
 * 
 */



/**
 * 
 * 1. WordPress核心目录
 * Define WordPress Core Directory
 * 
 */

/**
 * 网站根目录
 * Define WordPress Root Directory
 * @since 1.0.0
 */
defined('G3_ROOT') or define('G3_ROOT', ABSPATH);



/**
 * 
 * 2. G3核心目录和URL
 * Define G3 Core Directory & URL
 * 
 */

/**
 * G3插件目录和URL
 * Define G3 Plugin Directory & URL
 * @since 1.0.0
 */
defined('G3_PlUGIN_DIR') or define('G3_PlUGIN_DIR', WP_PLUGIN_DIR . '/g3');
defined('G3_PlUGIN_URL') or define('G3_PlUGIN_URL', WP_PLUGIN_URL . '/g3');

/**
 * G3 Bin 目录
 * Define G3 Bin Directory
 * @since 1.0.0
 */
defined('G3_BIN_DIR') or define('G3_BIN_DIR', G3_PlUGIN_DIR . '/bin');

/**
 * G3组件目录
 * Define G3 Component Directory
 * @since 1.0.0
 */
defined('G3_COMPONENT_DIR') or define('G3_COMPONENT_DIR', G3_PlUGIN_DIR . '/src/Components');

/**
 * G3扩展目录
 * Define G3 Extension Directory
 * @since 1.0.0
 */
defined('G3_EXT_DIR') or define('G3_EXT_DIR', G3_PlUGIN_DIR . '/extensions');

/**
 * G3第三方库目录
 * Define G3 Third Party Library Directory & URL
 * @since 1.0.0
 */
defined('G3_LIB_DIR') or define('G3_LIB_DIR', G3_PlUGIN_DIR . '/library');

/**
 * G3 SRC 目录
 * Define G3 SRC Directory & URL
 * @since 1.0.0
 */
defined('G3_SRC_DIR') or define('G3_SRC_DIR', G3_PlUGIN_DIR . '/src');

/**
 * G3公共资源目录和URL
 * Define G3 Public Directory & URL
 * @since 1.0.0
 */
defined('G3_PUBLIC_DIR') or define('G3_PUBLIC_DIR', G3_PlUGIN_DIR . '/public');
defined('G3_PUBLIC_URL') or define('G3_PUBLIC_URL', G3_PlUGIN_URL . '/public');

/**
 * G3 JS资源目录和URL
 * Define G3 Javascript Assets Directory & URL
 * @since 1.0.0
 */
defined('G3_JS_DIR') or define('G3_JS_DIR', G3_PUBLIC_DIR . '/javascript');
defined('G3_JS_URL') or define('G3_JS_URL', G3_PUBLIC_URL . '/javascript');

/**
 * G3 CSS资源目录和URL
 * Define G3 CSS Assets Directory & URL
 * @since 1.0.0
 */
defined('G3_CSS_DIR') or define('G3_CSS_DIR', G3_PUBLIC_DIR . '/css');
defined('G3_CSS_URL') or define('G3_CSS_URL', G3_PUBLIC_URL . '/css');

/**
 * G3 字体资源目录和URL
 * Define G3 Font Assets Directory & URL
 * @since 1.0.0
 */
defined('G3_FONT_DIR') or define('G3_FONT_DIR', G3_PUBLIC_DIR . '/fonts');
defined('G3_FONT_URL') or define('G3_FONT_URL', G3_PUBLIC_URL . '/fonts');

/**
 * G3 Images资源目录和URL
 * Define G3 Image Assets Directory & URL
 * @since 1.0.0
 */
defined('G3_IMG_DIR') or define('G3_IMG_DIR', G3_PUBLIC_DIR . '/images');
defined('G3_IMG_URL') or define('G3_IMG_URL', G3_PUBLIC_URL . '/images');

/**
 * G3语言资源目录和URL
 * Define G3 Language Directory & URL
 * @since 1.0.0
 */
defined('G3_LANG_DIR') or define('G3_LANG_DIR', G3_PUBLIC_DIR . '/languages');
defined('G3_LANG_URL') or define('G3_LANG_URL', G3_PUBLIC_URL . '/languages');

/**
 * G3语音资源目录和URL
 * Define G3 Audio Directory & URL
 * @since 1.0.0
 */
defined('G3_AUDIO_DIR') or define('G3_AUDIO_DIR', G3_PUBLIC_DIR . '/audios');
defined('G3_AUDIO_URL') or define('G3_AUDIO_URL', G3_PUBLIC_URL . '/audios');

/**
 * G3视频资源目录和URL
 * Define G3 Video Directory & URL
 * @since 1.0.0
 */
defined('G3_VIDEO_DIR') or define('G3_VIDEO_DIR', G3_PUBLIC_DIR . '/videos');
defined('G3_VIDEO_URL') or define('G3_VIDEO_URL', G3_PUBLIC_URL . '/videos');



/**
 * 
 * 3. 定义G3常量
 * Define G3 CONSTANTS
 * 
 */

/**
 * 定义 G3 版本号
 * Define G3 Version
 * @since 1.0.0
 */
defined('G3_VERSION') or define('G3_VERSION', '1.0.0');

/**
 * 定义 G3 大版本号
 * Define G3 Version Number
 * @since 1.0.0
 */
defined('G3_VERNUM') or define('G3_VERNUM', '1');

/**
 * Define Api Version
 * 定义 Api 版本号
 * @since 1.0.0
 */
defined('G3_API_VERSION') or define('G3_API_VERSION', 'v1');

/**
 * 定义 G3 名称
 * Define G3 Name
 * @since 1.0.0
 */
defined('G3_NAME') or define('G3_NAME', 'G3 System');

/**
 * 定义 G3 别名
 * Define G3 Alias
 * @since 1.0.0
 */
defined('G3_ALIAS') or define('G3_ALIAS', 'Raven');

/**
 * 定义 G3 演示站点URL
 * Define G3 Preview WebSite URL
 * @since 1.0.0
 */
defined('G3_URL') or define('G3_URL', 'https://www.g3system.com');

/**
 * 定义 JEALER 官网 URL
 * Define JEALER WebSite URL
 * @since 1.0.0
 */
defined('JL_URL') or define('JL_URL', 'https://www.jealer.com');


/**
 * Key
 */
// 加密Key
// defined('JL_KEY') or define('JL_KEY', 'JEALER_G3');
// 加密向量
// defined('JL_IV') or define('JL_IV', 'JL_IV');



/**
 * 
 * 4. G3当前主题目录和URL（如果启用了子主题，则返回子主题）
 * Define Current G3 Theme Directory & URL (return the Child Theme if it is active)
 * 
 */

/**
 * 当前主题目录和URL
 * Define Current Theme Directory & URL
 * @since 1.0.0
 */
defined('G3_THEME_DIR') or define('G3_THEME_DIR', get_stylesheet_directory());
defined('G3_THEME_URL') or define('G3_THEME_URL', get_stylesheet_directory_uri());

/**
 * 当前主题配置目录
 * Define Current Theme Config Directory
 * @since 1.0.0
 */
defined('G3_THEME_CONFIG_DIR') or define('G3_THEME_CONFIG_DIR', G3_THEME_DIR . '/config');

/**
 * 当前主题局部模板目录
 * Define Current Theme Part Templates Directory
 * @since 1.0.0
 */
defined('G3_THEME_PARTS_DIR') or define('G3_THEME_PARTS_DIR', get_stylesheet_directory() . '/parts');

/**
 * 当前主题公共资源目录和URL
 * Define Current Theme Public Assets Directory & URL
 * @since 1.0.0
 */
defined('G3_THEME_PUBLIC_DIR') or define('G3_THEME_PUBLIC_DIR', get_stylesheet_directory() . '/public');
defined('G3_THEME_PUBLIC_URL') or define('G3_THEME_PUBLIC_URL', get_stylesheet_directory_uri() . '/public');

/**
 * 当前主题js资源目录和URL
 * Define Current Theme JS Assets Directory & URL
 * @since 1.0.0
 */
defined('G3_THEME_JS_DIR') or define('G3_THEME_JS_DIR', G3_THEME_PUBLIC_DIR . '/javascript');
defined('G3_THEME_JS_URL') or define('G3_THEME_JS_URL', G3_THEME_PUBLIC_URL . '/javascript');

/**
 * 当前主题css资源目录和URL
 * Define Current Theme CSS Assets Directory & URL
 * @since 1.0.0
 */
defined('G3_THEME_CSS_DIR') or define('G3_THEME_CSS_DIR', G3_THEME_PUBLIC_DIR . '/css');
defined('G3_THEME_CSS_URL') or define('G3_THEME_CSS_URL', G3_THEME_PUBLIC_URL . '/css');

/**
 * 当前主题图片资源目录和URL
 * Define Current Theme Images Assets Directory & URL
 * @since 1.0.0
 */
defined('G3_THEME_IMG_DIR') or define('G3_THEME_IMG_DIR', G3_THEME_PUBLIC_DIR . '/images');
defined('G3_THEME_IMG_URL') or define('G3_THEME_IMG_URL', G3_THEME_PUBLIC_URL . '/images');

/**
 * 当前主题音频资源目录和URL
 * Define Current Theme Audio Assets Directory & URL
 * @since 1.0.0
 */
defined('G3_THEME_AUDIO_DIR') or define('G3_THEME_AUDIO_DIR', G3_THEME_PUBLIC_DIR . '/audios');
defined('G3_THEME_AUDIO_URL') or define('G3_THEME_AUDIO_URL', G3_THEME_PUBLIC_URL . '/audios');

/**
 * 当前主题视频资源目录和URL
 * Define Current Theme Video Assets Directory & URL
 * @since 1.0.0
 */
defined('G3_THEME_VIDEO_DIR') or define('G3_THEME_VIDEO_DIR', G3_THEME_PUBLIC_DIR . '/videos');
defined('G3_THEME_VIDEO_URL') or define('G3_THEME_VIDEO_URL', G3_THEME_PUBLIC_URL . '/videos');
