<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use JEALER\G3\Core\Admin\Panel;
use JEALER\G3\Components\Themes\Includes\Themes as ThemesClass;
use JEALER\G3\Services\SystemService;
use Override;
use WP_Error;

class Themes extends Components {
    public array   $option   = [];
    private array  $projects = [];
    private array  $config   = [];
    private string $script;
    #[Override]
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
    private function optionDefaults(): array
    {
        return [
            'low'     => '',
            'mobile'  => 'MobileTheme',
            'tablet'  => '',
            'theme'   => '1',
            'default' => '1',
        ];
    }
    #[Override]
    protected function init()
    {
        $this->projects = $this->getProjects();

        if (class_exists(ThemesClass::class)) {
            $this->container->get(ThemesClass::class);
        } else {
            new WP_Error('G3_Themes_Error', 'JEALER\G3\Components\Themes\Includes\Themes class not found');
        }

        add_action('user_register', [$this, 'setDefaultAdminColorScheme']);
    }
    #[Override]
    protected function adminMenu()
    {
        add_submenu_page(
            'themes.php',
            __('Theme Mode', 'G3'),
            __('Theme Mode', 'G3'),
            'manage_options',
            'theme-mode',
            [$this, 'render'],
            5
        );
    }
    #[Override]
    protected function adminPanelPage(): string
    {
        return 'theme-mode';
    }
    #[Override]
    protected function adminPanels(): array
    {
        return [
            $this->panel('theme-mode', __('Theme Mode', 'G3'))
                ->page('general', __('Theme Mode', 'G3'))
                ->option(SystemService::THEME_OPTION_KEY, $this->optionDefaults())
                ->select('low', __('Theme for low-end browsers', 'G3'), $this->projects, __('You can choose a theme for low-end browsers that do not support ES2015 standard.', 'G3'))
                ->select('mobile', __('Theme for mobile', 'G3'), $this->projects, __('You can Select a theme for mobile devices.', 'G3'))
                ->switch('theme', __('Theme Assembly', 'G3'), __('The theme mode is automatically determined based on the cookie <code>jui-theme</code>. The frontend template HTML element will have classes such as <code>dark</code>, <code>j-theme-indigo</code>, etc.', 'G3'))
                ->switch('default', __('Dark Scheme', 'G3'), __('Use the dark color scheme as the default theme color scheme.', 'G3'))
                ->callback('script', 'Head Script', fn() => $this->renderHeadScript())
        ];
    }
    public function render(): void
    {
        $this->createPanel();
    }
    private function renderHeadScript()
    {
        $script = "<script>(function(d,k){var m=d.cookie.match(new RegExp('(?:^|; )'+k+'=([^;]*)'));if(!m)return;try{var o=JSON.parse(m[1]),r=o.mode==='auto'?(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):o.mode,h=d.documentElement;h.classList.add(r||'dark','j-theme-'+(o.theme||'indigo'),'j-radius-'+(o.radius||'sm'),'j-shadow-'+(o.shadow||'sm'),'j-font-'+(o.font||'sm'));}catch(e){}})(document,'jui-theme');</script>";
        $des    = __('You can manually add the above script to the head tag of the theme template to replace the automatic theme assembly function set above.', 'G3');
        return '<pre style="text-wrap:auto;padding:8px;margin-bottom:8px;background:#e5e4e3" id="headScript">' . esc_html($script) . '</pre><p>' . $des . '</p><p><button class="j-button is-sm is-outline" type="button" id="copyScript">' . __('Copy') . '</button></p>';
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
            WP_PLUGIN_URL . '/g3-web/assets/css/admin.min.css',
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
     * @param int $user_id the user ID
     * @return void
     */
    public function setDefaultAdminColorScheme(int $user_id)
    {
        update_user_meta($user_id, 'admin_color', 'G3');
    }

    public function initHtmlClass(array $classes): array
    {
        $option = get_option(SystemService::THEME_OPTION_KEY, []);
        if (!is_array($option) || ($option['theme'] ?? '1') !== '1') {
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
            if ($key !== 'mode') {
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
        $option  = get_option(SystemService::THEME_OPTION_KEY, []);
        $scheme  = is_array($option) && ($option['default'] ?? '1') !== '1' ? 'light' : 'dark';
        $default = '{"mode":"' . $scheme . '","theme":"indigo","radius":"sm","shadow":"sm","font":"sm","render":"' . $scheme . '"}';
        $cookie  = $_COOKIE['jui-theme'] ?? $default;
        return json_decode(stripslashes($cookie), true);
    }
}
