<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use JEALER\G3\Utilities\Common;
use JEALER\G3\Utilities\Context;
use JEALER\G3\Utilities\Message;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Session;
use JEALER\G3\Services\SystemService;
use Override;
use WP_Error;

class Security extends Components {
    public array   $option  = [];
    public array   $queue   = [];
    private string $section = 'securitySection';
    #[Override]
    protected function hooks(): void
    {
        $this->filter([
            'rest_authentication_errors' => [[$this, 'restrictNativeRestApi'], 10, 1],
            'wp_sitemaps_enabled'        => [[$this, 'nativeSitemapEnabled'], 10, 1],
        ]);
    }
    #[Override]
    protected function options(): void
    {
        $this->option = Option::get(SystemService::SECURITY_OPTION_KEY, [
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
        ]);
        $this->queue  = Option::get(SystemService::QUEUE_OPTION_KEY, [
            'email' => '1',
        ]);
    }
    #[Override]
    protected function form(): void
    {
        if (isset($_REQUEST['page']) && $_REQUEST['page'] === 'security') {
            $this->option = Option::cache(SystemService::SECURITY_OPTION_KEY, $this->option);
        }
        if (isset($_REQUEST['page']) && $_REQUEST['page'] === 'performance') {
            $this->queue = Option::cache(SystemService::QUEUE_OPTION_KEY, $this->queue);
        }
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
        add_submenu_page(
            'g3-settings',
            __('Performance', 'G3'),
            __('Performance', 'G3'),
            'manage_options',
            'performance',
            [$this, 'renderPerformance'],
            19
        );
    }
    public function render(): void
    {
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . __('Security', 'G3') . '</h1>';
        $args = [
            'general' => __('General'),
            'nginx'   => 'Nginx',
            'caddy'   => 'Caddy',
        ];
        Element::tab('Security', 'general', $args);
        echo '</div>';
    }
    #[Override]
    protected function settings(): void
    {
        add_settings_section(
            $this->section,
            '',
            '__return_false',
            'security'
        );
        register_setting(
            $this->section,
            SystemService::SECURITY_OPTION_KEY
        );
        Element::settingFields(
            'security',
            $this->section,
            [
                [
                    'id'       => 'login',
                    'title'    => __('Custom Admin Login', 'G3'),
                    'callback' => function () {
                        echo Element::switch(
                            SystemService::SECURITY_OPTION_KEY,
                            $this->option,
                            'login',
                            __('Custom Admin Login', 'G3'),
                            __('The system will replace <code>"wp-login.php"</code> with the new address <code>"/oa/$url"</code> below.', 'G3')
                        );
                    }
                ],
                [
                    'id'       => 'url',
                    'title'    => __('Admin Login URL', 'G3'),
                    'callback' => function () {
                        echo Element::input(
                            SystemService::SECURITY_OPTION_KEY,
                            $this->option,
                            'url',
                            __('Admin Login URL', 'G3'),
                            __('Login', 'G3') . __('URL') . ': <code>' . esc_html(home_url('oa/' . (isset($this->option['url']) && $this->option['url'] ? $this->option['url'] : ''))) . '</code> ' . __('Please <a href="?page=developer-mode&tab=flush">flush rewrite rules</a> after setting.', 'G3'),
                        );
                    }
                ],
                [
                    'id'       => 'upload',
                    'title'    => __('Reset Upload File Names', 'G3'),
                    'callback' => function () {
                        echo Element::switch(
                            SystemService::SECURITY_OPTION_KEY,
                            $this->option,
                            'upload',
                            __('Reset Upload File Names', 'G3'),
                            __('Rename the currently uploaded file using a timestamp.', 'G3')
                        );
                    }
                ],
                [
                    'id'       => 'sitemap',
                    'title'    => __('Reset SiteMap', 'G3'),
                    'callback' => function () {
                        echo Element::switch(
                            SystemService::SECURITY_OPTION_KEY,
                            $this->option,
                            'sitemap',
                            __('Reset SiteMap', 'G3'),
                            __('Remove the default sitemap of WordPress and use the G3 sitemap instead.', 'G3'),
                        );
                    }
                ],
                [
                    'id'       => 'userSiteMap',
                    'title'    => __('Users SiteMap', 'G3'),
                    'callback' => function () {
                        echo Element::switch(
                            SystemService::SECURITY_OPTION_KEY,
                            $this->option,
                            'userSiteMap',
                            __('Users SiteMap', 'G3'),
                            __('Remove the default users sitemap module of WordPress to avoid exposing user ID and login name security risks.', 'G3'),
                        );
                    }
                ],
                [
                    'id'       => 'siteMapGenerator',
                    'title'    => __('SiteMap Generator', 'G3'),
                    'callback' => function () {
                        echo Element::switch(
                            SystemService::SECURITY_OPTION_KEY,
                            $this->option,
                            'siteMapGenerator',
                            __('SiteMap Generator', 'G3'),
                            sprintf(
                                __('In the <a href="%s">site map settings page</a>, manually trigger to generate the site map cache file, instead of generating the site map cache file through the site map dynamic link.', 'G3'),
                                admin_url('admin.php?page=g3-settings&tab=sitemap')
                            )
                        );
                    }
                ],
                [
                    'id'       => 'restApi',
                    'title'    => 'REST API',
                    'callback' => function () {
                        echo Element::switch(
                            SystemService::SECURITY_OPTION_KEY,
                            $this->option,
                            'restApi',
                            'REST API',
                            __('Disable the WordPress built-in REST API to avoid exposing sensitive information. Only admin can visit the build-in REST API.', 'G3'),
                        );
                    },
                ],
                [
                    'id'       => 'session',
                    'title'    => __('Safe Session', 'G3'),
                    'callback' => function () {
                        echo Element::switch(
                            SystemService::SECURITY_OPTION_KEY,
                            $this->option,
                            'session',
                            __('Safe Session', 'G3'),
                            __('When the same user logs in from multiple locations, only the most recent login session remains valid.', 'G3')
                        );
                    }
                ],
                [
                    'id'       => 'xmlrpc',
                    'title'    => __('Prevent XMLRPC Attacks', 'G3'),
                    'callback' => function () {
                        echo Element::switch(
                            SystemService::SECURITY_OPTION_KEY,
                            $this->option,
                            'xmlrpc',
                            __('Prevent XMLRPC Attacks', 'G3'),
                            __('Remove the XML-RPC API to block access to xmlrpc.php.', 'G3')
                        );
                    }
                ],
                [
                    'id'       => 'csp',
                    'title'    => __('Content Security Policy', 'G3'),
                    'callback' => function () {
                        echo Element::switch(
                            SystemService::SECURITY_OPTION_KEY,
                            $this->option,
                            'csp',
                            __('Content Security Policy', 'G3'),
                            __('Use Content Security Policy (CSP) to prevent cross-site scripting attacks. Before enabling, please ensure that there are no external resource references.', 'G3')
                        );
                    }
                ]
            ]
        );

        add_settings_section(
            'queue',
            '',
            '__return_false',
            'performance'
        );
        register_setting(
            'queues',
            SystemService::QUEUE_OPTION_KEY
        );
        Element::settingFields('performance', 'queue', [
            [
                'id'       => 'email',
                'title'    => __('Email Queue', 'g3'),
                'callback' => function () {
                    echo Element::switch(
                        SystemService::QUEUE_OPTION_KEY,
                        $this->queue,
                        'email',
                        __('Email Queue', 'G3')
                    );
                },
                'args'     => [
                    'class' => "advanced"
                ]
            ]
        ]);
    }
    public function renderPerformance(): void
    {
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . __('Performance', 'G3') . '</h1>';
        $args = [
            'queue'   => __('Queue', 'G3'),
            'opcache' => 'OPCache',
            'fastcgi' => 'FastCGI',
            'redis'   => 'Redis',
            'theme'   => __('Hybrid Theme', 'G3'),
        ];
        Element::tab('Security', 'queue', $args);
        echo '</div>';
    }

