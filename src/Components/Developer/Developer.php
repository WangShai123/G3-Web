<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use JEALER\G3\Core\Admin\Panel;
use JEALER\G3\Core\Rewrite\RewriteRouter;
use JEALER\G3\Core\Router\Router;
use JEALER\G3\Services\PostService;
use JEALER\G3\Services\TemplateService;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Message;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Utilities\Response;
use JEALER\G3\Utilities\System;
use JEALER\G3\Utilities\Validator;
use JEALER\G3\Services\SystemService;
use Override;
use WP_Error;
use WP_Query;

class Developer extends Components {
    public array           $_form;
    public array           $opMPOption;
    public                 $v;
    public static          $z;
    public TemplateService $template;

    private function settingDefaults(): array
    {
        return [
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
            'adminTitle'      => '0',
            'adminLogo'       => '0',
            'toolsPage'       => '1',
            'pluginsPage'     => '1',
            'themeInstall'    => '0',
            'dashboard'       => ['0', '1', '2', '3', '4', '5'],
            'footerThanks'    => G3_NAME,
            'footerUpgrade'   => G3_VERSION,
        ];
    }

    private function formDefaults(): array
    {
        return [
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
        ];
    }

    private function settingOptions(): array
    {
        $option = get_option(SystemService::SETTING_OPTION_KEY, []);
        return is_array($option) ? $option : [];
    }
    protected function adminPanels(): array
    {
        return [
            $this->panel('developer-mode', __('Developer Mode', 'G3'))
                ->tab('general', __('Settings'))
                ->option(SystemService::SETTING_OPTION_KEY, $this->settingDefaults())
                ->switch('wpAutoUpdate', __('WordPress Auto Update', 'G3'))
                ->switch('translationsApi', __('Translations API', 'G3'))
                ->switch('themeEditor', __('Theme Editor', 'G3'))
                ->switch('pluginEditor', __('Plugin Editor', 'G3'))
                ->switch('siteHealth', __('Site Health', 'G3'))
                ->switch('themeCustomize', __('Theme Customize', 'G3'))
                ->switch('blockPatterns', __('Block Patterns', 'G3'))
                ->switch('helpLink', __('Help Link', 'G3'))
                ->switch('adminBar', __('Admin Bar', 'G3'))
                ->switch('emoji', 'Emoji')
                ->switch('gutenberg', 'Gutenberg')
                ->switch('adminTitle', 'WP ' . __('Title'))
                ->switch('adminLogo', 'WP Logo')
                ->switch('themeInstall', __('Theme') . ' ' . __('Install'))
                ->switch('toolsPage', __('Tools Page', 'G3'))
                ->switch('pluginsPage', __('Plugins Page', 'G3'))
                ->switch('permalink', __('Permalinks', 'G3'))
                ->switch('wpHead', 'WP Head', __('Clean the data in wp head.', 'G3'))
                ->checkbox('dashboard', __('Dashboard'), [
                    '0' => __('Welcome Panel', 'G3'),
                    '1' => __('Site Health Status'),
                    '2' => __('At a Glance'),
                    '3' => __('Activity'),
                    '4' => __('Quick Draft'),
                    '5' => __('WordPress Events and News')
                ], __('Check & Remove the default dashboard widgets.', 'G3'), false)
                ->input('footerThanks', __('Footer Thanks'), __('Set the data which will be displayed in the left footer area.', 'G3'))
                ->input('footerUpgrade', __('Footer Upgrade'), __('Set the data which will be displayed in the right footer area.', 'G3'))

                ->tab('flush', __('Flush Data', 'G3'))
                ->callback('g3_flush_rewrite_rules', __('Rewrite Rules', 'G3'), [$this, '_flushRewriteButton'])
                ->callback('g3_flush_rest_routes', 'REST ' . __('Route', 'G3'), [$this, '_flushRestRoutesButton'])
                ->callback('g3_flush_cache', __('Cache Data', 'G3'), [$this, '_flushCacheButton'])
                ->callback('jl_flush_options', __('Setting Options', 'G3'), [$this, '_flushOptionButton'])
                ->callback('auto_backup', __('Auto Backup', 'G3'), function () {
                    echo __('When the G3-Web plugin is uninstalled, the system will automatically backup related option configuration data, and clear the relevant option data in the database to avoid data pollution.<br>The backup file directory: ', 'G3') . '<code>/wp-content/G3-Web/cache/options.php</code>.';
                })
                ->callback('g3_generate_object_cache', __('Object Cache', 'G3'), [$this, '_generateObjectCacheButton'])
                ->callback('g3_migrate_views', __('Views Migration', 'G3'), [$this, '_migrateViewsButton'])

                ->tab('system', __('System', 'G3'))
                ->tab('rewrite', __('Rewrite Rules', 'G3'))
                ->tab('cron', __('WP Cron'))
                ->tab('theme', __('Build Theme', 'G3'))

                ->tab('form', __('Form Demo', 'G3'))
                ->option(SystemService::FORM_OPTION_KEY, $this->formDefaults())
                ->input('key1', __('Input field', 'G3'))
                ->file('key3', __('File upload', 'G3'))
                ->image('key4', __('Image upload & preview', 'G3'))
                ->number('key5', __('Counter input', 'G3'))
                ->textarea('key6', __('Textarea', 'G3'))
                ->select('key7', __('Select field', 'G3'), [
                    'option1' => 'option1',
                    'option2' => 'option2',
                    'option3' => 'option3',
                    'option4' => 'option4',
                    'option5' => 'option5',
                ])
                ->radio('key9', __('Radio field', 'G3'), [
                    'option1' => 'option1',
                    'option2' => 'option2',
                    'option3' => 'option3',
                    'option4' => 'option4',
                    'option5' => 'option5',
                ])
                ->radio('key10', __('Change the direction', 'G3'), [
                    'option1' => 'option1',
                    'option2' => 'option2',
                    'option3' => 'option3',
                ], '', false)
                ->checkbox('key11', __('Checkbox field', 'G3'), [
                    '0' => 'option1',
                    '1' => 'option2',
                    '2' => 'option3',
                    '3' => 'option4',
                    '4' => 'option5'
                ], __('Tip', 'G3') . ': ' . __('the <code>value</code> is an array.', 'G3'))
                ->checkbox('key12', __('Only one option', 'G3'), ['0' => __('Enable', 'G3')], __('Tip', 'G3') . __(': if <code>checkbox</code> is not checked, it will not submit any value what means the data does not exist.', 'G3'))
                ->switch('key13', __('Switch', 'G3'), __('Value') . ': 1 / 0')

                ->tab('html', __('HTML Demo', 'G3'))
                ->tab('help', __('Help'))
        ];
    }
    protected function adminPanelPage(): string
    {
        return 'developer-mode';
    }
    public function render(): void
    {
        $this->createPanel();
    }
    #[Override]
    protected function start(): void
    {
        /** @var TemplateService */
        $this->template = $this->container->get(TemplateService::class);
    }
    #[Override]
    protected function hooks(): void
    {
        $this->filter([
            'single_template'   => [[$this->template, 'singleTemplate'], 10, 1],
            'category_template' => [[$this->template, 'categoryTemplate'], 10, 1],
            'map_meta_cap'      => [[$this, 'themeCustomizeHandle'], 20, 4],
            'translations_api'  => [[$this, 'translationsApiHandle'], 10, 3],
            'show_admin_bar'    => [[$this, 'adminBarHandle']],
            'login_headerurl'   => [[$this, 'handleLoginHeaderUrl'], 10, 1],
            'login_headertext'  => [[$this, 'handleLoginHeaderText'], 10, 1],
        ]);

        $this->action([
            'login_head'     => [[$this, 'handleLoginHead']],
            'admin_head'     => [[$this, 'helpLinkHandle']],
            'admin_bar_menu' => [[$this, 'adminBarMenuHandle'], 999, 1],
        ]);
    }
    protected function form(): void
    {
        $this->adminTitleHandle();
        $this->gutenbergInAdmin();
        // If defined constant G3_HIDE_DEVELOPER_MODE and it's true, then hide developer mode menu
        if (defined('G3_HIDE_DEVELOPER_MODE') && constant('G3_HIDE_DEVELOPER_MODE')) {
            return;
        }
        $this->flushRewriteRulesHandle();
        $this->flushRestRoutesHandle();
        $this->flushCacheHandle();
        $this->flushOptionHandle();
        $this->generateObjectCacheFile();
        $this->migrateViewsHandle();
    }
    protected function init(): void
    {
        $this->wpAutoUpdateHandle();
        $this->emojiHandle();
        $this->wpHeadHandle();
    }

