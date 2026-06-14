<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use JEALER\G3\Utilities\Context;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Services\PostService;
use JEALER\G3\Services\SystemService;
use Override;

class Setting extends Components {

    public array $option = [];

    public array $seo = [];

    public array $rss = [];

    public array $llm = [];

    private array $config = [];

    #[Override]
    protected function options(): void
    {
        $siteName     = get_bloginfo('name');
        $this->option = Option::get(SystemService::OPTION_KEY, [
            'sad'          => '0',
            'avatar'       => G3_IMG_URL . '/avatar.png',
            'cover'        => G3_IMG_URL . '/cover-placeholder.png',
            'icp'          => '',
            'headerCode'   => '',
            'footerCode'   => '',
            'customCode'   => '',
            'links'        => '1',
            'redirectLink' => '1',
        ]);
        $this->seo    = Option::get(SystemService::SEO_OPTION_KEY, [
            'seo'      => '1',
            'keywords' => "{$siteName},G3 Web,G3 System,JEALER",
        ]);
        $this->rss    = Option::get(SystemService::RSS_OPTION_KEY, [
            'rss'  => '1',
            'rss1' => get_bloginfo('rss_url'),
            'rss2' => get_bloginfo('rss2_url'),
            'atom' => get_bloginfo('atom_url'),
        ], false);
        $this->llm    = Option::get(SystemService::LLM_OPTION_KEY, [
            'llm'          => '1',
            'postsPerType' => 2000,
        ], false);
    }

    #[Override]
    protected function system(): void
    {
        add_filter('g3_filter_html_class', [$this, 'initHtmlClass']);
        add_action('body_class', [$this, 'initBodyClass']);

        $this->redirectLinkHandle();
        $this->rssHandle();
    }

    #[Override]
    protected function form(): void
    {
        Frontend::loadStyle('jui');
        Frontend::loadScript('jui');
        Frontend::loadScript('g3.admin');

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
        add_filter('admin_body_class', function ($classes) {
            return $classes . ' light j-theme-indigo j-shadow-none j-radius-sm j-font-sm';
        });

        if (!isset($_REQUEST['page']) || $_REQUEST['page'] !== 'g3-settings') return;
        $this->option = Option::cache(SystemService::OPTION_KEY, $this->option);
        $this->seo    = Option::cache(SystemService::SEO_OPTION_KEY, $this->seo);
        $this->rss    = Option::cache(SystemService::RSS_OPTION_KEY, $this->rss);
        $this->llm    = Option::cache(SystemService::LLM_OPTION_KEY, $this->llm);
    }

    #[Override]
    protected function init(): void
    {
        add_action('wp_head', [$this, 'sadHandle']);
        add_action('wp_head', [$this, 'headerCodeHandle']);
        add_action('wp_footer', [$this, 'footerCodeHandle']);
    }

