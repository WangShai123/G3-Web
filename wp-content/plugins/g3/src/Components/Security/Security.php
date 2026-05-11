<?php

namespace JEALER\G3\Components;

use JEALER\G3\Components\Components;
use JEALER\G3\Utilities\Common;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Session;
use JEALER\G3\Services\SystemService;
use Override;

class Security extends Components {

    public array $option = [];

    public array $queue = [];

    private string $section = 'securitySection';

    #[Override]
    protected function options(): void
    {
        $this->option = Option::get(SystemService::SECURITY_OPTION_KEY, [
            'login'       => '0',
            'url'         => Common::hash(8),
            'upload'      => '1',
            'userSiteMap' => '1',
            'session'     => '0',
            'xmlrpc'      => '1',
            'csp'         => '0',
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
    protected function front(): void
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
    protected function admin(): void
    {
        $this->settings();
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
                    },
                    'args'     => [
                        'label_for' => 'url',
                        'class'     => 'security-field__url',
                    ]
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
                    'id'       => 'userSiteMap',
                    'title'    => __('Users SiteMap', 'G3'),
                    'callback' => function () {
                        echo Element::switch(
                            SystemService::SECURITY_OPTION_KEY,
                            $this->option,
                            'userSiteMap',
                            __('Users SiteMap', 'G3'),
                            __('Remove the default users site map to avoid exposing user ID and login name security risks.', 'G3'),
                        );
                    }
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
                    'class' => 'advanced'
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
        if (!$this->customAdminLogin()) {
            return;
        }
        $this->disableWPLogin();
        $this->disableAdminVisit();
    }
    public function customAdminLogin(): bool
    {
        if (!isset($this->option['login']) || $this->option['login'] !== '1') {
            return false;
        }
        return true;
    }
    public function customAdminParam(): string
    {
        return isset($this->option['url']) && $this->option['url'] ? $this->option['url'] : Common::hash();
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
    public function userSiteMapHandle(): void
    {
        add_filter('wp_sitemaps_add_provider', function ($provider, $name) {
            $v = get_option(SystemService::SECURITY_OPTION_KEY);
            if (!isset($v['userSiteMap']) || $v['userSiteMap'] !== '1') {
                return $provider;
            }
            if ('users' === $name) {
                return false;
            }
            return $provider;
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
}