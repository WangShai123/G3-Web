<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use JEALER\G3\Core\Admin\Panel;
use JEALER\G3\Services\SidebarService;
use JEALER\G3\Utilities\Common;
use JEALER\G3\Utilities\Message;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Session;
use JEALER\G3\Services\SystemService;
use Override;
use WP_Error;

class Security extends Components {
    public array $queue = [];
    #[Override]
    protected function hooks(): void
    {
        $this->filter([
            'rest_authentication_errors' => [[$this, 'restrictNativeRestApi'], 10, 1],
            'wp_sitemaps_enabled'        => [[$this, 'nativeSitemapEnabled'], 10, 1],
        ]);
    }
    private function optionDefaults(): array
    {
        return [
            'login'            => '0',
            'url'              => Common::hash(8),
            'upload'           => '1',
            'sitemap'          => '1',
            'userSiteMap'      => '1',
            'siteMapGenerator' => '0',
            'restApi'          => '1',
            'session'          => '0',
            'xmlrpc'           => '1',
            'csp'              => '0',
        ];
    }
    private static function optionData(): array
    {
        $option = get_option(SystemService::SECURITY_OPTION_KEY, []);
        return is_array($option) ? $option : [];
    }
    #[Override]
    protected function ready(): void
    {
        $this->userSiteMapHandle();
    }
    #[Override]
    protected function system(): void
    {
        add_filter('wp_handle_upload_prefilter', [$this, 'uploadFilenameHandle']);
    }
    #[Override]
    protected function init(): void
    {
        $this->securityLoginHandle();
        $this->destroyExtraSessions();
        $this->xmlRpcHandle();
        $this->cspHandle();
    }
    #[Override]
    protected function adminMenu(): void
    {
        add_submenu_page(
            'g3-settings',
            __('Security', 'G3'),
            __('Security', 'G3'),
            'manage_options',
            'security',
            [$this, 'render'],
            18
        );
    }
    #[Override]
    protected function adminPanels(): array
    {
        return [
            $this->panel('security', __('Security', 'G3'))
                ->tab('general', __('General'))
                ->option(SystemService::SECURITY_OPTION_KEY, $this->optionDefaults())
                ->switch('login', __('Custom Admin Login', 'G3'), __('The system will replace <code>"wp-login.php"</code> with the new address <code>"/oa/$url"</code> below.', 'G3'))
                ->input('url', __('Admin Login URL', 'G3'), __('Login', 'G3') . __('URL') . ': <code>' . esc_html(home_url('oa/' . (self::optionData()['url'] ?? Common::hash()))) . '</code> ' . __('Please <a href="?page=developer-mode&tab=flush">flush rewrite rules</a> after setting.', 'G3'))
                ->switch('upload', __('Reset Upload File Names', 'G3'), __('Rename the currently uploaded file using a timestamp.', 'G3'))
                ->rowClass('advanced')
                ->switch('sitemap', __('Reset SiteMap', 'G3'), __('Remove the default sitemap of WordPress and use the G3 sitemap instead.', 'G3'))
                ->switch('userSiteMap', __('Users SiteMap', 'G3'), __('Remove the default users sitemap module of WordPress to avoid exposing user ID and login name security risks.', 'G3'))
                ->rowClass('advanced')
                ->switch('siteMapGenerator', __('SiteMap Generator', 'G3'), sprintf(
                    __('In the <a href="%s">site map settings page</a>, manually trigger to generate the site map cache file, instead of generating the site map cache file through the site map dynamic link.', 'G3'),
                    admin_url('admin.php?page=g3-settings&tab=sitemap')
                ))
                ->switch('restApi', 'REST API', __('Disable the WordPress built-in REST API to avoid exposing sensitive information. Only admin can visit the build-in REST API.', 'G3'))
                ->switch('session', __('Safe Session', 'G3'), __('When the same user logs in from multiple locations, only the most recent login session remains valid.', 'G3'))
                ->switch('xmlrpc', __('Prevent XMLRPC Attacks', 'G3'), __('Remove the XML-RPC API to block access to xmlrpc.php.', 'G3'))
                ->switch('csp', __('Content Security Policy', 'G3'), __('Use Content Security Policy (CSP) to prevent cross-site scripting attacks. Before enabling, please ensure that there are no external resource references.', 'G3'))
                ->tab('nginx', 'Nginx')
                ->tab('caddy', 'Caddy')
        ];
    }
    protected function adminPanelPage(): string
    {
        return 'security';
    }
    public function render(): void
    {
        $this->createPanel();
    }

