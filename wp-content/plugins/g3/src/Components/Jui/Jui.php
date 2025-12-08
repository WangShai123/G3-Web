<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components;
use JEALER\G3\Utilities\Frontend;
class Jui extends Components {
    private array $config = [];

    #[\Override]
    protected function init(): void
    {
    }

    #[\Override]
    public function system(): void
    {
        add_filter('g3_filter_html_class', [$this, 'initHtmlClass']);
        add_action('body_class', [$this, 'initBodyClass']);
    }

    protected function initStyle()
    {
        Frontend::loadStyle('jui');
    }
    protected function initScript(): void
    {
        Frontend::loadScript('jui');
    }
    protected function initModule(): void
    {
        Frontend::loadModule('jui');
    }

    /**
     * Initialize body class
     * 
     * 初始化 JUI body class
     * 
     * @param array $classes body class
     * @return array $classes body class
     * @since 1.0.0
     */
    public function initBodyClass($classes)
    {
        $classes[] = 'jui bg-background text-foreground';
        return $classes;
    }
    public function initHtmlClass($classes)
    {
        if (is_admin()) {
            return;
        }
        $classes[] = 'jui';
        $classes[] = self::getConfigString();
        return $classes;
    }
    public static function getJsonConfig()
    {
        $cookie = $_COOKIE['jui-theme'] ?? '{}';
        $config = json_decode(stripslashes($cookie), true);
        return $config;
    }
    public static function getMode(): string
    {
        $config = self::getJsonConfig();
        return $config['mode'] ?? 'auto';
    }
    public static function getTheme(): string
    {
        $config = self::getJsonConfig();
        return $config['theme'] ?? 'indigo';
    }
    public static function getChartTheme(): string
    {
        $config = self::getJsonConfig();
        return $config['chartTheme'] ?? 'default';
    }

    /**
     * Get JUI config string
     * 
     * 获取 JUI 配置字符串
     * 
     * @return string key1-value1 key2-value2
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function getConfigString(): string
    {
        $config = self::getJsonConfig();
        $config = array_map(function (string $key, string $value) {
            if ($key !== 'mode') {
                if ($key === 'render') {
                    return $value;
                }
                return 'j-' . $key . '-' . $value;
            }
        }, array_keys($config), array_values($config));
        return implode(' ', $config);
    }

}