    public function securityLoginHandle(): void
    {
        // if (!$this->customAdminLogin()) {
        //     return;
        // }
        if (!self::customAdminLogin()) {
            return;
        }
        $this->disableWPLogin();
        $this->disableAdminVisit();
    }
    public static function customAdminLogin(): bool
    {
        // if (!isset($this->option['login']) || $this->option['login'] !== '1') {
        //     return false;
        // }
        // return true;
        $v = get_option(SystemService::SECURITY_OPTION_KEY)['login'] ?? '0';
        return $v === '1';
    }
    public static function onSitemap(): bool
    {
        $x = Context::get(SystemService::SECURITY_OPTION_KEY)['sitemap'] ?? '1';
        $y = Context::get(SystemService::SECURITY_OPTION_KEY)['siteMapGenerator'] ?? '1';
        return $x === '1' && $y !== '1';
    }
    public function nativeSitemapEnabled($enabled): bool
    {
        // $x = Context::get(SystemService::SECURITY_OPTION_KEY)['sitemap'] ?? '1';
        $x = get_option(SystemService::SECURITY_OPTION_KEY)['sitemap'] ?? '1';
        return $x === '1' ? false : (bool) $enabled;
    }
    public static function customAdminParam(): string
    {
        // return isset($this->option['url']) && $this->option['url'] ? $this->option['url'] : Common::hash();
        return get_option(SystemService::SECURITY_OPTION_KEY)['url'] ?? Common::hash();
    }
    private function disableWPLogin(): void
    {
        if (str_contains($_SERVER['REQUEST_URI'], 'wp-login.php') && !str_contains($_SERVER['REQUEST_URI'], 'action=logout')) {
            wp_safe_redirect(home_url(), 302, get_bloginfo('name'));
            exit;
        }
    }
    private function disableAdminVisit(): void
    {
        if (
            str_contains($_SERVER['REQUEST_URI'], 'wp-admin')
            && !current_user_can('manage_options')
        ) {
            wp_safe_redirect(home_url(), 302, get_bloginfo('name'));
            exit;
        }
    }
    public function uploadFilenameHandle($file)
    {
        if (!isset($this->option['upload']) || $this->option['upload'] !== '1') {
            return $file;
        }
        $file['name'] = time() . '_' . $file['name'];
        return $file;
    }
    public function userSiteMapHandle()
    {
        add_filter('wp_sitemaps_add_provider', function ($provider, $name) {
            $v = Context::get(SystemService::SECURITY_OPTION_KEY)['userSiteMap'] ?? '1';
            return $v === '1' && 'users' === $name ? false : $provider;
        }, 10, 2);
    }
    public function destroyExtraSessions(): void
    {
        if (!isset($this->option['session']) || $this->option['session'] !== '1') {
            return;
        }
        $this->sessionHandle();
    }
    private function sessionHandle(): void
    {
        if (current_user_can('manage_options')) {
            return;
        }

        if (!(is_user_logged_in() && count(wp_get_all_sessions()) > 1)) {
            return;
        }

        $n       = max(wp_list_pluck(wp_get_all_sessions(), 'login'));
        $session = Session::current();

        $session['login'] === $n ? wp_destroy_other_sessions() : wp_destroy_current_session();
    }
    private function xmlRpcHandle(): void
    {
        if (!isset($this->option['xmlrpc']) || $this->option['xmlrpc'] !== '1') {
            return;
        }
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
        if (is_admin() || !isset($this->option['csp']) || $this->option['csp'] !== '1') {
            return;
        }
        header('Content-Security-Policy: default-src \'self\'; script-src \'self\'; style-src \'self\'; img-src \'self\'; font-src \'self\'; object-src \'none\';');
    }
    /**
     * 间接禁用 WordPress 默认的 REST API。
     * 方式：限制对应 namespace（wp）为管理员访问。
     */
    public function restrictNativeRestApi($result)
    {
        if (!isset($this->option['restApi']) || $this->option['restApi'] !== '1') {
            return $result;
        }

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
                'unauthorized',
                Message::unauthorized(),
                ['status' => 401]
            );
        }
        // denied: not admin
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'forbidden',
                Message::forbidden(),
                ['status' => 403]
            );
        }
        return $result;
    }
}
