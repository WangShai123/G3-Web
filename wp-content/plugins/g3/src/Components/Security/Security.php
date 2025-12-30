<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components;
use JEALER\G3\Utilities\Common;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Utilities\Container;
use JEALER\G3\Utilities\Session;
use JEALER\G3\Services\SystemService;

class Security extends Components {
    public array $option = [];
    private string $section = 'securitySection';

    #[\Override]
    protected function options(): void
    {
        $this->option = Option::get(SystemService::SECURITY_OPTION_KEY, [
            'login'       => '0',
            'url'         => Common::hash(8),
            'upload'      => '1',
            'userSiteMap' => '1',
            'session'     => '0',
            'xmlrpc'      => '1',
            'xss'         => '0',
            'csp'         => '0',
            'xPoweredBy'  => '1'
        ]);
    }
    #[\Override]
    protected function adminOptions(): void
    {
        $this->option = Option::cache(SystemService::SECURITY_OPTION_KEY, $this->option);
    }
    #[\Override]
    protected function front(): void
    {
        $this->userSiteMapHandle();
    }
    #[\Override]
    protected function system(): void
    {
        add_filter('wp_handle_upload_prefilter', [$this, 'uploadFilenameHandle']);
        // $this->uaHandle();
    }
    #[\Override]
    protected function init(): void
    {
        $this->securityLoginHandle();
        $this->destroyExtraSessions();
        $this->xmlRpcHandle();
        // $this->xssHandle();
        $this->cspHandle();
    }
    #[\Override]
    protected function admin(): void
    {
        $this->settings();
    }
    #[\Override]
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
    public function render(): void
    {
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . __('Security', 'G3') . '</h1>';
        $args = [
            'general' => __('General', 'G3'),
            'nginx'   => 'Nginx',
            'caddy'   => 'Caddy',
        ];
        Container::tab('Security', 'general', $args);
        echo '</div>';
    }
    #[\Override]
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
        Container::settingFields(
            'security',
            $this->section,
            [
                [
                    'id'       => 'login',
                    'title'    => __('Custom Admin Login', 'G3'),
                    'callback' => function () {
                        echo Container::enable(
                            SystemService::SECURITY_OPTION_KEY,
                            $this->option,
                            'login',
                            __('Custom Admin Login', 'G3'),
                            __('The system will replace <code>"wp-login.php"</code> with the new address <code>"/oa/$url"</code> below.', 'G3')
                        );
                    },
                    'args'     => [
                        'label_for' => 'login',
                        'class'     => 'security-field__login',
                    ]
                ],
                [
                    'id'       => 'url',
                    'title'    => __('Admin Login URL', 'G3'),
                    'callback' => function () {
                        echo Container::input(
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
                        echo Container::enable(
                            SystemService::SECURITY_OPTION_KEY,
                            $this->option,
                            'upload',
                            __('Reset Upload File Names', 'G3'),
                            __('Rename the currently uploaded file using a timestamp.', 'G3')
                        );
                    },
                    'args'     => [
                        'label_for' => 'upload',
                        'class'     => 'security-field__upload',
                    ]
                ],
                [
                    'id'       => 'userSiteMap',
                    'title'    => __('Users SiteMap', 'G3'),
                    'callback' => function () {
                        echo Container::enable(
                            SystemService::SECURITY_OPTION_KEY,
                            $this->option,
                            'userSiteMap',
                            __('Users SiteMap', 'G3'),
                            __('Remove the default users site map to avoid exposing user ID and login name security risks.', 'G3'),
                        );
                    },
                    'args'     => [
                        'label_for' => 'userSiteMap',
                        'class'     => 'security-field__userSiteMap',
                    ]
                ],
                [
                    'id'       => 'session',
                    'title'    => __('Safe Session', 'G3'),
                    'callback' => function () {
                        echo Container::enable(
                            SystemService::SECURITY_OPTION_KEY,
                            $this->option,
                            'session',
                            __('Safe Session', 'G3'),
                            __('When the same user logs in from multiple locations, only the most recent login session remains valid.', 'G3')
                        );
                    },
                    'args'     => [
                        'label_for' => 'session',
                        'class'     => 'security-field__session',
                    ]
                ],
                [
                    'id'       => 'xmlrpc',
                    'title'    => __('Prevent XMLRPC Attacks', 'G3'),
                    'callback' => function () {
                        echo Container::enable(
                            SystemService::SECURITY_OPTION_KEY,
                            $this->option,
                            'xmlrpc',
                            __('Prevent XMLRPC Attacks', 'G3'),
                            __('Remove the XML-RPC API to block access to xmlrpc.php.', 'G3')
                        );
                    },
                    'args'     => [
                        'label_for' => 'xmlrpc',
                        'class'     => 'security-field__xmlrpc',
                    ]
                ],
                // [
                //     'id'       => 'xss',
                //     'title'    => __('Prevent XSS Attacks', 'G3'),
                //     'callback' => function () {
                //         echo Container::enable(
                //             SystemService::SECURITY_OPTION_KEY,
                //             $this->option,
                //             'xss',
                //             __('Prevent XSS Attacks', 'G3'),
                //             __('Filter requests and prevent XSS attacks.', 'G3')
                //         );
                //     },
                //     'args'     => [
                //         'label_for' => 'xss',
                //         'class'     => 'security-field__xss',
                //     ]
                // ],
                [
                    'id'       => 'csp',
                    'title'    => __('Content Security Policy', 'G3'),
                    'callback' => function () {
                        echo Container::enable(
                            SystemService::SECURITY_OPTION_KEY,
                            $this->option,
                            'csp',
                            __('Content Security Policy', 'G3'),
                            __('Use Content Security Policy (CSP) to prevent cross-site scripting attacks. Before enabling, please ensure that there are no external resource references.', 'G3')
                        );
                    },
                    'args'     => [
                        'label_for' => 'csp',
                        'class'     => 'security-field__csp',
                    ]
                ],
                // [
                //     'id'       => 'xPoweredBy',
                //     'title'    => 'X-Powered-By',
                //     'callback' => function () {
                //         echo Container::enable(
                //             SystemService::SECURITY_OPTION_KEY,
                //             $this->option,
                //             'xPoweredBy',
                //             'X-Powered-By',
                //             __('Remove X-Powered-By header.', 'G3')
                //         );
                //     },
                //     'args'     => [
                //         'label_for' => 'xPoweredBy',
                //         'class'     => 'security-field__xPoweredBy',
                //     ]
                // ],
                // [
                //     'id'       => 'libredtail',
                //     'title'    => 'Libredtail-HTTP',
                //     'callback' => function () {
                //         echo Container::enable(
                //             SystemService::SECURITY_OPTION_KEY,
                //             $this->option,
                //             'libredtail',
                //             'Libredtail-HTTP',
                //             __('Prevent requests from Libredtail-HTTP UA.', 'G3')
                //         );
                //     },
                //     'args'     => [
                //         'label_for' => 'libredtail',
                //         'class'     => 'security-field__libredtail',
                //     ]
                // ]
            ]
        );
    }