    public function securityLoginHandle(): void
    {
        if (!self::customAdminLogin()) return;
        $this->disableWPLogin();
        $this->disableAdminVisit();
    }
    public static function customAdminLogin(): bool
    {
        $v = self::optionData()['login'] ?? '0';
        return $v === '1';
    }
    public static function onSitemap(): bool
    {
        $option = self::optionData();
        $x      = $option['sitemap'] ?? '1';
        $y      = $option['siteMapGenerator'] ?? '0';
        return $x === '1' && $y !== '1';
    }
    public function nativeSitemapEnabled($enabled): bool
    {
        $x = self::optionData()['sitemap'] ?? '1';
        return $x === '1' ? false : (bool) $enabled;
    }
    public static function customAdminParam(): string
    {
        return self::optionData()['url'] ?? Common::hash();
    }
    private function disableWPLogin(): void
    {
        if (str_contains($_SERVER['REQUEST_URI'], 'wp-login.php') && !str_contains($_SERVER['REQUEST_URI'], 'action=logout')) {
            wp_safe_redirect(home_url(), 302, 'G3');
            exit;
        }
    }
    private function disableAdminVisit(): void
    {
        if (
            str_contains($_SERVER['REQUEST_URI'], 'wp-admin')
            && !current_user_can('manage_options')
        ) {
            wp_safe_redirect(home_url(), 302, 'G3');
            exit;
        }
    }
    public function uploadFilenameHandle($file)
    {
        if ((self::optionData()['upload'] ?? '1') === '1') {
            $file['name'] = time() . Common::hash(4) . '_' . $file['name'];
        }
        return $file;
    }
    public function userSiteMapHandle()
    {
        add_filter('wp_sitemaps_add_provider', function ($provider, $name) {
            $v = self::optionData()['userSiteMap'] ?? '1';
            return $v === '1' && 'users' === $name ? false : $provider;
        }, 10, 2);
    }
    public function destroyExtraSessions(): void
    {
        if ((self::optionData()['session'] ?? '0') !== '1') return;
        $this->sessionHandle();
    }
    private function sessionHandle(): void
    {
        if (!is_user_logged_in()) return;
        wp_destroy_other_sessions();
    }
    private function xmlRpcHandle(): void
    {
        if ((self::optionData()['xmlrpc'] ?? '1') !== '1') return;

        // Disable XML-RPC API
        add_filter('xmlrpc_enabled', '__return_false');
        // Block requests to xmlrpc.php
        if (strpos($_SERVER['REQUEST_URI'], 'xmlrpc.php') !== false) {
            $protocol = $_SERVER['SERVER_PROTOCOL'] ?? '';
            if (!in_array($protocol, array('HTTP/1.1', 'HTTP/2', 'HTTP/2.0', 'HTTP/3'), true)) {
                $protocol = 'HTTP/1.0';
            }
            header("$protocol 403 Forbidden", true, 403);
            die();
        }
    }
    private function cspHandle(): void
    {
        if (is_admin() || (self::optionData()['csp'] ?? '0') === '0') return;
        header('Content-Security-Policy: default-src \'self\'; script-src \'self\'; style-src \'self\'; img-src \'self\'; font-src \'self\'; object-src \'none\';');
    }
    /**
     * 间接禁用 WordPress 默认的 REST API。
     * 方式：限制对应 namespace（wp）为管理员访问。
     */
    public function restrictNativeRestApi($result)
    {
        if ((self::optionData()['restApi'] ?? '1') !== '1') return $result;

        // return error immidiately
        if (!empty($result)) {
            return $result;
        }
        // get route from $wp object
        global $wp;
        $route = isset($wp->query_vars['rest_route']) ? $wp->query_vars['rest_route'] : '';
        // if route is empty
        if (empty($route)) {
            return $result;
        }
        // if route is not WordPress native API
        if (strpos($route, '/wp/') !== 0) {
            return $result;
        }
        $currentUser = wp_get_current_user();
        // denied: unauthorized
        if (!$currentUser || $currentUser->ID === 0) {
            return new WP_Error(
                // 'unauthorized',
                // Message::unauthorized(),
                // ['status' => 401]
                'forbidden',
                Message::forbidden(),
                ['status' => 403]
            );
        }
        // denied: not admin
        // if (!current_user_can('manage_options')) {
        //     return new WP_Error(
        //         'forbidden',
        //         Message::forbidden(),
        //         ['status' => 403]
        //     );
        // }
        return $result;
    }
}
