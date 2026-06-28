<?php
namespace JEALER\G3\Components;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Components\Components;
use JEALER\G3\Core\Admin\Panel;
use JEALER\G3\Components\Themes\Includes\Themes as ThemesClass;
use JEALER\G3\Services\SystemService;
use JEALER\G3\Utilities\Element;
use Override;
use WP_Error;

class Themes extends Components {
    public array  $option   = [];
    private array $projects = [];
    private array $config   = [];
    protected function hooks(): void
    {
        $this->filter([
            'g3_filter_html_class' => [[$this, 'initHtmlClass'], 10, 1],
        ]);
        $this->action([
            'body_class' => [[$this, 'initBodyClass'], 10, 1],
            'admin_init' => [[$this, 'themeHandle']],
        ]);
    }
    protected function state(): array
    {
        return [
            'option' => $this->optionState(SystemService::THEME_OPTION_KEY, [
                'default' => 'WebTheme',
                'low'     => '',
                'mobile'  => 'MobileTheme',
                'tablet'  => '',
                'theme'   => '1'
            ])
        ];
    }
    protected function init()
    {
        $this->projects = $this->getProjects();

        if (class_exists(ThemesClass::class)) {
            Container::use(ThemesClass::class);
        } else {
            new WP_Error('G3_Themes_Error', 'JEALER\G3\Components\Themes\Includes\Themes class not found');
        }

        add_action('user_register', [$this, 'setDefaultAdminColorScheme']);
    }
    protected function adminMenu()
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
    protected function adminPanelPage(): string
    {
        return 'multiple-themes';
    }
    public function render(): void
    {
        $firstPanel = $this->firstPanel();
        if (!$firstPanel instanceof Panel) {
            return;
        }

        $this->createPanel()->render($this, $firstPanel, 'general');
    }
    protected function adminPanels(): array
    {
        return [
            $this->panel('multiple-themes', __('Multi-theme Mode', 'G3'))
                ->page('general', __('Multi-theme Mode', 'G3'), 'option')
                ->select('low', __('Theme for low-end browsers', 'G3'), $this->projects, __('You can choose a theme for low-end browsers that do not support ES2015 standard.', 'G3'))
                ->select('mobile', __('Theme for mobile', 'G3'), $this->projects, __('You can Select a theme for mobile devices.', 'G3'))
                ->switch('theme', __('Theme Mode', 'G3'), __('The theme mode is automatically determined based on the cookie <code>jui-theme</code>. The frontend template HTML element will have classes such as <code>dark</code>, <code>j-theme-indigo</code>, etc.', 'G3'))
        ];
    }

    private function createTip()
    {
        $p1  = __('You can configure different theme templates for different types of devices to improve user experience.', 'G3');
        $p2  = __('The theme you configure on the Appearance-Theme page is the main theme of the system. When the option corresponding to the multi-theme mode is empty, the main theme will be used first by default.', 'G3');
        $msg = "<div>{$p1}</div><div>{$p2}</div>";
        return Element::tip(
            $msg,
            '',
            'default',
            'mt-4'
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
        // remove default admin color scheme picker
        remove_action('admin_color_scheme_picker', 'admin_color_scheme_picker');

        // set G3 admin color scheme
        wp_admin_css_color(
            'G3',
            'G3',
            WP_PLUGIN_URL . '/g3/assets/css/admin.min.css',
            ['#2d2f39', '#434656', '#0f49bd', '#135bec'],
            ['base' => '#e5f8ff', 'focus' => '#fff', 'current' => '#fff']
        );

        // set default admin color scheme for all users
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
    public function setDefaultAdminColorScheme(int $user_id)
    {
        update_user_meta($user_id, 'admin_color', 'G3');
    }

    public function initHtmlClass(array $classes): array
    {
        if (!isset($this->option['theme']) || $this->option['theme'] !== '1') {
            return $classes;
        }
        if (!is_admin()) {
            $classes[] = $this->getConfigString();
        }
        return $classes;
    }
    public function initBodyClass(array $classes): array
    {
        $classes[] = 'jui';
        return $classes;
    }
    private function getConfigString(): string
    {
        $this->config = $this->getConfigJson();
        $config       = array_map(function (string $key, string $value) {
            if ($key !== 'mode' && $key !== 'key') {
                if ($key === 'render') {
                    return $value;
                }
                return 'j-' . $key . '-' . $value;
            }
        }, array_keys($this->config), array_values($this->config));
        return implode(' ', $config);
    }
    private function getConfigJson(): array
    {
        $cookie = $_COOKIE['jui-theme'] ?? '{}';
        return json_decode(stripslashes($cookie), true);
    }
}