    #[Override]
    protected function adminMenu(): void
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
            __('General'),
            __('General'),
            'manage_options',
            'g3-settings',
            [$this, 'render'],
            1
        );
    }

    protected function end(): void
    {
        $this->linksHandle();
    }

    public function render(): void
    {
        $tabs = [
            'general' => __('General'),
            'seo'     => 'SEO',
            'rss'     => 'RSS',
            'llm'     => 'LLM',
            'sitemap' => __('SiteMap', 'G3'),
        ];
        echo '<div class="wrap"><h1>' . __('General') . '</h1>';
        Element::tab('Setting', 'general', $tabs);
        echo '</div>';
    }

    #[Override]
    protected function settings(): void
    {
        add_settings_section(
            'general',
            '',
            '__return_false',
            'g3-settings'
        );
        register_setting('general', SystemService::OPTION_KEY);
        Element::settingFields('g3-settings', 'general', [
            [
                'id'       => 'sad',
                'title'    => __('Sad Mod', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        SystemService::OPTION_KEY,
                        $this->option,
                        'sad',
                        __('Sad Mod', 'G3'),
                        __('The entire website will be immersed in a mournful mode with only black, white, and gray colors.', 'G3')
                    );
                }
            ],
            [
                'id'       => 'avatar',
                'title'    => __('Default Avatar', 'G3'),
                'callback' => function () {
                    echo Element::imageInput(
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
                    echo Element::imageInput(
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
                    echo Element::input(
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
                    echo Element::textarea(
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
                    echo Element::textarea(
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
                'id'       => 'customCode',
                'title'    => __('Custom Code', 'G3'),
                'callback' => function () {
                    echo Element::textarea(
                        SystemService::OPTION_KEY,
                        $this->option,
                        'customCode',
                        __('Custom Code', 'G3'),
                        __('Enter any HTML, CSS, JS code here that you want to customize output.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'customCode',
                ]
            ],
            [
                'id'       => 'links',
                'title'    => __('Links'),
                'callback' => function () {
                    echo Element::switch(
                        SystemService::OPTION_KEY,
                        $this->option,
                        'links',
                        __('Links'),
                        __('The links feature helps you manage your friendship links.', 'G3')
                    );
                }
            ],
            [
                'id'       => 'redirectLink',
                'title'    => __('Redirect Link', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        SystemService::OPTION_KEY,
                        $this->option,
                        'redirectLink',
                        __('Redirect Link', 'G3'),
                        __('All outbound links will be intercepted by the system and redirected to the link middle page instead of the original target url.', 'G3')
                    );
                }
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
        Element::settingFields('g3-settings&tab=seo', 'seo', [
            [
                'id'       => 'seo',
                'title'    => 'SEO',
                'callback' => function () {
                    echo Element::switch(
                        SystemService::SEO_OPTION_KEY,
                        $this->seo,
                        'seo',
                        'SEO',
                        __('You can add custom SEO data for each page.', 'G3')
                    );
                }
            ],
            [
                'id'       => 'keywords',
                'title'    => __('HomePage Keywords', 'G3'),
                'callback' => function () {
                    echo Element::input(
                        SystemService::SEO_OPTION_KEY,
                        $this->seo,
                        'keywords',
                        __('HomePage Keywords', 'G3'),
                        __('Separate tags with commas'),
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
        Element::settingFields('g3-settings&tab=rss', 'rss', [
            [
                'id'       => 'rss',
                'title'    => 'RSS',
                'callback' => function () {
                    echo Element::switch(
                        SystemService::RSS_OPTION_KEY,
                        $this->rss,
                        'rss',
                        'RSS'
                    );
                }
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

        // llm
        add_settings_section(
            'llm',
            null,
            '__return_false',
            'g3-settings&tab=llm'
        );
        register_setting('llm', SystemService::LLM_OPTION_KEY);
        Element::settingFields('g3-settings&tab=llm', 'llm', [
            [
                'id'       => 'llm',
                'title'    => 'LLM',
                'callback' => function () {
                    echo Element::switch(
                        SystemService::LLM_OPTION_KEY,
                        $this->llm,
                        'llm',
                        'LLM',
                        sprintf(
                            '%s: <a href="%s" target="_blank">%s</a><br>%s: <a href="%s" target="_blank">%s</a>',
                            __('Real-time data', 'G3'),
                            site_url('/llm/endpoint'),
                            site_url('/llm/endpoint'),
                            __('Cache Data', 'G3'),
                            site_url('/llms.txt'),
                            site_url('/llms.txt')
                        )
                    );
                }
            ],
            [
                'id'       => 'postsPerType',
                'title'    => __('Posts Per Type', 'G3'),
                'callback' => function () {
                    echo Element::input(
                        SystemService::LLM_OPTION_KEY,
                        $this->llm,
                        'postsPerType',
                        __('Posts Per Type', 'G3'),
                        __('The number of posts to be generated for each post type.<br>Default: <code>2000</code>.', 'G3'),
                        'number',
                    );
                }
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
        add_action('wp_footer', function () {
            Frontend::loadScript('g3.redirect.link');
        });
        // Modify content url while saving
        add_action('save_post', [$this, 'modifyContentUrl'], 10, 3);
    }
    public function modifyContentUrl($postId, $post, $update)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if ($post->post_status !== 'publish') {
            return;
        }
        $content = $post->post_content;
        $siteUrl = home_url();
        $pattern = '/<a\s+(?:[^>]*?\s+)?href=(["\'])(.*?)\1/i';

        $newContent = preg_replace_callback($pattern, function ($matches) use ($siteUrl) {
            $fullMatch = $matches[0];
            $quote     = $matches[1];
            $url       = $matches[2];

            // check if external
            if (
                !empty($url) &&
                filter_var($url, FILTER_VALIDATE_URL) &&
                !str_starts_with($url, $siteUrl) &&
                !str_starts_with($url, 'http://' . parse_url($siteUrl, PHP_URL_HOST)) &&
                !str_starts_with($url, 'https://' . parse_url($siteUrl, PHP_URL_HOST))
            ) {
                // Replace external links with internal redirect links
                $redirectUrl = $siteUrl . '/redirect/go/' . urlencode($url);
                return str_replace($url, $redirectUrl, $fullMatch);
            }

            return $fullMatch;
        }, $content);

        // update if needed
        if ($newContent !== $content) {
            wp_update_post([
                'ID'           => $postId,
                'post_content' => $newContent,
            ], true);
        }
    }
    public static function onRedirect(): bool
    {
        $v = Context::get(SystemService::OPTION_KEY)['redirectLink'] ?? '1';
        return $v === '1';
    }

    public function initPostbox(): void
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

    public function updateSeoPostbox(int $post_id): void
    {
        if (!isset($_POST['seoKeywords']) || !isset($_POST['seoDescription']) || !isset($_POST['seoTitle'])) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        update_post_meta($post_id, PostService::TITLE_KEY, $_POST['seoTitle']);
        update_post_meta($post_id, PostService::DESCRIPTION_KEY, $_POST['seoDescription']);
        update_post_meta($post_id, PostService::KEYWORDS_KEY, $_POST['seoKeywords']);
    }

    public function renderPostbox(object $post): void
    {
        $id          = $post->ID ?? 0;
        $title       = get_post_meta($id, PostService::TITLE_KEY, true);
        $description = get_post_meta($id, PostService::DESCRIPTION_KEY, true);
        $keywords    = get_post_meta($id, PostService::KEYWORDS_KEY, true);

        echo '<div class="flex-container">';
        echo '<div class="flex-col-1 flex-col-xl-3 flex-col-lg-2 flex-col-md-1 flex-col-sm-1"><div class="input-group"><div class="el-addon"><div class="is-text">' . __('Title', 'G3') . '</div></div><input class="j-input" type="text" id="seoTitle" name="seoTitle" value="' . $title . '"></div></div>';
        echo '<div class="flex-col"><div class="input-group"><div class="el-addon"><div class="is-text">' . __('Keywords', 'G3') . '</div></div><input class="j-input" placeholder="' . __('Separate tags with commas.', 'G3') . '" type="text" id="seoKeywords" name="seoKeywords" value="' . $keywords . '"></div></div>';
        echo '</div>';
        echo '<div class="flex-container">';
        echo '<div class="flex-col-1"><div class="input-group"><div class="el-addon"><div class="is-text">' . __('Description', 'G3') . '</div></div><input class="j-input" type="text" id="seoDescription" name="seoDescription" value="' . $description . '"></div></div>';
        echo '</div>';

        /**
         * Custom Action: add custom fields in post SEO metabox
         * Action: g3_action_seo
         * 
         * @param object $post The post object.
         */
        do_action('g3_action_seo', $post);
    }

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
        echo esc_attr(get_term_meta($tag->term_id, PostService::KEYWORDS_KEY, true));
        echo '" size="40"/><p class="description">' . __('Separate tags with commas.', 'G3') . '</p></td></tr>';
    }

    public function updateKeywordsField(int $term_id): bool|int
    {
        if (isset($_POST['seoKeywords'])) {
            if (!current_user_can('manage_categories')) {
                return $term_id;
            }
            $v = $_POST['seoKeywords'];
            update_term_meta($term_id, PostService::KEYWORDS_KEY, $v);
        }
        return true;
    }

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

    public function disableRss(): void
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
        if (Context::get('permalink_structure') === '/%postname%/') return;

        update_option('permalink_structure', '/%postname%/');
        flush_rewrite_rules();
    }

    private function pluginAction(): void
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

    private function linksHandle(): void
    {
        if (!SystemService::hasLinkService()) {
            return;
        }
        add_filter('pre_option_link_manager_enabled', '__return_true');
    }

    public function initBodyClass(array $classes): array
    {
        $classes[] = 'jui bg-background text-foreground';
        return $classes;
    }

    public function initHtmlClass(array $classes): array
    {
        if (!is_admin()) {
            // $classes[] = 'jui';
            $classes[] = $this->getConfigString();
        }
        return $classes;
    }

    public static function onLLM(): bool
    {
        $v = Context::get(SystemService::LLM_OPTION_KEY)['llm'] ?? '1';
        return $v === '1';
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
