<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components;
use JEALER\G3\Themes as ThemesClass;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Utilities\Container;
use WP_Error;
class Themes extends Components {
    public string $optionKey = 'g3_option_themes';
    public array $option = [];
    private array $projects = [];
    protected string $version = '1.0.0';

    #[\Override]
    protected function init(): void
    {
        $this->projects = $this->getProjects();

        if (class_exists(ThemesClass::class)) {
            ThemesClass::run();
        } else {
            new WP_Error('G3_Themes_Error', 'JEALER\G3\Themes class not found');
        }

        add_action('user_register', [$this, 'setDefaultAdminColorScheme']);
    }

    #[\Override]
    protected function admin(): void
    {
        $this->settings();
        $this->themeHandle();
    }
    #[\Override]
    protected function adminMenu(): void
    {
        $this->submenu();
    }
    #[\Override]
    protected function options(): void
    {
        $option       = Option::get($this->optionKey, [
            'default' => 'G3-Web',
            'low'     => '',
            'mobile'  => 'G3-Mobile',
            'tablet'  => '',
        ]);
        $this->option = Option::cache($this->optionKey, $option);
    }

    private function submenu(): void
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

    private function settings(): void
    {
        add_settings_section(
            'section_themes',
            null,
            '__return_false',
            'multiple-themes'
        );
        register_setting(
            'section_themes',
            $this->optionKey
        );
        add_settings_field(
            'low',
            __('Theme for low-end browsers', 'G3'),
            [$this, 'renderLow'],
            'multiple-themes',
            'section_themes',
            ['label_for' => 'low']
        );
        add_settings_field(
            'mobile',
            __('Theme for mobile', 'G3'),
            [$this, 'renderMobile'],
            'multiple-themes',
            'section_themes',
            ['label_for' => 'mobile']
        );
    }

    public function renderLow(): void
    {
        echo Container::select(
            $this->optionKey,
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
        echo Container::select(
            $this->optionKey,
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
     * @since 1.0.0
     * @author Wang Shai
     */
    public function themeHandle()
    {
        /**
         * remove default admin color scheme picker
         */
        remove_action('admin_color_scheme_picker', 'admin_color_scheme_picker');

        /**
         * set G3 admin color scheme
         */
        $option   = get_option('g3_option_dev_setting');
        $fileName = isset($option['environment']) && ($option['environment'] === 'local' || $option['environment'] === 'development') ? '/dist/css/admin.css' : '/public/css/admin.min.css';
        wp_admin_css_color(
            'G3',
            'G3',
            WP_PLUGIN_URL . '/g3' . $fileName,
            ['#2d2f39', '#434656', '#0f49bd', '#135bec'],
            ['base' => '#e5f8ff', 'focus' => '#fff', 'current' => '#fff']
        );
        /**
         * set default admin color scheme for all users
         */
        global $_wp_admin_css_colors;
        $_wp_admin_css_colors = ['G3' => $_wp_admin_css_colors['G3']];
        update_user_meta(get_current_user_id(), 'admin_color', 'G3');
    }

    /**
     * Set default admin color scheme for new registered users
     * 
     * 设置新注册用户默认颜色方案
     * 
     * @param int $user_id the user ID
     * 
     * @since 1.0.0
     * @author Wang Shai
     */
    function setDefaultAdminColorScheme(int $user_id)
    {
        update_user_meta($user_id, 'admin_color', 'G3');
    }
}
