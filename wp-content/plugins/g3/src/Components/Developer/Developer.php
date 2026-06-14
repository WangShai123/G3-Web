<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use JEALER\G3\Core\Rewrite\RewriteRouter;
use JEALER\G3\Services\TemplateService;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Context;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Utilities\Response;
use JEALER\G3\Utilities\System;
use JEALER\G3\Utilities\Validator;
use JEALER\G3\Services\SystemService;
use Override;
use WP_Error;
use WP_Query;

class Developer extends Components {
    public array            $formOption;
    public array            $settingOption;
    public array            $opMPOption;
    public                  $v;
    public static           $z;
    private TemplateService $template;

    #[Override]
    protected function start(): void
    {
        /** @var TemplateService */
        $this->template = $this->container->get(TemplateService::class);
    }

    protected function hooks(): void
    {
        $this->filter([
            'single_template'  => [$this->template, 'singleTemplate'],
            'map_meta_cap'     => [[$this, 'themeCustomizeHandle'], 20, 4],
            'translations_api' => [[$this, 'translationsApiHandle'], 10, 3],
            'show_admin_bar'   => [$this, 'adminBarHandle'],
            'login_headerurl'  => [$this, 'handleLoginHeaderUrl'],
            'login_headertext' => [$this, 'handleLoginHeaderText'],
        ]);

        $this->action([
            'login_head'     => [$this, 'handleLoginHead'],
            'admin_head'     => [$this, 'helpLinkHandle'],
            'admin_bar_menu' => [[$this, 'adminBarMenuHandle'], 999, 1],
        ]);
    }
    #[Override]
    protected function options(): void
    {
        $this->settingOption = Option::get(SystemService::SETTING_OPTION_KEY, [
            'environment'     => 'production',
            'wpAutoUpdate'    => '0',
            'translationsApi' => '0',
            'themeEditor'     => '0',
            'pluginEditor'    => '0',
            'siteHealth'      => '0',
            'themeCustomize'  => '0',
            'blockPatterns'   => '0',
            'permalink'       => '1',
            'helpLink'        => '0',
            'adminBar'        => '0',
            'emoji'           => '0',
            'wpHead'          => '1',
            'gutenberg'       => '0',
            'adminTitle'      => '1',
            'adminLogo'       => '1',
            'toolsPage'       => '1',
            'pluginsPage'     => '1',
            'themeInstall'    => '1',
            'dashboard'       => ['0', '1', '2', '3', '4', '5'],
            'footerThanks'    => G3_NAME,
            'footerUpgrade'   => G3_VERSION,
        ]);
        $this->opMPOption    = Option::get(SystemService::OPEN_WECHAT_OA_KEY, [
            'type'           => '0',
            'slug'           => '',
            'appId'          => '',
            'appSecret'      => '',
            'token'          => '',
            'encodingAESKey' => '',
        ]);
    }

    #[Override]
    protected function prepareInAdmin(): void
    {
        $this->formOption = Option::get(SystemService::FORM_OPTION_KEY, [
            'key1'  => 'Element::input',
            'key2'  => 'regular-text',
            'key3'  => 'Element::uploadInput',
            'key4'  => G3_IMG_URL . '/avatar.png',
            'key5'  => '5',
            'key6'  => 'Some texts here for textarea testing.',
            'key7'  => 'option2',
            'key8'  => '0',
            'key9'  => 'option2',
            'key10' => 'option2',
            'key11' => ['1', '2'],
            'key12' => ['0'],
            'key13' => ['1'],
        ], false);
    }

    #[Override]
    protected function form(): void
    {
        if (isset($_REQUEST['page']) && $_REQUEST['page'] === 'developer-mode') {
            $this->formOption    = Option::cache(SystemService::FORM_OPTION_KEY, $this->formOption);
            $this->settingOption = Option::cache(SystemService::SETTING_OPTION_KEY, $this->settingOption);
        }
        if (isset($_REQUEST['page']) && $_REQUEST['page'] === 'open-platform') {
            $this->opMPOption = Option::cache(SystemService::OPEN_WECHAT_OA_KEY, $this->opMPOption);
        }
    }

    #[Override]
    protected function init(): void
    {
        $this->wpAutoUpdateHandle();
        $this->emojiHandle();
        $this->wpHeadHandle();
    }

    #[Override]
    protected function admin(): void
    {
        $this->onActivate();

        $this->thanksHandle();
        $this->upgradeHandle();
        $this->themeInstallHandle();
        $this->adminTitleHandle();
        $this->gutenbergInAdmin();

        $this->cleanerSetting();

        // If defined constant G3_HIDE_DEVELOPER_MODE and it's true, then hide developer mode menu
        if (defined('G3_HIDE_DEVELOPER_MODE') && constant('G3_HIDE_DEVELOPER_MODE')) {
            return;
        }

        // Add admin pages & actions in developer mode
        $this->advancedSetting();
        $this->flushSetting();
        $this->formSetting();
        add_action('wp_ajax_g3_admin_flush_options', [$this, '_ajaxFlushOptions']);
        $this->flushRewriteRulesHandle();
        $this->flushCacheHandle();
        $this->generateObjectCacheFile();
        $this->opMpSetting();
    }

    #[Override]
    protected function adminMenu(): void
    {
        $this->adminMenuHandle();
        $this->welcomePage();
        $this->toolsPageHandle();
        $this->pluginsPageHandle();

        // open platform menu
        add_submenu_page(
            'g3-settings',
            __('Open Platform', 'G3'),
            __('Open Platform', 'G3'),
            'manage_options',
            'open-platform',
            [$this, 'openPlatform'],
            18
        );
        // junk cleaner menu
        add_submenu_page(
            'g3-settings',
            __('Junk Cleaner', 'G3'),
            __('Junk Cleaner', 'G3'),
            'manage_options',
            'junk-cleaner',
            [$this, 'renderJunkCleaner'],
            19
        );

        // developer menu
        if (defined('G3_HIDE_DEVELOPER_MODE') && constant('G3_HIDE_DEVELOPER_MODE')) {
            return;
        } else {
            add_submenu_page(
                'g3-settings',
                __('Developer Mode', 'G3'),
                __('Developer Mode', 'G3'),
                'manage_options',
                'developer-mode',
                [$this, 'render'],
                20
            );
        }
    }