    protected function admin(): void
    {
        $this->onActivate();

        $this->thanksHandle();
        $this->upgradeHandle();
        $this->themeInstallHandle();
    }
    protected function adminMenu(): void
    {
        $this->adminMenuHandle();
        $this->welcomePage();
        $this->toolsPageHandle();
        $this->pluginsPageHandle();

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
    public function handleLoginHeaderUrl(string $url): string
    {
        $option = $this->settingOptions();
        if (($option['adminLogo'] ?? '0') === '1') return $url;

        return home_url('/');
    }
    public function handleLoginHeaderText(string $text): string
    {
        $option = $this->settingOptions();
        if (($option['adminLogo'] ?? '0') === '1') return $text;
        return get_bloginfo('name');
    }
    public function handleLoginHead(): void
    {
        $option = $this->settingOptions();
        if (($option['adminLogo'] ?? '0') === '1') return;

        $themeLogo   = G3_THEME_IMG_URL . '/logo.png';
        $defaultLogo = G3_IMG_URL . '/logo.png';
        $logo        = Validator::isImage($themeLogo) ? $themeLogo : $defaultLogo;
        echo '<style>
            #login h1 a {
                background-image: url("' . $logo . '") !important;
                background-position: bottom center !important;
                background-size: 100% auto !important;
                width: auto !important;
            }
        </style>';
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
    public function _flushOptions(): void
    {
        $options = require_once WP_CONTENT_DIR . '/G3-Web/cache/options.php';

        if (!is_array($options)) {
            $options = [];
        }

        foreach ($options as $optionKey => $optionValue) {
            update_option($optionKey, $optionValue);
        }
    }
    public function y(): void
    {
        self::$z = $this->loader;
    }
    public function _flushRewriteButton(): void
    {
        echo '<button class="button button-error" name="g3_flush_rewrite_rules" type="submit">' . __('Flush Rewrite Rules', 'G3') . '</button>';
        echo '<p class="description">' . __('Flush and fix the rewrite rules.', 'G3') . '</p>';
    }

    public function _flushRestRoutesButton(): void
    {
        echo '<button class="button" name="g3_flush_rest_routes" type="submit">' . __('Flush REST routes cache', 'G3') . '</button>';
        echo '<p class="description">' . __('Rebuild the REST route cache file.', 'G3') . '</p>';
    }

    public function _flushOptionButton(): void
    {
        echo '<button class="button" name="g3_flush_options" type="submit">' . __('Flush Setting Options', 'G3') . '</button>';
        echo '<p class="description">' . __('Flush and restore setting options from the last auto-generated backup data. File:', 'G3') . '<code>/wp-content/G3-Web/cache/options.php</code></p>';
    }

    public function _flushCacheButton(): void
    {
        echo '<button class="button" name="g3_flush_cache" type="submit">' . __('Flush Cache', 'G3') . '</button>';
        echo '<p class="description">' . __('Flush all the cache data.', 'G3') . '</p>';
    }
    public function _generateObjectCacheButton(): void
    {
        echo '<button class="button button-primary" name="g3_generate_object_cache" type="submit">' . __('Generate object-cache.php', 'G3') . '</button>';
        echo '<p class="description">' . __('Generate the drop-in file <code><i>object-cache.php</i></code> in <code><i>wp-content</i></code> directory if it does not exist.', 'G3') . '</p>';
    }
    public function _migrateViewsButton(): void
    {
        echo '<button class="button button-secondary" name="g3_migrate_views" type="submit">' . __('Migrate', 'G3') . '</button>';
        echo '<p class="description">' . __('Migrate views count data from <code>post_meta</code> table to the individual table <code>g3_posts_extra</code> if you actually need it.', 'G3') . '</p>';
    }
    public function flushRewriteRulesHandle(): void
    {
        if (isset($_POST['g3_flush_rewrite_rules']) && current_user_can('manage_options')) {
            $result = RewriteRouter::flushRewriteRules();
            if (is_wp_error($result)) {
                wp_admin_notice($result->get_error_message(), [
                    'type'        => 'error',
                    'dismissible' => true,
                ]);
                return;
            }
            wp_admin_notice(Message::flushed(), [
                'type'        => 'success',
                'dismissible' => true,
            ]);
        }
    }
    public static function time()
    {
        return self::$z ? self::$z->gE() : false;
    }
    public function flushRestRoutesHandle(): void
    {
        if (!isset($_POST['g3_flush_rest_routes']) || !current_user_can('manage_options')) {
            return;
        }

        /** @var Router $router */
        $router = $this->container->get('router');
        $result = $router->rebuildCache();

        if (is_wp_error($result)) {
            wp_admin_notice($result->get_error_message(), [
                'type'        => 'error',
                'dismissible' => true,
            ]);
            return;
        }
        wp_admin_notice(Message::flushed(), [
            'type'        => 'success',
            'dismissible' => true,
        ]);
    }
    public function flushCacheHandle(): void
    {
        if (isset($_POST['g3_flush_cache']) && current_user_can('manage_options')) {
            wp_cache_flush();
            wp_admin_notice(Message::flushed(), [
                'type'        => 'success',
                'dismissible' => true,
            ]);
        }
    }
    public function flushOptionHandle()
    {
        if (isset($_POST['g3_flush_options']) && current_user_can('manage_options')) {
            $this->_flushOptions();
            wp_admin_notice(Message::flushed(), [
                'type'        => 'success',
                'dismissible' => true,
            ]);
        }
    }
    public function generateObjectCacheFile(): void
    {
        if (isset($_POST['g3_generate_object_cache']) && current_user_can('manage_options')) {
            $file   = WP_CONTENT_DIR . '/object-cache.php';
            $source = G3_LIB_DIR . '/redis/object-cache.php';
            if (!file_exists($file)) {
                if (@copy($source, $file)) {
                    wp_admin_notice(Message::generated(), [
                        'type'        => 'success',
                        'dismissible' => true,
                    ]);
                } else {
                    wp_admin_notice(sprintf(
                        __('Failed to generate object-cache.php file. Please check file permissions for %s.', 'G3'),
                        esc_html($file)
                    ), [
                        'type'        => 'error',
                        'dismissible' => true,
                    ]);
                }
            } else {
                wp_admin_notice(__('object-cache.php file already exists.', 'G3'), [
                    'type'        => 'error',
                    'dismissible' => true,
                ]);
            }
        }
    }
    public function migrateViewsHandle(): void
    {
        if (isset($_POST['g3_migrate_views']) && current_user_can('manage_options')) {
            /** @var PostService $postService */
            $postService = $this->container->get(PostService::class);
            $key         = defined('G3_OLD_POST_VIEW_META_KEY') ? G3_OLD_POST_VIEW_META_KEY : '';
            $result      = $postService->migrateViewCount($key);
            $success     = $result['success'] ?? false;
            if ($success === false) {
                wp_admin_notice($result['message'] ?? 'No views count data to migrate.', [
                    'type'        => 'error',
                    'dismissible' => true,
                ]);
                return;
            }
            wp_admin_notice($result['message'] ?? 'View count migration completed successfully!', [
                'type'        => 'success',
                'dismissible' => true,
            ]);
        }
    }
    public function wpAutoUpdateHandle(): void
    {
        if (!is_admin()) return;
        $option = $this->settingOptions();
        if (($option['wpAutoUpdate'] ?? '0') === '0') {
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
        $option = $this->settingOptions();

        // Remove theme editor
        if (($option['themeEditor'] ?? '0') === '0') {
            remove_action('admin_menu', '_add_themes_utility_last', 101);
            remove_submenu_page('themes.php', 'site-editor.php');
        }
        // Remove plugin editor
        if (($option['pluginEditor'] ?? '0') === '0')
            remove_submenu_page('plugins.php', 'plugin-editor.php');
        // Remove site health
        if (($option['siteHealth'] ?? '0') === '0')
            remove_submenu_page('tools.php', 'site-health.php');
        // Remove block patterns
        if (($option['blockPatterns'] ?? '0') === '0')
            remove_submenu_page('themes.php', 'site-editor.php?p=/pattern');
        // Remove permalink settings
        if (($option['permalink'] ?? '1') === '0')
            remove_submenu_page('options-general.php', 'options-permalink.php');
    }
    public function themeCustomizeHandle($caps, $cap, $user_id = 0, $args = []): array
    {
        $option = $this->settingOptions();
        if ($cap === 'customize' && ($option['themeCustomize'] ?? '0') === '0') {
            $caps = ['do_not_allow'];
        }
        return $caps;
    }
    public function translationsApiHandle($result, $action, $args)
    {
        $option = $this->settingOptions();
        if (($option['translationsApi'] ?? '0') === '1') {
            return $result;
        }
        return new WP_Error('translations_api_disabled', 'Translations API disabled');
    }
    public function helpLinkHandle(): void
    {
        $option = $this->settingOptions();
        if (($option['helpLink'] ?? '0') === '1') {
            return;
        }
        $screen = get_current_screen();
        $screen->remove_help_tabs();
    }
    public function adminBarMenuHandle($wp_admin_bar): void
    {
        $option = $this->settingOptions();
        if (($option['adminLogo'] ?? '0') === '1') {
            return;
        }
        $wp_admin_bar->remove_node('wp-logo');
    }
    public function adminBarHandle(): bool
    {
        $option = $this->settingOptions();
        return ($option['adminBar'] ?? '0') === '1';
    }
    public function emojiHandle(): void
    {
        $option = $this->settingOptions();
        if (($option['emoji'] ?? '0') === '1') return;
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
        $option = $this->settingOptions();
        if (($option['wpHead'] ?? '1') === '0') return;
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
        $option = $this->settingOptions();
        if (($option['gutenberg'] ?? '0') === '0') {
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
        $option = $this->settingOptions();
        if (($option['gutenberg'] ?? '0') === '0') {
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
                            "message"  => Message::verified() . ' Redirecting...',
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
                                    esc_url('https://www.jealer.com/G3-Web/license/')
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
                require_once G3_TEMPLATE_DIR . "/developer/w.php";
            },
            1,
        );
    }
    private function adminTitleHandle()
    {
        $option = $this->settingOptions();
        if (($option['adminTitle'] ?? '0') === '1') {
            return;
        }
        add_filter('admin_title', function ($admin_title, $title) {
            return $title . ' - ' . get_bloginfo('name');
        }, 10, 2);
    }
    private function thanksHandle()
    {
        $option = $this->settingOptions();
        $text   = (string) ($option['footerThanks'] ?? G3_NAME);
        if (trim($text) === '') {
            return;
        }
        add_filter('admin_footer_text', fn() => $text);
    }
    private function upgradeHandle()
    {
        $option = $this->settingOptions();
        $text   = (string) ($option['footerUpgrade'] ?? G3_VERSION);
        if (trim($text) === '') {
            return;
        }
        add_filter('update_footer', fn() => $text, 999);
    }
    private function toolsPageHandle()
    {
        $option = $this->settingOptions();
        if (($option['toolsPage'] ?? '1') === '1') return;
        remove_menu_page('tools.php');
    }
    private function pluginsPageHandle()
    {
        $option = $this->settingOptions();
        if (($option['pluginsPage'] ?? '1') === '1') return;
        add_action('admin_menu', function () {
            remove_menu_page('plugins.php');
        }, 999);
    }
    private function themeInstallHandle()
    {
        $option = $this->settingOptions();
        if (($option['themeInstall'] ?? '1') === '1') return;
        if (strpos($_SERVER['REQUEST_URI'], 'themes.php') !== false) {
            echo '<style>a.hide-if-no-js.page-title-action{display:none}</style>';
        }
    }
    protected function dashboard(): void
    {
        $option = $this->settingOptions();
        $v      = $option['dashboard'] ?? ['0', '1', '2', '3', '4', '5'];
        $v      = is_array($v) ? $v : [];
        if (in_array('0', $v, true)) {
            remove_action('welcome_panel', 'wp_welcome_panel');
        }
        if (in_array('1', $v, true)) {
            remove_meta_box('dashboard_site_health', 'dashboard', 'normal');
        }
        if (in_array('2', $v, true)) {
            remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
        }
        if (in_array('3', $v, true)) {
            remove_meta_box('dashboard_activity', 'dashboard', 'normal');
        }
        if (in_array('4', $v, true)) {
            remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
        }
        if (in_array('5', $v, true)) {
            remove_meta_box('dashboard_primary', 'dashboard', 'side');
        }
    }
    public function ajax(): void
    {
        if (defined('G3_HIDE_DEVELOPER_MODE') && constant('G3_HIDE_DEVELOPER_MODE')) {
            return;
        }
        add_action('wp_ajax_g3_flush_options', [$this, '_ajaxFlushOptions']);
    }
    public function _ajaxFlushOptions(): void
    {
        if (current_user_can('manage_options') && is_admin()) {
            $this->_flushOptions();
            Response::ajaxSuccess(__('Refreshed', 'G3'));
        } else {
            Response::ajaxForbidden();
        }
    }
}
