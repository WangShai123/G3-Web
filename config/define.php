<?php
/**
 * 1. about G3 
 */
// plugin
defined('G3_PLUGIN_DIR') or define('G3_PLUGIN_DIR', WP_PLUGIN_DIR . '/g3-web');
defined('G3_PLUGIN_URL') or define('G3_PLUGIN_URL', WP_PLUGIN_URL . '/g3-web');
// bin
defined('G3_BIN_DIR') or define('G3_BIN_DIR', G3_PLUGIN_DIR . '/bin');
// config
defined('G3_CONFIG_DIR') or define('G3_CONFIG_DIR', G3_PLUGIN_DIR . '/config');
// component
defined('G3_COMPONENT_DIR') or define('G3_COMPONENT_DIR', G3_PLUGIN_DIR . '/src/Components');
// extension
defined('G3_EXT_DIR') or define('G3_EXT_DIR', G3_PLUGIN_DIR . '/extensions');
// library
defined('G3_LIB_DIR') or define('G3_LIB_DIR', G3_PLUGIN_DIR . '/library');
// src
defined('G3_SRC_DIR') or define('G3_SRC_DIR', G3_PLUGIN_DIR . '/src');
// template
defined('G3_TEMPLATE_DIR') or define('G3_TEMPLATE_DIR', G3_PLUGIN_DIR . '/templates');
// public
defined('G3_PUBLIC_DIR') or define('G3_PUBLIC_DIR', G3_PLUGIN_DIR . '/public');
defined('G3_PUBLIC_URL') or define('G3_PUBLIC_URL', G3_PLUGIN_URL . '/public');
defined('G3_JS_URL') or define('G3_JS_URL', G3_PUBLIC_URL . '/js');
defined('G3_CSS_URL') or define('G3_CSS_URL', G3_PUBLIC_URL . '/css');
defined('G3_FONT_URL') or define('G3_FONT_URL', G3_PUBLIC_URL . '/font');
defined('G3_IMG_URL') or define('G3_IMG_URL', G3_PUBLIC_URL . '/img');
defined('G3_LANG_URL') or define('G3_LANG_URL', G3_PUBLIC_URL . '/languages');
defined('G3_AUDIO_URL') or define('G3_AUDIO_URL', G3_PUBLIC_URL . '/audio');
defined('G3_VIDEO_URL') or define('G3_VIDEO_URL', G3_PUBLIC_URL . '/video');
// assets
defined('G3_ASSETS_DIR') or define('G3_ASSETS_DIR', G3_PLUGIN_DIR . '/assets');
defined('G3_ASSETS_URL') or define('G3_ASSETS_URL', G3_PLUGIN_URL . '/assets');
// dist
defined('G3_DIST_DIR') or define('G3_DIST_DIR', G3_PLUGIN_DIR . '/dist');
defined('G3_DIST_URL') or define('G3_DIST_URL', G3_PLUGIN_URL . '/dist');

// version
defined('G3_VERSION') or define('G3_VERSION', '1.0.0');
// name
defined('G3_NAME') or define('G3_NAME', 'G3-Web');
// alias
defined('G3_ALIAS') or define('G3_ALIAS', 'Raven');

/**
 * 2. about current theme (return the child theme if it is active)
 */
// theme
defined('G3_THEME_DIR') or define('G3_THEME_DIR', get_stylesheet_directory());
defined('G3_THEME_URL') or define('G3_THEME_URL', get_stylesheet_directory_uri());
// theme config
defined('G3_THEME_CONFIG_DIR') or define('G3_THEME_CONFIG_DIR', G3_THEME_DIR . '/config');
// theme parts
defined('G3_THEME_PARTS_DIR') or define('G3_THEME_PARTS_DIR', get_stylesheet_directory() . '/parts');
// theme assets
defined('G3_THEME_ASSETS_DIR') or define('G3_THEME_ASSETS_DIR', get_stylesheet_directory() . '/assets');
defined('G3_THEME_ASSETS_URL') or define('G3_THEME_ASSETS_URL', get_stylesheet_directory_uri() . '/assets');
defined('G3_THEME_JS_URL') or define('G3_THEME_JS_URL', G3_THEME_ASSETS_URL . '/js');
defined('G3_THEME_CSS_URL') or define('G3_THEME_CSS_URL', G3_THEME_ASSETS_URL . '/css');
defined('G3_THEME_IMG_URL') or define('G3_THEME_IMG_URL', G3_THEME_ASSETS_URL . '/img');
defined('G3_THEME_AUDIO_URL') or define('G3_THEME_AUDIO_URL', G3_THEME_ASSETS_URL . '/audio');
defined('G3_THEME_VIDEO_URL') or define('G3_THEME_VIDEO_URL', G3_THEME_ASSETS_URL . '/video');


/**
 * 3. about common constants
 */
defined('ADMIN_CACHE_TTL') or define('ADMIN_CACHE_TTL', 3600); // 1 hour
defined('USER_CACHE_TTL') or define('USER_CACHE_TTL', 86400); // 1 day
defined('COMMON_CACHE_TTL') or define('COMMON_CACHE_TTL', 604800); // 1 week
