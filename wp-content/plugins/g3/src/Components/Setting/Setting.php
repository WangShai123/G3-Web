<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Utilities\Container;
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Services\PostService;
use JEALER\G3\Services\SystemService;

class Setting extends Components {
    public array $option = [];
    public array $seo = [];
    public array $rss = [];

    #[\Override]
    protected function options(): void
    {
        $siteName     = get_bloginfo('name');
        $option       = Option::get(SystemService::OPTION_KEY, [
            'sad'          => '0',
            'avatar'       => G3_IMG_URL . '/avatar.png',
            'cover'        => G3_IMG_URL . '/cover-placeholder.png',
            'icp'          => '',
            'headerCode'   => '',
            'footerCode'   => '',
            'links'        => '',
            'redirectLink' => '0',
        ]);
        $this->option = Option::cache(SystemService::OPTION_KEY, $option);
        $seo          = Option::get(SystemService::SEO_OPTION_KEY, [
            'seo'      => '1',
            'keywords' => "{$siteName},G3 Web,G3 System,JEALER",
        ]);
        $this->seo    = Option::cache(SystemService::SEO_OPTION_KEY, $seo);
        $rss          = Option::get(SystemService::RSS_OPTION_KEY, [
            'rss'  => '1',
            'rss1' => get_bloginfo('rss_url'),
            'rss2' => get_bloginfo('rss2_url'),
            'atom' => get_bloginfo('atom_url'),
        ]);
        $this->rss    = Option::cache(SystemService::RSS_OPTION_KEY, $rss);
    }
    #[\Override]
    protected function init(): void
    {
        $this->registerRedirectLink();
        add_action('wp_head', [$this, 'sadHandle']);
        add_action('wp_head', [$this, 'headerCodeHandle']);
        add_action('wp_footer', [$this, 'footerCodeHandle']);
    }
    #[\Override]
    protected function admin(): void
    {
        $this->settings();
        $this->permalink();
        if (isset($this->seo['seo']) && $this->seo['seo'] === '1') {
            // SEO: add field in edit form for post
            add_action('add_meta_boxes', [$this, 'initPostbox'], 10, 2);
            add_action('save_post', [$this, 'updateSeoPostbox'], 10, 2);
            // SEO: add field in add form for all taxonomies
            $taxonomies = get_taxonomies();
            foreach ($taxonomies as $taxonomy) {
                add_action($taxonomy . '_add_form_fields', [$this, 'addKeywordsField']);
                add_action($taxonomy . '_edit_form_fields', [$this, 'editKeywordsField']);
                add_action('created_' . $taxonomy, [$this, 'updateKeywordsField']);
                add_action('edited_' . $taxonomy, [$this, 'updateKeywordsField']);
            }
        }
        $this->pluginAction();
    }
    #[\Override]
    protected function adminMenu(): void
    {
        $this->menu();
    }
    #[\Override]
    protected function system(): void
    {
        add_action('wp_head', [$this, 'redirectLinkHandle']);
        $this->rssHandle();
    }
    private function menu(): void
    {
        add_menu_page(
            __('Operation Settings', 'G3'),
            __('Operation Settings', 'G3'),
            "manage_options",
            "g3-settings",
            '__return_false',
            'dashicons-share',
            '79'
        );
        add_submenu_page(
            'g3-settings',
            __('General', 'G3'),
            __('General', 'G3'),
            'manage_options',
            'g3-settings',
            [$this, 'render'],
            1
        );
    }
    public function render(): void
    {
        $tabs = [
            'general' => __('General', 'G3'),
            'seo'     => 'SEO',
            'rss'     => 'RSS',
            'sitemap' => __('SiteMap', 'G3'),
        ];
        echo '<div class="wrap"><h1>' . __('General', 'G3') . '</h1>';
        Container::tab('Setting', 'general', $tabs);
        echo '</div>';
    }
    private function settings(): void
    {
        add_settings_section(
            'general',
            '',
            '__return_false',
            'g3-settings'
        );
        register_setting('general', SystemService::OPTION_KEY);
        Container::settingFields('g3-settings', 'general', [
            [
                'id'       => 'sad',
                'title'    => __('Sad Mod', 'G3'),
                'callback' => function () {
                    echo Container::enable(
                        SystemService::OPTION_KEY,
                        $this->option,
                        'sad',
                        __('Sad Mod', 'G3'),
                        __('After enabling it, the entire website will be immersed in a mournful mode with only black, white, and gray colors.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'sad',
                ]
            ],
            [
                'id'       => 'avatar',
                'title'    => __('Default Avatar', 'G3'),
                'callback' => function () {
                    echo Container::imageInput(
                        SystemService::OPTION_KEY,
                        $this->option,
                        'avatar',
                        __('Default Avatar', 'G3'),
                        __('Modify the default system avatar.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'avatar',
                ]
            ],
            [
                'id'       => 'cover',
                'title'    => __('Default Cover', 'G3'),
                'callback' => function () {
                    echo Container::imageInput(
                        SystemService::OPTION_KEY,
                        $this->option,
                        'cover',
                        __('Default Cover', 'G3'),
                        __('Modify the default system cover.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'cover',
                ]
            ],
            [
                'id'       => 'icp',
                'title'    => __('ICP Code', 'G3'),
                'callback' => function () {
                    echo Container::input(
                        SystemService::OPTION_KEY,
                        $this->option,
                        'icp',
                        __('ICP Code', 'G3'),
                        __('If you are conducting online business in mainland China, please be sure to input the ICP filing number issued by the Ministry of Industry and Information Technology of the People\'s Republic of China.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'icp',
                ]
            ],
            [
                'id'       => 'headerCode',
                'title'    => __('Header Code', 'G3'),
                'callback' => function () {
                    echo Container::textarea(
                        SystemService::OPTION_KEY,
                        $this->option,
                        'headerCode',
                        __('Header Code', 'G3'),
                        __('Enter the HTML, CSS, and JS code that you want to add to the website\'s header here.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'headerCode',
                ]
            ],
            [
                'id'       => 'footerCode',
                'title'    => __('Footer Code', 'G3'),
                'callback' => function () {
                    echo Container::textarea(
                        SystemService::OPTION_KEY,
                        $this->option,
                        'footerCode',
                        __('Footer Code', 'G3'),
                        __('Enter the HTML, CSS, and JS code that you want to add to the website\'s footer here.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'footerCode',
                ]
            ],
            [
                'id'       => 'links',
                'title'    => __('Links', 'G3'),
                'callback' => function () {
                    echo Container::textarea(
                        SystemService::OPTION_KEY,
                        $this->option,
                        'links',
                        __('Links', 'G3'),
                        __('Enter the code for the friend links you want to add here, or any HTML, CSS, JS code that you want to customize output.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'links',
                ]
            ],
            [
                'id'       => 'redirectLink',
                'title'    => __('Redirect Link', 'G3'),
                'callback' => function () {
                    echo Container::enable(
                        SystemService::OPTION_KEY,
                        $this->option,
                        'redirectLink',
                        __('Redirect Link', 'G3'),
                        __('After enabling it, all outbound links will be intercepted by the system and redirected to the link middle page instead of the original target url.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'redirectLink',
                ]
            ]
        ]);

        // seo
        add_settings_section(
            'seo',
            null,
            '__return_false',
            'g3-settings&tab=seo'
        );
        register_setting('seo', SystemService::SEO_OPTION_KEY);
        Container::settingFields('g3-settings&tab=seo', 'seo', [
            [
                'id'       => 'seo',
                'title'    => 'SEO',
                'callback' => function () {
                    echo Container::enable(
                        SystemService::SEO_OPTION_KEY,
                        $this->seo,
                        'seo',
                        'SEO',
                        __('After enabling it, you can add custom SEO data for each page.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'seo'
                ]
            ],
            [
                'id'       => 'keywords',
                'title'    => __('HomePage Keywords', 'G3'),
                'callback' => function () {
                    echo Container::input(
                        SystemService::SEO_OPTION_KEY,
                        $this->seo,
                        'keywords',
                        __('HomePage Keywords', 'G3'),
                        '',
                        'text',
                        'regular-text'
                    );
                },
                'args'     => [
                    'label_for' => 'keywords'
                ]
            ]
        ]);

        // rss
        add_settings_section(
            'rss',
            null,
            '__return_false',
            'g3-settings&tab=rss'
        );
        register_setting('rss', SystemService::RSS_OPTION_KEY);
        Container::settingFields('g3-settings&tab=rss', 'rss', [
            [
                'id'       => 'rss',
                'title'    => 'RSS',
                'callback' => function () {
                    echo Container::enable(
                        SystemService::RSS_OPTION_KEY,
                        $this->rss,
                        'rss',
                        'RSS'
                    );
                },
                'args'     => [
                    'label_for' => 'rss'
                ]
            ],
            [
                'id'       => 'rss1',
                'title'    => __('RSS URL', 'G3'),
                'callback' => function () {
                    $url = $this->rss['rss1'] ?? get_bloginfo('rss_url');
                    echo '<input name="' . SystemService::RSS_OPTION_KEY . '[rss1]" type="url" id="rss1" value="' . esc_url($url) . '" class="regular-text" readonly="readonly">';
                },
                'args'     => [
                    'label_for' => 'rss1'
                ]
            ],
            [
                'id'       => 'rss2',
                'title'    => __('RSS2 URL', 'G3'),
                'callback' => function () {
                    $url = $this->rss['rss2'] ?? get_bloginfo('rss2_url');
                    echo '<input name="' . SystemService::RSS_OPTION_KEY . '[rss2]" type="url" id="rss2" value="' . esc_url($url) . '" class="regular-text" readonly="readonly">';
                },
                'args'     => [
                    'label_for' => 'rss2'
                ]
            ],
            [
                'id'       => 'atom',
                'title'    => __('Atom URL', 'G3'),
                'callback' => function () {
                    $url = $this->rss['atom'] ?? get_bloginfo('atom_url');
                    echo '<input name="' . SystemService::RSS_OPTION_KEY . '[atom]" type="url" id="atom" value="' . esc_url($url) . '" class="regular-text" readonly="readonly">';
                },
                'args'     => [
                    'label_for' => 'atom'
                ]
            ]
        ]);

        // sitemap
        add_settings_section(
            'sitemap',
            null,
            '__return_false',
            'g3-settings&tab=sitemap'
        );
        register_setting(
            'sitemap',
            null
        );
        add_settings_field(
            'sitemap',
            'WP SiteMap',
            function () {
                $sitemap = home_url('wp-sitemap.xml');
                ?>
            <fieldset>
                <legend class="screen-reader-text"><span>WP sitemap</span></legend>
                <p><a href="<?php echo $sitemap; ?>" target="_blank"><?php echo $sitemap; ?></a></p>
                <p class="description">
                    <?php _e('Starting from version 5.5 of WordPress, the functionality of multi-level and multi-page XML sitemaps has been loaded as a default core feature.', 'G3'); ?>
                </p>
            </fieldset>
            <?php
            },
            'g3-settings&tab=sitemap',
            'sitemap'
        );
    }

    public function sadHandle(): void
    {
        if (!isset($this->option['sad']) || $this->option['sad'] !== '1' || is_admin()) {
            return;
        }
        echo '<style>body{-webkit-filter: grayscale(100%);-ms-filter: progid:DXImageTransform.Microsoft.BasicImage(grayscale=1);filter:grayscale(100%);}</style>';
    }
    public function headerCodeHandle(): void
    {
        if (!isset($this->option['headerCode']) || empty($this->option['headerCode'])) {
            return;
        }
        echo stripslashes($this->option['headerCode']);
    }
    public function footerCodeHandle(): void
    {
        if (!isset($this->option['footerCode']) || empty($this->option['footerCode'])) {
            return;
        }
        echo stripslashes($this->option['footerCode']);
    }
    public function redirectLinkHandle(): void
    {
        if (!isset($this->option['redirectLink']) || $this->option['redirectLink'] !== '1') {
            return;
        }
        Frontend::loadScript('redirect.link');
    }
    public function registerRedirectLink()
    {
        // register rewrite rule
        add_rewrite_rule(
            '^redirect/go/([^/]+)/?$',
            'index.php?redirect_link=$matches[1]',
            'top'
        );

        // add query var
        add_filter('query_vars', function ($vars) {
            $vars[] = 'redirect_link';
            return $vars;
        });

        // handle template include
        add_filter('template_include', function ($template) {
            global $wp_query;
            if (isset($wp_query->query_vars['template_include']) && $wp_query->query_vars['template_include'] === 'redirect-link') {
                $defaultTemplate = WP_PLUGIN_DIR . '/g3/templates/redirect-link.php';
                $userTemplate    = get_stylesheet_directory() . '/templates/redirect-link.php';
                $template        = file_exists($userTemplate) ? $userTemplate : $defaultTemplate;
            }
            return $template;
        });
    }

    /**
     * SEO: init postbox
     * @return void
     * @param  string $post_type The post type.
     * @since 1.0.0
     * @author Wang Shai
     */
    public function initPostbox()
    {
        $post_types = get_post_types(['public' => true], 'names', 'and');
        foreach ($post_types as $post_type) {
            add_meta_box(
                'post-seo-meta',
                __('SEO & Social Media', 'G3'),
                [$this, 'renderPostbox'],
                $post_type,
                'normal',
                'high'
            );
        }
    }
    /**
     * SEO: update postbox
     * @param  int $post_id The post ID.
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public function updateSeoPostbox($post_id): void
    {
        if (!isset($_POST['seoKeywords']) || !isset($_POST['seoDescription']) || !isset($_POST['seoTitle'])) {
            return;
        }
        $post_keyword   = $_POST['seoKeywords'];
        $post_des       = $_POST['seoDescription'];
        $post_seo_title = $_POST['seoTitle'];
        update_post_meta($post_id, PostService::$seoKeywordsKey, $post_keyword);
        update_post_meta($post_id, PostService::$seoDescriptionKey, $post_des);
        update_post_meta($post_id, PostService::$seoTitleKey, $post_seo_title);
    }
    /**
     * SEO: render postbox
     * @param  object $post The post object.
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public function renderPostbox($post): void
    {
        $id          = $post->ID ?? 0;
        $title       = get_post_meta($id, PostService::$seoTitleKey, true);
        $description = get_post_meta($id, PostService::$seoDescriptionKey, true);
        $keywords    = get_post_meta($id, PostService::$seoKeywordsKey, true);

        echo '<div class="j-input-group is-2">';
        echo '<div class="input-group-item"><label for="seoTitle" class="">' . __('Title', 'G3') . '</label><input type="text" id="seoTitle" name="seoTitle" value="' . $title . '"></div>';
        echo '<div class="input-group-item"><label for="seoKeywords" class="">' . __('Keywords', 'G3') . '</label><input placeholder="' . __('Separate tags with commas.', 'G3') . '" type="text" id="seoKeywords" name="seoKeywords" value="' . $keywords . '"></div></div>';
        echo '<div class="j-input-group"><div class="input-group-item"><label for="seoDescription" class="">' . __('Description', 'G3') . '</label><input type="text" id="seoDescription" name="seoDescription" value="' . $description . '"></div></div>';

        /**
         * Custom Action: add custom fields in post SEO metabox
         * Action: g3_seo_action
         * 
         * @param object $post The post object.
         * @since 1.0.0
         * @author Wang Shai
         */
        do_action('g3_seo_action', $post);
    }
    /**
     * SEO: add keywords field in add & edit form for all taxonomies
     * @since 1.0.0
     * @author Wang Shai
     */
    public function addKeywordsField(): void
    {
        echo '<div class="form-field"><label for="seoKeywords">';
        echo 'SEO ' . __('Keywords', 'G3') . '</label><input name="seoKeywords" id="seoKeywords" type="text" value="" size="40"><p>';
        echo __('Separate tags with commas.', 'G3') . '</p></div>';
    }
    public function editKeywordsField($tag): void
    {
        echo '<tr class="form-field"><th scope="row"><label for="seoKeywords">';
        echo 'SEO ' . __('Keywords', 'G3');
        echo '</label></th><td><input name="seoKeywords" id="seoKeywords" type="text" value="';
        echo esc_attr(get_term_meta($tag->term_id, PostService::$seoKeywordsKey, true));
        echo '" size="40"/><p class="description">' . __('Separate tags with commas.', 'G3') . '</p></td></tr>';
    }
    public function updateKeywordsField($term_id)
    {
        if (isset($_POST['seoKeywords'])) {
            if (!current_user_can('manage_categories')) {
                return $term_id;
            }
            $v = $_POST['seoKeywords'];
            update_term_meta($term_id, PostService::$seoKeywordsKey, $v);
        }
        return true;
    }

    /**
     * rss handle
     * @return void
     * @since 1.0.0
     * @author Wang Shai
     */
    public function rssHandle(): void
    {
        if (!isset($this->rss['rss']) || $this->rss['rss'] !== '0') {
            return;
        }

        // remove rss feed links in header
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);
        remove_action('wp_head', 'rsd_link');

        // disable rss feed
        add_action('do_feed', [$this, 'disableRss'], 0, 1);
        add_action('do_feed_rdf', [$this, 'disableRss'], 0, 1);
        add_action('do_feed_rss', [$this, 'disableRss'], 0, 1);
        add_action('do_feed_rss2', [$this, 'disableRss'], 0, 1);
        add_action('do_feed_atom', [$this, 'disableRss'], 0, 1);
        add_action('do_feed_rss2_comments', [$this, 'disableRss'], 0, 1);
        add_action('do_feed_atom_comments', [$this, 'disableRss'], 0, 1);

        remove_theme_support('automatic-feed-links');

        add_filter('feed_links_show_posts_feed', '__return_false', 999);
        add_filter('feed_links_show_comments_feed', '__return_false', 999);
    }
    public function disableRss()
    {
        wp_die(
            __('Feed Disabled', 'G3'),
            __('Feed Disabled', 'G3'),
            [
                'response' => 404,
                'code'     => 404,
                'charset'  => 'UTF-8'
            ]
        );
    }
    private function permalink(): void
    {
        if (get_option('permalink_structure') === '/%postname%/') return;

        update_option('permalink_structure', '/%postname%/');
        flush_rewrite_rules();
    }

    private function pluginAction()
    {
        add_filter('plugin_row_meta', function ($links, $file) {
            if ($file !== 'g3/loader.php') return $links;
            $links[] = '<a href="https://www.jealer.com/g3-web/sponsor/" target="_blank"><span class="dashicons dashicons-star-filled" aria-hidden="true" style="font-size:14px;line-height:1.4"></span>' . __('Sponsor', 'G3') . '</a>';
            $links[] = '<a href="https://www.jealer.com/documents" target="_blank"><span class="dashicons dashicons-book-alt" aria-hidden="true" style="font-size:14px;line-height:1.4"></span>' . __('Documents', 'G3') . '</a>';
            $links[] = '<a href="https://www.jealer.com/courses" target="_blank"><span class="dashicons dashicons-video-alt" aria-hidden="true" style="font-size:14px;line-height:1.4"></span>' . __('Online Courses', 'G3') . '</a>';
            return $links;
        }, 10, 2);
        add_filter('plugin_action_links_g3/loader.php', function ($links) {
            $links[] = '<a href="index.php?page=g3-welcome">' . __('Tip', 'G3') . '</a>';
            return $links;
        });
    }
}