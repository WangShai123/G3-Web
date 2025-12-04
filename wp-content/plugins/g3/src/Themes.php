<?php
namespace JEALER\G3;
use JEALER\G3\Utilities\Common;
use JEALER\G3\Utilities\Frontend;
use WP_THEME;
class Themes {
    public array $option = [];
    private WP_THEME $lowTheme;
    private WP_THEME $mobileTheme;
    public function __construct()
    {
        $this->option      = get_option('g3_option_themes', [
            'default' => 'G3-Web',
            'low'     => '',
            'mobile'  => 'G3-Mobile',
            'tablet'  => '',
        ]);
        $this->lowTheme    = wp_get_theme($this->option['low'] ?? '');
        $this->mobileTheme = wp_get_theme($this->option['mobile'] ?? '');

        if (isset($this->option['low']) && $this->lowTheme->exists() && Frontend::isLowEndBrowser()) {
            add_filter('template', [$this, 'lowTemplate']);
            add_filter('stylesheet', [$this, 'lowStylesheet']);
        }

        if (isset($this->option['mobile']) && $this->mobileTheme->exists() && wp_is_mobile()) {
            add_filter('template', [$this, 'mobileTemplate']);
            add_filter('stylesheet', [$this, 'mobileStylesheet']);
        }
    }

    public static function run(): Themes
    {
        return Common::singleton(__CLASS__);
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