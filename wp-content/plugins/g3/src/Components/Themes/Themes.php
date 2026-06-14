<?php

namespace JEALER\G3\Components;

use JEALER\G3\Core\Container\Container;
use JEALER\G3\Components\Components;
use JEALER\G3\Components\Themes\Includes\Themes as ThemesClass;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Services\SystemService;
use Override;
use WP_Error;

class Themes extends Components {

    public array $option = [];

    private array $projects = [];

    #[Override]
    protected function options(): void
    {
        $this->option = Option::get(SystemService::THEME_OPTION_KEY, [
            'default' => 'WebTheme',
            'low'     => '',
            'mobile'  => 'MobileTheme',
            'tablet'  => '',
        ]);
    }

    #[Override]
    protected function form(): void
    {
        if (!isset($_REQUEST['page']) || $_REQUEST['page'] !== 'multiple-themes') return;
        $this->option = Option::cache(SystemService::THEME_OPTION_KEY, $this->option);
    }

    #[Override]
    protected function init(): void
    {
        $this->projects = $this->getProjects();

        if (class_exists(ThemesClass::class)) {
            Container::use(ThemesClass::class);
        } else {
            new WP_Error('G3_Themes_Error', 'JEALER\G3\Components\Themes\Includes\Themes class not found');
        }

        add_action('user_register', [$this, 'setDefaultAdminColorScheme']);
    }

    #[Override]
    protected function adminMenu(): void
    {
        add_submenu_page(
            'themes.php',
            __('Multi-theme Mode', 'G3'),
            __('Multi-theme Mode', 'G3'),
            'manage_options',
            'multiple-themes',
            [$this, 'render'],
            5
        );
    }

    public function render()
    {
        return require_once __DIR__ . "/views/page-multiple-themes.php";
    }

    #[Override]
    protected function settings(): void
    {
        $this->themeHandle();
        add_settings_section(
            'themesSection',
            null,
            '__return_false',
            'multiple-themes'
        );
        register_setting(
            'themesSection',
            SystemService::THEME_OPTION_KEY
        );
        add_settings_field(
            'low',
            __('Theme for low-end browsers', 'G3'),
            [$this, 'renderLow'],
            'multiple-themes',
            'themesSection',
            ['label_for' => 'low']
        );
        add_settings_field(
            'mobile',
            __('Theme for mobile', 'G3'),
            [$this, 'renderMobile'],
            'multiple-themes',
            'themesSection',
            ['label_for' => 'mobile']
        );
    }

    public function renderLow(): void
    {
        echo Element::select(
            SystemService::THEME_OPTION_KEY,
            $this->option,
            'low',
            __('Theme for low-end browsers', 'G3'),
            __('You can choose a theme for low-end browsers that do not support ES2015 standard.', 'G3'),
            'low-browsers',
            $this->projects
        );
    }

    public function renderMobile(): void
    {
        echo Element::select(
            SystemService::THEME_OPTION_KEY,
            $this->option,
            'mobile',
            __('Theme for mobile', 'G3'),
            __('You can Select a theme for mobile devices.', 'G3'),
            'mobile-themes',
            $this->projects
        );
    }

    private function getProjects(): array
    {
        $projects = wp_get_themes();
        // add an empty option to the beginning of the array
        $projects = ['' => __('None', 'G3')] + $projects;
        return $projects;
    }

    /**
     * Custom Admin Theme
     * 
     * 自定义后台颜色方案
     *
     * @return void
     */
    public function themeHandle(): void
    {
        /**
         * remove default admin color scheme picker
         */
        remove_action('admin_color_scheme_picker', 'admin_color_scheme_picker');

        /**
         * set G3 admin color scheme
         */
        wp_admin_css_color(
            'G3',
            'G3',
            WP_PLUGIN_URL . '/g3/assets/css/admin.min.css',
            ['#2d2f39', '#434656', '#0f49bd', '#135bec'],
            ['base' => '#e5f8ff', 'focus' => '#fff', 'current' => '#fff']
        );

        /**
         * set default admin color scheme for all users
         */
        $userId = get_current_user_id();

        if (get_user_meta($userId, 'admin_color', true) === 'G3') {
            return;

        }

        update_user_meta($userId, 'admin_color', 'G3');

        // global $_wp_admin_css_colors;
        // $_wp_admin_css_colors = ['G3' => $_wp_admin_css_colors['G3']];

    }

    /**
     * Set default admin color scheme for new registered users
     * 
     * 设置新注册用户默认颜色方案
     * 
     * @param int $user_id the user ID
     * @return void
     */
    function setDefaultAdminColorScheme(int $user_id)
    {
        update_user_meta($user_id, 'admin_color', 'G3');
    }
}