    #[Override]
    protected function system(): void
    {
        // Auto define environment
        // $this->autoDefineEnvironment();
    }

    public function handleLoginHeaderUrl(string $url): string
    {
        if (!isset($this->settingOption['adminLogo']) || $this->settingOption['adminLogo'] !== '0') {
            return $url;
        }

        return home_url('/');
    }

    public function handleLoginHeaderText(string $text): string
    {
        if (!isset($this->settingOption['adminLogo']) || $this->settingOption['adminLogo'] !== '0') {
            return $text;
        }

        return get_bloginfo('name');
    }

    public function handleLoginHead(): void
    {
        if (!isset($this->settingOption['adminLogo']) || $this->settingOption['adminLogo'] !== '0') {
            return;
        }

        $themeLogo   = G3_THEME_IMG_URL . '/logo.png';
        $defaultLogo = G3_IMG_URL . '/logo.png';
        $logo        = Validator::isImage($themeLogo) ? $themeLogo : $defaultLogo;
        echo '<style type="text/css">
            #login h1 a {
                background-image: url("' . $logo . '") !important;
                background-position: bottom center !important;
                background-size: 100% auto !important;
                width: auto !important;
            }
        </style>';
    }

    #[Override]
    protected function front(): void
    {
        // show_admin_bar subscription is registered in start()
    }

    private function onActivate(): void
    {
        if (isset($_GET['g3-activated']) && $_GET['g3-activated'] == 1) {
            wp_safe_redirect(
                admin_url('index.php?page=g3-welcome'),
                302,
                'G3'
            );
        }
    }

    /**
     * Auto define environment constant WP_ENVIRONMENT_TYPE
     * Option value: local, development, staging, production
     */
    private function autoDefineEnvironment(): void
    {
        $option = $this->settingOption;
        if (isset($option['environment']) && in_array($option['environment'], ['local', 'development', 'staging', 'production'])) {
            define('WP_ENVIRONMENT_TYPE', $option['environment']);
        }
    }

    public function render(): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . __('Developer Mode', 'G3') . '</h1>';
        $tabs = [
            'system'  => __('System', 'G3'),
            'rewrite' => __('Rewrite Rules', 'G3'),
            'setting' => __('Settings'),
            'flush'   => __('Flush Data', 'G3'),
            'theme'   => __('Build Theme', 'G3'),
            'form'    => __('Form Demo', 'G3'),
            'html'    => __('HTML Demo', 'G3'),
            'help'    => __('Help'),
        ];
        Element::tab('Developer', 'system', $tabs);
        echo '</div>';
    }

    public function flushSetting(): void
    {
        add_settings_section(
            'flush',
            null,
            '__return_false',
            'developer-mode&tab=refresh'
        );
        register_setting(
            'flush',
            '',
        );
        Element::settingFields('developer-mode&tab=refresh', 'flush', [
            [
                'id'       => 'jl_flush_rewrite_rules',
                'title'    => __('Rewrite Rules', 'G3'),
                'callback' => [$this, '_flushRewriteButton']
            ],
            [
                'id'       => 'jl_flush_options',
                'title'    => __('Setting Options', 'G3'),
                'callback' => [$this, '_flushOptionButton']
            ],
            [
                'id'       => 'jl_flush_cache',
                'title'    => __('Cache Data', 'G3'),
                'callback' => [$this, '_flushCacheButton']
            ],
            [
                'id'       => 'jl_generate_object_cache',
                'title'    => __('Object Cache', 'G3'),
                'callback' => [$this, '_generateObjectCacheButton']
            ],
            [
                'id'       => 'auto_backup',
                'title'    => __('Auto Backup', 'G3'),
                'callback' => function () {
                    echo __('When the G3-Web plugin is uninstalled, the system will automatically backup related option configuration data, and clear the relevant option data in the database to avoid data pollution.<br>The backup file directory: ', 'G3') . '<code>/wp-plugins/g3/backup/options.php</code>.';
                }
            ]
        ]);
    }

    public function advancedSetting()
    {
        add_settings_section('devSetting', '', '__return_false', 'developer-mode&tab=setting');
        register_setting('devSetting', SystemService::SETTING_OPTION_KEY);
        Element::settingFields('developer-mode&tab=setting', 'devSetting', [
            // [
            //     'id'       => 'environment',
            //     'title'    => __('Environment', 'G3'),
            //     'callback' => function () {
            //         echo Element::select(SystemService::SETTING_OPTION_KEY, $this->settingOption, 'environment', __('Environment', 'G3'), __('Select the environment you are working in.', 'G3'), 'dev-environment', [
            //             'local'       => __('Local Env', 'G3'),
            //             'development' => __('Development Env', 'G3'),
            //             'staging'     => __('Staging Env', 'G3'),
            //             'production'  => __('Production Env', 'G3')
            //         ]);
            //     }
            // ],
            [
                'id'       => 'wpAutoUpdate',
                'title'    => __('WordPress Auto Update', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        SystemService::SETTING_OPTION_KEY,
                        $this->settingOption,
                        'wpAutoUpdate',
                        __('WordPress Auto Update', 'G3'),
                        // __('Disable WordPress auto updates.', 'G3')
                    );
                },
            ],
            [
                'id'       => 'translationsApi',
                'title'    => __('Translations API', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        SystemService::SETTING_OPTION_KEY,
                        $this->settingOption,
                        'translationsApi',
                        __('Translations API', 'G3'),
                        // __('Disable the translations API.', 'G3')
                    );
                },
            ],
            [
                'id'       => 'themeEditor',
                'title'    => __('Theme Editor', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        SystemService::SETTING_OPTION_KEY,
                        $this->settingOption,
                        'themeEditor',
                        __('Theme Editor', 'G3'),
                        // __('Disable the theme editor from the WordPress admin.', 'G3')
                    );
                },
            ],
            [
                'id'       => 'pluginEditor',
                'title'    => __('Plugin Editor', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        SystemService::SETTING_OPTION_KEY,
                        $this->settingOption,
                        'pluginEditor',
                        __('Plugin Editor', 'G3'),
                        // __('Disable the plugin editor from the WordPress admin.', 'G3')
                    );
                },
            ],
            [
                'id'       => 'siteHealth',
                'title'    => __('Site Health', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        SystemService::SETTING_OPTION_KEY,
                        $this->settingOption,
                        'siteHealth',
                        __('Site Health', 'G3'),
                        // __('Disable the site health from the WordPress admin.', 'G3')
                    );
                },
            ],
            [
                'id'       => 'themeCustomize',
                'title'    => __('Theme Customize', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        SystemService::SETTING_OPTION_KEY,
                        $this->settingOption,
                        'themeCustomize',
                        __('Theme Customize', 'G3'),
                        // __('Disable the theme customize from the WordPress admin.', 'G3')
                    );
                },
            ],
            [
                'id'       => 'blockPatterns',
                'title'    => __('Block Patterns', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        SystemService::SETTING_OPTION_KEY,
                        $this->settingOption,
                        'blockPatterns',
                        __('Block Patterns', 'G3'),
                        // __('Disable the block patterns from the WordPress admin.', 'G3')
                    );
                },
            ],
            [
                'id'       => 'permalink',
                'title'    => __('Permalinks'),
                'callback' => function () {
                    echo Element::switch(
                        SystemService::SETTING_OPTION_KEY,
                        $this->settingOption,
                        'permalink',
                        __('Permalinks', 'G3'),
                        // __('Disable the permalinks setting page from the WordPress admin.', 'G3')
                    );
                },
            ],
            [
                'id'       => 'helpLink',
                'title'    => __('Help Link', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        SystemService::SETTING_OPTION_KEY,
                        $this->settingOption,
                        'helpLink',
                        __('Help Link', 'G3'),
                        // __('Disable the help link from the WordPress admin.', 'G3')
                    );
                }
            ],
            [
                'id'       => 'adminBar',
                'title'    => __('Admin Bar', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        SystemService::SETTING_OPTION_KEY,
                        $this->settingOption,
                        'adminBar',
                        __('Admin Bar', 'G3'),
                        // __('Disable the admin bar for all users.', 'G3')
                    );
                },
            ],
            [
                'id'       => 'emoji',
                'title'    => 'Emoji',
                'callback' => function () {
                    echo Element::switch(
                        SystemService::SETTING_OPTION_KEY,
                        $this->settingOption,
                        'emoji',
                        'Emoji',
                        // __('Disable the emoji.', 'G3')
                    );
                },
            ],
            [
                'id'       => 'wpHead',
                'title'    => 'WP Head',
                'callback' => function () {
                    echo Element::switch(
                        SystemService::SETTING_OPTION_KEY,
                        $this->settingOption,
                        'wpHead',
                        'WP Head',
                        __('Clean the data wp head.', 'G3')
                    );
                },
            ],
            [
                'id'       => 'gutenberg',
                'title'    => 'Gutenberg',
                'callback' => function () {
                    echo Element::switch(
                        SystemService::SETTING_OPTION_KEY,
                        $this->settingOption,
                        'gutenberg',
                        'Gutenberg',
                        // __('Disable the block editor.', 'G3')
                    );
                },
            ],
            [
                'id'       => 'adminTitle',
                'title'    => __('Admin Page Title', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        SystemService::SETTING_OPTION_KEY,
                        $this->settingOption,
                        'adminTitle',
                        __('Admin Page Title', 'G3'),
                        __('Active & Remove <code>WordPress</code> in the admin page title.', 'G3')
                    );
                },
            ],
            [
                'id'       => 'adminLogo',
                'title'    => __('Admin Logo', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        SystemService::SETTING_OPTION_KEY,
                        $this->settingOption,
                        'adminLogo',
                        __('Admin Logo', 'G3'),
                        __('Active & Remove the WordPress Logo & menu in the admin bar & admin login page.', 'G3')
                    );
                },
            ],
            [
                'id'       => 'toolsPage',
                'title'    => __('Tools Page', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        SystemService::SETTING_OPTION_KEY,
                        $this->settingOption,
                        'toolsPage',
                        __('Tools Page', 'G3'),
                        // __('Remove Tools page from admin menu.', 'G3')
                    );
                }
            ],
            [
                'id'       => 'pluginsPage',
                'title'    => __('Plugins Page', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        SystemService::SETTING_OPTION_KEY,
                        $this->settingOption,
                        'pluginsPage',
                        __('Plugins Page', 'G3'),
                        // __('Remove Plugins page from admin menu.', 'G3')
                    );
                }
            ],
            [
                'id'       => 'themeInstall',
                'title'    => __('Theme Install', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        SystemService::SETTING_OPTION_KEY,
                        $this->settingOption,
                        'themeInstall',
                        __('Theme Install'),
                        __('Active & Remove Theme Install button in admin page themes.php.', 'G3')
                    );
                }
            ],
            [
                'id'       => 'dashboard',
                'title'    => __('Dashboard'),
                'callback' => function () {
                    echo Element::checkbox(SystemService::SETTING_OPTION_KEY, $this->settingOption, 'dashboard', __('Dashboard'), __('Check & Remove the default dashboard widgets.', 'G3'), '', [
                        '0' => __('Welcome Panel', 'G3'),
                        '1' => __('Site Health Status'),
                        '2' => __('At a Glance'),
                        '3' => __('Activity'),
                        '4' => __('Quick Draft'),
                        '5' => __('WordPress Events and News')
                    ], false);
                },
            ],
            [
                'id'       => 'footerThanks',
                'title'    => 'Footer Thanks',
                'callback' => function () {
                    echo Element::input(SystemService::SETTING_OPTION_KEY, $this->settingOption, 'footerThanks', 'Footer Thanks', __('Set the data which will be displayed in the left footer area.', 'G3'));
                },
            ],
            [
                'id'       => 'footerUpgrade',
                'title'    => 'Footer Upgrade',
                'callback' => function () {
                    echo Element::input(SystemService::SETTING_OPTION_KEY, $this->settingOption, 'footerUpgrade', 'Footer Upgrade', __('Set the data which will be displayed in the right footer area.', 'G3'));
                },
            ]
        ]);
    }

    public function formSetting()
    {
        add_settings_section(
            'formFields',
            __('Back-end Form Fields Demonstration', 'G3'),
            '__return_false',
            'developer-mode&tab=form'
        );
        register_setting('formFields', SystemService::FORM_OPTION_KEY);
        Element::settingFields('developer-mode&tab=form', 'formFields', [
            [
                'id'       => 'key1',
                'title'    => __('Input field', 'G3'),
                'callback' => function () {
                    echo Element::input(SystemService::FORM_OPTION_KEY, $this->formOption, 'key1', __('Input field', 'G3'), __('Method', 'G3') . ': <code>Element::input()</code>');
                },
                'args'     => [
                    'label_for'         => 'key1',
                    'sanitize_callback' => 'sanitize_text_field',
                ]
            ],
            [
                'id'       => 'key2',
                'title'    => __('Longer input', 'G3'),
                'callback' => function () {
                    echo Element::input(SystemService::FORM_OPTION_KEY, $this->formOption, 'key2', __('Longer input', 'G3'), __('Set the class parameter to <code>regular-text</code>', 'G3'), 'text', 'regular-text');
                },
                'args'     => [
                    'label_for'         => 'key2',
                    'sanitize_callback' => 'sanitize_text_field',
                ]
            ],
            [
                'id'       => 'key3',
                'title'    => __('File upload', 'G3'),
                'callback' => function () {
                    echo Element::uploadInput(SystemService::FORM_OPTION_KEY, $this->formOption, 'key3', __('File upload', 'G3'), __('Method', 'G3') . ': <code>Element::uploadInput()</code>');
                },
                'args'     => [
                    'label_for'         => 'key3',
                    'sanitize_callback' => 'esc_url_raw',
                ]
            ],
            [
                'id'       => 'key4',
                'title'    => __('Image upload & preview', 'G3'),
                'callback' => function () {
                    echo Element::imageInput(SystemService::FORM_OPTION_KEY, $this->formOption, 'key4', __('Image upload & preview', 'G3'), __('Method', 'G3') . ': <code>Element::imageInput()</code>');
                },
                'args'     => [
                    'label_for'         => 'key4',
                    'sanitize_callback' => 'esc_url_raw',
                ]
            ],
            [
                'id'       => 'key5',
                'title'    => __('Counter input', 'G3'),
                'callback' => function () {
                    echo Element::counterInput(SystemService::FORM_OPTION_KEY, $this->formOption, 'key5', __('Counter input', 'G3'), __('Custom Texts', 'G3'), __('Method', 'G3') . ': <code>Element::counterInput()</code>');
                },
                'args'     => [
                    'label_for'         => 'key5',
                    'sanitize_callback' => 'absint',
                ]
            ],
            [
                'id'       => 'key6',
                'title'    => __('Textarea', 'G3'),
                'callback' => function () {
                    echo Element::textarea(SystemService::FORM_OPTION_KEY, $this->formOption, 'key6', __('Textarea', 'G3'), __('Method', 'G3') . ': <code>Element::textarea()</code>');
                },
                'args'     => [
                    'label_for'         => 'key6',
                    'sanitize_callback' => 'wp_kses_post',
                ]
            ],
            [
                'id'       => 'key7',
                'title'    => __('Select field', 'G3'),
                'callback' => function () {
                    echo Element::select(
                        SystemService::FORM_OPTION_KEY,
                        $this->formOption,
                        'key7',
                        __('Select field', 'G3'),
                        __('Method', 'G3') . ': <code>Element::select()</code>',
                        '',
                        [
                            'option1' => 'option1',
                            'option2' => 'option2',
                            'option3' => 'option3',
                            'option4' => 'option4',
                            'option5' => 'option5',
                        ]
                    );
                },
                'args'     => ['label_for' => 'key7']
            ],
            [
                'id'       => 'key8',
                'title'    => __('Enable Select', 'G3'),
                'callback' => function () {
                    echo Element::enable(SystemService::FORM_OPTION_KEY, $this->formOption, 'key8', __('Enable Select', 'G3'), __('Method', 'G3') . ': <code>Element::enable()</code>');
                },
                'args'     => ['label_for' => 'key8']
            ],
            [
                'id'       => 'key9',
                'title'    => __('Radio field', 'G3'),
                'callback' => function () {
                    echo Element::radio(SystemService::FORM_OPTION_KEY, $this->formOption, 'key9', __('Radio field', 'G3'), __('Method', 'G3') . ': <code>Element::radio()</code>', '', [
                        'option1' => 'option1',
                        'option2' => 'option2',
                        'option3' => 'option3',
                        'option4' => 'option4',
                        'option5' => 'option5',
                    ]);
                }
            ],
            [
                'id'       => 'key10',
                'title'    => __('Change the direction', 'G3'),
                'callback' => function () {
                    echo Element::radio(SystemService::FORM_OPTION_KEY, $this->formOption, 'key10', __('Change the direction', 'G3'), '', '', [
                        'option1' => 'option1',
                        'option2' => 'option2',
                        'option3' => 'option3',
                    ], false);
                }
            ],
            [
                'id'       => 'key11',
                'title'    => __('Checkbox field', 'G3'),
                'callback' => function () {
                    echo Element::checkbox(SystemService::FORM_OPTION_KEY, $this->formOption, 'key11', __('Checkbox field', 'G3'), __('Method', 'G3') . ': <code>Element::checkbox()</code>. ' . __('Tip', 'G3') . ': ' . __('the <code>value</code> is an array.', 'G3'), '', [
                        '0' => 'option1',
                        '1' => 'option2',
                        '2' => 'option3',
                        '3' => 'option4',
                        '4' => 'option5'
                    ]);
                }
            ],
            [
                'id'       => 'key12',
                'title'    => __('Only one option', 'G3'),
                'callback' => function () {
                    echo Element::checkbox(SystemService::FORM_OPTION_KEY, $this->formOption, 'key12', __('Only one option', 'G3'), __('Tip', 'G3') . __(': if <code>checkbox</code> is not checked, it will not submit any value what means the data does not exist.', 'G3'), '', ['0' => __('Enable', 'G3')]);
                }
            ],
            [
                'id'       => 'key13',
                'title'    => __('Switch', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        SystemService::FORM_OPTION_KEY,
                        $this->formOption,
                        'key13',
                        __('Switch', 'G3'),
                        __('Method', 'G3') . ': <code>Element::switch()</code> ' . __('Value') . ': 1 / 0',
                    );
                }
            ]
        ]);
    }

    public function _flushOptions(): void
    {
        $options = @require_once G3_PlUGIN_DIR . '/backup/options.php';

        if (!is_array($options)) {
            $options = [];
        }

        foreach ($options as $optionKey => $optionValue) {
            update_option($optionKey, $optionValue);
        }
    }

    public function _flushRewriteButton(): void
    {
        echo '<button class="button button-error" name="jl_flush_rewrite_rules" type="submit">' . __('Flush Rewrite Rules', 'G3') . '</button>';
        echo '<p class="description">' . __('Flush and fix the rewrite rules.', 'G3') . '</p>';
    }

    public function _flushOptionButton(): void
    {
        echo '<button class="button" id="g3-action__flush-options" type="button">' . __('Flush Setting Options', 'G3') . '</button>';
        echo '<p class="description">' . __('Flush and restore setting options from the last auto-generated backup data. File:', 'G3') . '<code>/wp-plugins/g3/backup/options.php</code></p>';
    }

    public function _flushCacheButton(): void
    {
        echo '<button class="button" name="jl_flush_cache" type="submit">' . __('Flush Cache', 'G3') . '</button>';
        echo '<p class="description">' . __('Flush all the cache data.', 'G3') . '</p>';
    }

    public function _generateObjectCacheButton(): void
    {
        echo '<button class="button button-primary" name="jl_generate_object_cache" type="submit">' . __('Generate object-cache.php', 'G3') . '</button>';
        echo '<p class="description">' . __('Generate the drop-in file <code><i>object-cache.php</i></code> in <code><i>wp-content</i></code> directory if it does not exist.', 'G3') . '</p>';
    }

    public function flushRewriteRulesHandle(): void
    {
        if (isset($_POST['jl_flush_rewrite_rules']) && current_user_can('manage_options')) {
            RewriteRouter::flushRewriteRules();
            add_settings_error('flush', 'rewrite_flushed', __('Rewrite rules flushed successfully!', 'G3'), 'updated');
        }
    }

    public function _ajaxFlushOptions(): void
    {
        if (current_user_can('manage_options')) {
            $this->_flushOptions();
            wp_send_json([
                'code'    => 200,
                'message' => __('Refreshed', 'G3')
            ]);
        } else {
            wp_send_json([
                'code'    => 403,
                'message' => __('Forbidden', 'G3')
            ], 403);
        }
    }

    public function flushCacheHandle(): void
    {
        if (isset($_POST['jl_flush_cache']) && current_user_can('manage_options')) {
            wp_cache_flush();
            add_settings_error('flush', 'cache_flushed', __('Cache flushed successfully!', 'G3'), 'updated');
        }
    }

    public function generateObjectCacheFile(): void
    {
        if (isset($_POST['jl_generate_object_cache']) && current_user_can('manage_options')) {
            $file   = WP_CONTENT_DIR . '/object-cache.php';
            $source = G3_EXT_DIR . '/cache/object-cache.php';
            if (!file_exists($file)) {
                if (@copy($source, $file)) {
                    add_settings_error('flush', 'object_cache_generated', __('object-cache.php file generated successfully!', 'G3'), 'updated');
                } else {
                    add_settings_error(
                        'flush',
                        'object_cache_failed',
                        sprintf(
                            __('Failed to generate object-cache.php file. Please check file permissions for %s.', 'G3'),
                            esc_html($file)
                        ),
                        'error'
                    );
                }
            } else {
                add_settings_error('flush', 'object_cache_exists', __('object_cache.php file already exists.', 'G3'), 'error');
            }
        }
    }

    public function wpAutoUpdateHandle(): void
    {
        if (!is_admin()) return;
        if (isset($this->settingOption['wpAutoUpdate']) && $this->settingOption['wpAutoUpdate'] === '0') {
            // Remove update-core.php submenu page
            add_action('admin_menu', function () {
                remove_submenu_page('index.php', 'update-core.php');
            });
            // Disable automatic update
            add_filter('automatic_updater_disabled', '__return_true');
            add_filter('pre_site_transient_update_core', '__return_null');
            remove_action('wp_version_check', 'wp_version_check');
            // Remove automatic update schedule
            remove_action('init', 'wp_schedule_update_checks');
            wp_clear_scheduled_hook('wp_version_check');
            wp_clear_scheduled_hook('wp_update_plugins');
            wp_clear_scheduled_hook('wp_update_themes');
            wp_clear_scheduled_hook('wp_maybe_auto_update');
            // Remove plugin update notice
            remove_action('load-plugins.php', 'wp_update_plugins');
            remove_action('load-update.php', 'wp_update_plugins');
            remove_action('load-update-core.php', 'wp_update_plugins');
            remove_action('admin_init', '_maybe_update_plugins');
            add_filter('pre_site_transient_update_plugins', '__return_null');
            // Remove theme update notice
            remove_action('load-themes.php', 'wp_update_themes');
            remove_action('load-update.php', 'wp_update_themes');
            remove_action('load-update-core.php', 'wp_update_themes');
            remove_action('admin_init', '_maybe_update_themes');
            remove_action('admin_init', '_maybe_update_core');
            add_filter('pre_site_transient_update_themes', '__return_null');
            // Remove translation update notice
            remove_action('load-update-core.php', 'wp_update_translations');
            add_filter('pre_site_transient_update_core', '__return_null');
        }
    }
    public function adminMenuHandle(): void
    {
        // Remove theme editor
        if (isset($this->settingOption['themeEditor']) && $this->settingOption['themeEditor'] === '0') {
            remove_action('admin_menu', '_add_themes_utility_last', 101);
            remove_submenu_page('themes.php', 'site-editor.php');
        }
        // Remove plugin editor
        if (isset($this->settingOption['pluginEditor']) && $this->settingOption['pluginEditor'] === '0')
            remove_submenu_page('plugins.php', 'plugin-editor.php');
        // Remove site health
        if (isset($this->settingOption['siteHealth']) && $this->settingOption['siteHealth'] === '0')
            remove_submenu_page('tools.php', 'site-health.php');
        // Remove block patterns
        if (isset($this->settingOption['blockPatterns']) && $this->settingOption['blockPatterns'] === '0')
            remove_submenu_page('themes.php', 'site-editor.php?p=/pattern');
        // Remove permalink settings
        if (isset($this->settingOption['permalink']) && $this->settingOption['permalink'] === '0')
            remove_submenu_page('options-general.php', 'options-permalink.php');
    }

    /**
     * Remove theme customize
     * @param array $caps
     * @param string $cap
     * @param int $user_id
     * @param array $args
     * @return array
     */
    public function themeCustomizeHandle($caps, $cap, $user_id = 0, $args = []): array
    {
        if ($cap === 'customize' && isset($this->settingOption['themeCustomize']) && $this->settingOption['themeCustomize'] === '0') {
            $caps = ['do_not_allow'];
        }
        return $caps;
    }
    /**
     * Disable WordPress translations API
     * 
     * @param WP_Error|bool $result
     * @param string $action
     * @param array $args
     * @return WP_Error|bool
     */
    public function translationsApiHandle($result, $action, $args)
    {
        if (!isset($this->settingOption['translationsApi']) || $this->settingOption['translationsApi'] !== '0') {
            return $result;
        }

        return new WP_Error('translations_api_disabled', 'Translations API disabled');
    }

    /**
     * Remove help link
     */
    public function helpLinkHandle(): void
    {
        if (!isset($this->settingOption['helpLink']) || $this->settingOption['helpLink'] !== '0') {
            return;
        }

        $screen = get_current_screen();
        $screen->remove_help_tabs();
    }

    public function adminBarMenuHandle($wp_admin_bar): void
    {
        if (!isset($this->settingOption['adminLogo']) || $this->settingOption['adminLogo'] !== '1') {
            return;
        }
        // Remove default node: LOGO
        $wp_admin_bar->remove_node('wp-logo');
    }

    /**
     * Remove top toolbar
     * 移除顶部工具栏
     */
    public function adminBarHandle(): bool
    {
        if (!isset($this->settingOption['adminBar']) || $this->settingOption['adminBar'] !== '0') {
            return true;
        }
        return false;
    }

    public function emojiHandle(): void
    {
        if (!isset($this->settingOption['emoji']) || $this->settingOption['emoji'] !== '0') {
            return;
        }

        /** Remove emoji detection script */
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        // close emoji feature
        add_filter('emoji_svg_url', '__return_false');
    }

    public function wpHeadHandle(): void
    {
        if (!isset($this->settingOption['wpHead']) || $this->settingOption['wpHead'] !== '1') {
            return;
        }

        /** Remove S.W.org DNS prefetch in head */
        remove_action('wp_head', 'wp_resource_hints', 2);
        /** Remove generator meta in head */
        remove_action('wp_head', 'wp_generator');
        /** Remove rsd link in head */
        remove_action('wp_head', 'rsd_link');
        /** Remove REST Api output link in head */
        remove_action('wp_head', 'rest_output_link_wp_head', 10);
        /** Remove robots meta: max_image_preview_large */
        remove_filter('wp_robots', 'wp_robots_max_image_preview_large');
        /** Disable responsive image intrinsic size style */
        add_filter('wp_img_tag_add_auto_sizes', '__return_false');
        /** Remove global styles in head */
        add_action('wp_loaded', function () {
            remove_action('wp_footer', 'wp_enqueue_global_styles', 1);
        }, 20);
        add_action('wp_footer', function () {
            wp_dequeue_style('core-block-supports');
        }, 5);
        // add_action('wp_enqueue_scripts', function () {
        //     wp_deregister_style('global-styles');
        //     wp_dequeue_style('global-styles');
        //     wp_cache_delete('global-styles', 'options');
        // }, 20);
    }

    protected function scripts(): void
    {
        if (isset($this->settingOption['gutenberg']) && $this->settingOption['gutenberg'] !== '1') {
            /** Remove Gutenberg styles */
            wp_dequeue_style('wp-block-library');
            /** Remove Gutenberg theme styles */
            wp_dequeue_style('wp-block-library-theme');
            /** Remove classic theme styles */
            wp_dequeue_style('classic-theme-styles');
        }
    }

    private function gutenbergInAdmin(): void
    {
        if (isset($this->settingOption['gutenberg']) && $this->settingOption['gutenberg'] !== '1') {
            remove_theme_support('widgets-block-editor');
            /** Disable Gutenberg editor for all posts */
            add_filter('use_block_editor_for_post', '__return_false');
            add_filter('use_block_editor_for_post_type', '__return_false');
        }
    }

    public function noticeInAdminBar($wp_admin_bar)
    {
        $wp_admin_bar->add_node(
            [
                'id'    => 'G3-License-Notice',
                'title' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M3.78307 2.82598L12 1L20.2169 2.82598C20.6745 2.92766 21 3.33347 21 3.80217V13.7889C21 15.795 19.9974 17.6684 18.3282 18.7812L12 23L5.6718 18.7812C4.00261 17.6684 3 15.795 3 13.7889V3.80217C3 3.33347 3.32553 2.92766 3.78307 2.82598ZM5 4.60434V13.7889C5 15.1263 5.6684 16.3752 6.7812 17.1171L12 20.5963L17.2188 17.1171C18.3316 16.3752 19 15.1263 19 13.7889V4.60434L12 3.04879L5 4.60434ZM13 10H16L11 17V12H8L13 5V10Z"></path></svg>' . __('License Expired', 'G3'),
                'href'  => admin_url('admin.php?page=g3-verify-license'),
                'meta'  => [
                    'title' => __('License Expired', 'G3'),
                    'class' => 'g3-license-notice',
                ],
            ]
        );
    }

    #[Override]
    public function x(): void
    {
        add_action("admin_bar_menu", [$this, "noticeInAdminBar"], 999);
        add_action('admin_menu', function () {
            add_submenu_page(
                "index.php",
                "Verify License",
                "G3 License",
                "read",
                "g3-verify-license",
                [$this, "formHandler"],
                2,
            );
        });
    }

    #[Override]
    public function y(): void
    {
        self::$z = $this->loader;
    }

    public static function time()
    {
        return self::$z ? self::$z->gE() : false;
    }

    public function formHandler(): void
    {
        $current_notice = null;

        if (isset($_POST["submit"]) && isset($_POST["g3_code"])) {
            if (
                !isset($_POST["g3_license_nonce"]) ||
                !wp_verify_nonce($_POST["g3_license_nonce"], "g3_license_verify")
            ) {
                $current_notice = [
                    "type"    => "error",
                    "message" => __("Security check failed. Please try again.", "G3")
                ];
            } else {
                $code = sanitize_text_field($_POST["g3_code"]);
                if (empty($code)) {
                    $current_notice = [
                        "type"    => "error",
                        "message" => __("Please enter a valid license code.", "G3")
                    ];
                } else {
                    $result         = $this->loader->vY($code);
                    $current_notice = is_wp_error($result) ?
                        [
                            "type"    => "error",
                            "message" => $result->get_error_message()
                        ] :
                        [
                            "type"     => "success",
                            "message"  => __("Verified successfully! Redirecting...", "G3"),
                            "redirect" => true
                        ];
                }
            }
        }

        $this->formRender($current_notice);
    }

    private function formRender($notice = null)
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(__('Verify License', 'G3')); ?></h1>
            <?php
            if ($notice && is_array($notice) && isset($notice['type']) && isset($notice['message'])) {
                $notice_class = 'notice notice-' . esc_attr($notice['type']) . ' is-dismissible';
                echo '<div class="' . $notice_class . '">';
                echo '<p>' . esc_html($notice['message']) . '</p>';
                echo '</div>';
                if (isset($notice['redirect']) && $notice['redirect']) {
                    echo '<script>';
                    echo 'setTimeout(function() {';
                    echo '    window.location.href = "' . esc_url(admin_url('index.php?page=g3-welcome')) . '";';
                    echo '}, 2000);';
                    echo '</script>';
                }
            }
            ?>
            <form method="post" action="">
                <?php wp_nonce_field('g3_license_verify', 'g3_license_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="g3_code"><?php echo esc_html(__('G3 License Code', 'G3')); ?></label>
                        </th>
                        <td>
                            <input type="text" id="g3_code" name="g3_code" value=""
                                placeholder="<?php echo 'G3-XXXX-XXXX-XXXX-XXXX'; ?>"
                                style="text-transform: uppercase; letter-spacing: 2px; font-family: monospace; min-width: 300px;"
                                required>
                            <p class="description">
                                <?php
                                echo sprintf(
                                    __('Please enter your G3 license code to activate G3 Web.<br>No License? Click <a href="%s" target="_blank">HERE</a> to get one!', 'G3'),
                                    esc_url('https://www.jealer.com/g3-web/license/')
                                ) . '<br>test: G3-TEST-CODE-DEMO-0001';
                                ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Verify License', 'G3'), 'primary', 'submit'); ?>
            </form>
        </div>
        <?php
    }

    public function welcomePage()
    {
        add_submenu_page(
            "index.php",
            __('Welcome', 'G3'),
            __('Welcome', 'G3'),
            "read",
            "g3-welcome",
            function () {
                require_once WP_PLUGIN_DIR . "/g3/templates/developer/w.php";
            },
            1,
        );
    }

    private function adminTitleHandle()
    {
        if (!isset($this->settingOption['adminTitle']) || $this->settingOption['adminTitle'] !== '1') {
            return;
        }
        add_filter('admin_title', function ($admin_title, $title) {
            return $title . ' - ' . get_bloginfo('name');
        }, 10, 2);
    }

    private function thanksHandle()
    {
        if (!isset($this->settingOption['footerThanks']) || !$this->settingOption['footerThanks']) {
            return;
        }
        add_filter('admin_footer_text', function ($text) {
            return $this->settingOption['footerThanks'];
        });
    }

    private function upgradeHandle()
    {
        if (!isset($this->settingOption['footerUpgrade']) || !$this->settingOption['footerUpgrade']) {
            return;
        }
        add_filter('update_footer', function ($text) {
            return $this->settingOption['footerUpgrade'];
        }, 999);
    }

    private function toolsPageHandle()
    {
        if (!isset($this->settingOption['toolsPage']) || $this->settingOption['toolsPage'] !== '0') {
            return;
        }
        remove_menu_page('tools.php');
    }

    private function pluginsPageHandle()
    {
        if (!isset($this->settingOption['pluginsPage']) || $this->settingOption['pluginsPage'] !== '0') {
            return;
        }
        add_action('admin_menu', function () {
            remove_menu_page('plugins.php');
        }, 999);
    }

    private function themeInstallHandle()
    {
        if (!isset($this->settingOption['themeInstall']) || $this->settingOption['themeInstall'] !== '0') {
            return;
        }
        if (strpos($_SERVER['REQUEST_URI'], 'themes.php') !== false) {
            echo '<style>a.hide-if-no-js.page-title-action{display:none}</style>';
        }
    }

    protected function dashboard(): void
    {
        if (!isset($this->settingOption['dashboard'])) {
            return;
        }

        if (in_array('0', $this->settingOption['dashboard'], true)) {
            remove_action('welcome_panel', 'wp_welcome_panel');
        }
        if (in_array('1', $this->settingOption['dashboard'], true)) {
            remove_meta_box('dashboard_site_health', 'dashboard', 'normal');
        }
        if (in_array('2', $this->settingOption['dashboard'], true)) {
            remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
        }
        if (in_array('3', $this->settingOption['dashboard'], true)) {
            remove_meta_box('dashboard_activity', 'dashboard', 'normal');
        }
        if (in_array('4', $this->settingOption['dashboard'], true)) {
            remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
        }
        if (in_array('5', $this->settingOption['dashboard'], true)) {
            remove_meta_box('dashboard_primary', 'dashboard', 'side');
        }
    }

    public function openPlatform(): void
    {
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . __('Open Platform', 'G3') . '</h1>';
        $args = [
            'wechatOA' => __('Wechat OA', 'G3')
        ];
        Element::tab('Developer', 'wechatOA', $args);
        echo '</div>';
    }

    private function opMpSetting(): void
    {
        add_settings_section(
            'opWechatOA',
            null,
            '__return_false',
            'open-platform&tab=wechatOA'
        );
        register_setting('opWechatOA', SystemService::OPEN_WECHAT_OA_KEY);
        Element::settingFields('open-platform&tab=wechatOA', 'opWechatOA', [
            [
                'id'       => 'type',
                'title'    => __('Official Account Type', 'G3'),
                'callback' => function () {
                    echo Element::select(
                        SystemService::OPEN_WECHAT_OA_KEY,
                        $this->opMPOption,
                        'type',
                        __('Official Account Type', 'G3'),
                        '',
                        '',
                        [
                            ''  => __('Please Select', 'G3'),
                            '1' => __('Service Account', 'G3'),
                            '2' => __('Subscription Account', 'G3'),
                            '3' => __('Verified Service Account', 'G3'),
                            '4' => __('Verified Subscription Account', 'G3'),
                        ]
                    );
                }
            ],
            [
                'id'       => 'slug',
                'title'    => __('Wechat ID', 'G3'),
                'callback' => function () {
                    echo Element::input(
                        SystemService::OPEN_WECHAT_OA_KEY,
                        $this->opMPOption,
                        'slug',
                        __('Wechat ID', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'slug',
                ]
            ],
            [
                'id'       => 'appId',
                'title'    => 'App ID',
                'callback' => function () {
                    echo Element::input(
                        SystemService::OPEN_WECHAT_OA_KEY,
                        $this->opMPOption,
                        'appId',
                        'App ID'
                    );
                },
                'args'     => [
                    'label_for' => 'appId',
                ]
            ],
            [
                'id'       => 'appSecret',
                'title'    => 'App Secret',
                'callback' => function () {
                    echo Element::input(
                        SystemService::OPEN_WECHAT_OA_KEY,
                        $this->opMPOption,
                        'appSecret',
                        'App Secret',
                        '',
                        'password'
                    );
                },
                'args'     => [
                    'label_for' => 'appSecret',
                ]
            ],
            [
                'id'       => 'token',
                'title'    => 'Token',
                'callback' => function () {
                    echo Element::input(
                        SystemService::OPEN_WECHAT_OA_KEY,
                        $this->opMPOption,
                        'token',
                        'Token'
                    );
                },
                'args'     => [
                    'label_for' => 'token',
                ]
            ],
            [
                'id'       => 'encodingAESKey',
                'title'    => 'Encoding AES Key',
                'callback' => function () {
                    echo Element::input(
                        SystemService::OPEN_WECHAT_OA_KEY,
                        $this->opMPOption,
                        'encodingAESKey',
                        'Encoding AES Key'
                    );
                },
                'args'     => [
                    'label_for' => 'encodingAESKey',
                ]
            ],
            [
                'id'       => 'url',
                'title'    => 'URL',
                'callback' => function () {
                    echo get_site_url() . '/dev/wechat_oa/callback';
                }
            ]
        ]);
    }

    public function renderJunkCleaner(): void
    {
        require_once __DIR__ . '/views/tab-junk-cleaner.php';
    }

    private function cleanerSetting(): void
    {
        $postTypes = get_post_types([
            'public'  => true,
            'show_ui' => true,
        ]);
        unset($postTypes['attachment']);
        $postTypes = array_values($postTypes);

        $draft     = 0;
        $autoDraft = 0;
        $trash     = 0;

        foreach ($postTypes as $postType) {
            $postCount  = wp_count_posts($postType);
            $draft     += $postCount->draft ?? 0;
            $autoDraft += $postCount->{'auto-draft'} ?? 0;
            $trash     += $postCount->trash ?? 0;
        }

        $revision = new WP_Query([
            'post_type'      => 'revision',
            'posts_per_page' => -1,
            'post_status'    => 'any',
        ]);
        $revision = $revision->found_posts;

        $fields = [
            [
                'id'       => 'draft',
                'title'    => __('Draft'),
                'callback' => function () use ($draft) {
                    $this->renderActions('clear-draft', $draft);
                }
            ],
            [
                'id'       => 'auto-draft',
                'title'    => __('Auto Draft'),
                'callback' => function () use ($autoDraft) {
                    $this->renderActions('clear-auto-draft', $autoDraft);
                }
            ],
            [
                'id'       => 'trash',
                'title'    => __('Trash', 'G3'),
                'callback' => function () use ($trash) {
                    $this->renderActions('clear-trash', $trash);
                }
            ],
            [
                'id'       => 'revision',
                'title'    => __('Revision'),
                'callback' => function () use ($revision) {
                    $this->renderActions('clear-revision', $revision);
                }
            ]
        ];
        add_settings_section(
            'junk-cleaner',
            null,
            '__return_false',
            'junk-cleaner'
        );
        register_setting('junk-cleaner', 'junk-cleaner');
        Element::settingFields('junk-cleaner', 'junk-cleaner', $fields);
    }

    private function renderActions(string $action, int|string $count)
    {
        $total = __('Total', 'G3');
        $clear = __('Clear');
        echo "<button class='button junk-action-button' type='button' data-action='{$action}' data-count='{$count}'>{$clear}</button>";
        echo "<p class='description'>{$total}: <span class='junk-count'>{$count}</span></p>";
    }

    public function ajax(): void
    {
        $this->addJunkCleanerAjax('g3_clear_draft', 'draft');
        $this->addJunkCleanerAjax('g3_clear_auto_draft', 'auto-draft');
        $this->addJunkCleanerAjax('g3_clear_trash', 'trash');
        $this->addJunkCleanerAjax('g3_clear_revision', 'inherit', 'revision');
    }

    private function addJunkCleanerAjax(string $action, string $status, string $postType = 'any')
    {
        add_action('wp_ajax_' . $action, function () use ($status, $postType) {
            if (!current_user_can('manage_options') || !is_admin()) {
                Response::ajaxIllegal();
            }
            $result = $this->deletePostsByStatus($status, $postType);
            if ($result) {
                Response::ajaxDeleted();
            } else {
                Response::ajaxFailed();
            }
        });
    }

    private function deletePostsByStatus(string $status, string $postType = 'any'): bool
    {
        $query = new WP_Query([
            'post_type'      => $postType,
            'post_status'    => $status,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        while ($query->have_posts()) {
            $query->the_post();
            wp_delete_post(get_the_ID(), true);
        }
        wp_reset_postdata();
        return $query->found_posts > 0;
    }
}
