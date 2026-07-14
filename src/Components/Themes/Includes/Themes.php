<?php
namespace JEALER\G3\Components\Themes\Includes;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Utilities\Frontend;
use Detection\MobileDetect;
use WP_THEME;

class Themes {
    public array         $option       = [];
    private WP_THEME     $lowTheme;
    private WP_THEME     $mobileTheme;
    private Container    $container;
    private MobileDetect $mobileDetect;
    public function __construct()
    {
        $this->option      = get_option('g3_option_themes', [
            'low'     => '',
            'mobile'  => 'G3-Mobile',
            'tablet'  => '',
            'theme'   => '1',
            'default' => '1',
        ]);
        $this->lowTheme    = wp_get_theme($this->option['low'] ?? '');
        $this->mobileTheme = wp_get_theme($this->option['mobile'] ?? '');

        $this->container    = Container::run();
        $this->mobileDetect = $this->container->get(MobileDetect::class);

        if (isset($this->option['low']) && $this->lowTheme->exists() && Frontend::isLowEndBrowser()) {
            add_filter('template', [$this, 'lowTemplate']);
            add_filter('stylesheet', [$this, 'lowStylesheet']);
        }

        if (isset($this->option['mobile']) && $this->mobileTheme->exists() && $this->mobileDetect->isMobile()) {
            add_filter('template', [$this, 'mobileTemplate']);
            add_filter('stylesheet', [$this, 'mobileStylesheet']);
        }
    }

    public function lowTemplate(): string
    {
        return $this->lowTheme->get_template();
    }

    public function lowStylesheet(): string
    {
        return $this->lowTheme->get_stylesheet();
    }

    public function mobileTemplate(): string
    {
        return $this->mobileTheme->get_template();
    }

    public function mobileStylesheet(): string
    {
        return $this->mobileTheme->get_stylesheet();
    }
}