    public function securityLoginHandle(): void
    {
        if (!$this->customAdminLogin()) {
            return;
        }
        $this->disableWPLogin();
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
    private function xssHandle(): void
    {
        if (is_admin() || !isset($this->option['xss']) || $this->option['xss'] !== '1') {
            return;
        }
        if (
            strpos($_SERVER["REQUEST_URI"], "eval(") !== false ||
            strpos($_SERVER["REQUEST_URI"], "base64") !== false ||
            preg_match('/\\b(\\w*\\/\\*\\w*)\\b/', $_SERVER["REQUEST_URI"]) === 1
        ) {
            @header("HTTP/1.1 414 Request-URI Too Long");
            @header("Status: 414 Request-URI Too Long");
            @header("Connection: Close");
            @exit;
        }
    }
    private function cspHandle(): void
    {
        if (is_admin() || !isset($this->option['csp']) || $this->option['csp'] !== '1') {
            return;
        }
        header('Content-Security-Policy: default-src \'self\'; script-src \'self\'; style-src \'self\'; img-src \'self\'; font-src \'self\'; object-src \'none\';');
    }
    private function uaHandle(): void
    {
        if (is_admin() || !isset($this->option['libredtail']) || $this->option['libredtail'] !== '1') {
            return;
        }
        add_action('init', function () {
            if (isset($_SERVER['HTTP_USER_AGENT']) && str_contains($_SERVER['HTTP_USER_AGENT'], 'libredtail-http')) {
                status_header(403);
                exit;
            }
        });
    }
}