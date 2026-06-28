<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use JEALER\G3\Core\Admin\Panel;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Services\LLMService;
use JEALER\G3\Services\SitemapService;
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Services\PostService;
use JEALER\G3\Services\SystemService;
use JEALER\G3\Utilities\Response;
use JEALER\G3\Utilities\State;
use Override;

class Setting extends Components {
    public array $option  = [];
    public array $seo     = [];
    public array $rss     = [];
    public array $llm     = [];
    public array $sitemap = [];
    protected function state(): array
    {
        return [
            'option'  => $this->optionState(SystemService::OPTION_KEY, [
                'sad'          => '0',
                'avatar'       => G3_IMG_URL . '/avatar.png',
                'cover'        => G3_IMG_URL . '/cover-placeholder.png',
                'icp'          => '',
                'headerCode'   => '',
                'footerCode'   => '',
                'customCode'   => '',
                'links'        => '1',
                'redirectLink' => '1',
            ]),
            'seo'     => $this->optionState(SystemService::SEO_OPTION_KEY, [
                'seo'      => '1',
                'keywords' => "G3 Web,G3 System,JEALER",
            ]),
            'rss'     => $this->optionState(SystemService::RSS_OPTION_KEY, [
                'rss'  => '1',
                'rss1' => '',
                'rss2' => '',
                'atom' => '',
            ])->autoload(false),
            'llm'     => $this->optionState(SystemService::LLM_OPTION_KEY, [
                'llm'          => '1',
                'postsPerType' => 2000,
                'manual'       => '1',
            ])->autoload(false),
            'sitemap' => $this->memoryState(),
        ];
    }
    protected function system(): void
    {
        $this->redirectLinkHandle();
        $this->rssHandle();
    }
    protected function form(): void
    {
        Frontend::css('jui');
        Frontend::umd('jui');
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
    }
    protected function init(): void
    {
        add_action('wp_head', [$this, 'sadHandle']);
        add_action('wp_head', [$this, 'headerCodeHandle']);
        add_action('wp_footer', [$this, 'footerCodeHandle']);
    }
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
    protected function adminPanels(): array
    {
        return [
            $this->panel('g3-settings', __('General'))
                ->tab('general', __('General'), 'option')
                ->switch('sad', __('Sad Mod', 'G3'), __('The entire website will be immersed in a mournful mode with only black, white, and gray colors.', 'G3'))
                ->image('avatar', __('Default Avatar', 'G3'), __('Modify the default system avatar.', 'G3'))
                ->image('cover', __('Default Cover', 'G3'), __('Modify the default system cover.', 'G3'))
                ->input('icp', __('ICP Code', 'G3'), __('If you are conducting online business in mainland China, please be sure to input the ICP filing number issued by the Ministry of Industry and Information Technology of the People\'s Republic of China.', 'G3'))
                ->textarea('headerCode', __('Header Code', 'G3'), __('Enter the HTML, CSS, and JS code that you want to add to the website\'s header here.', 'G3'))
                ->textarea('footerCode', __('Footer Code', 'G3'), __('Enter the HTML, CSS, and JS code that you want to add to the website\'s footer here.', 'G3'))
                ->textarea('customCode', __('Custom Code', 'G3'), __('Enter any HTML, CSS, JS code here that you want to customize output.', 'G3'))
                ->switch('links', __('Links'), __('The links feature helps you manage your friendship links.', 'G3'))
                ->switch('redirectLink', __('Redirect Link', 'G3'), __('All outbound links will be intercepted by the system and redirected to the link middle page instead of the original target url.', 'G3'))
                ->tab('seo', 'SEO', 'seo')
                ->switch('seo', 'SEO', __('You can add custom SEO data for each page.', 'G3'))
                ->input('keywords', __('HomePage Keywords', 'G3'), __('Separate tags with commas'))
                ->tab('rss', 'RSS', 'rss')
                ->switch('rss', 'RSS')
                ->readonlyUrl('rss1', __('RSS URL', 'G3'), get_bloginfo('rss_url'))
                ->readonlyUrl('rss2', __('RSS2 URL', 'G3'), get_bloginfo('rss2_url'))
                ->readonlyUrl('atom', __('Atom URL', 'G3'), get_bloginfo('atom_url'))
                ->tab('llm', 'LLM', 'llm')
                ->switch('llm', 'LLM', sprintf(
                    '%s: <a href="%s" target="_blank">%s</a><br>%s: <a href="%s" target="_blank">%s</a>',
                    __('Real-time data', 'G3'),
                    site_url('/helper/llm/endpoint'),
                    site_url('/helper/llm/endpoint'),
                    __('Cache Data', 'G3'),
                    site_url('/llm/llms.txt'),
                    site_url('/llm/llms.txt')
                ))
                ->number('postsPerType', __('Count', 'G3'), __('The number of posts to be generated for each post type.<br>Default: <code>2000</code>.', 'G3'))
                ->switch('manual', __('LLM Cache', 'G3'), __('Real-time data access no longer generates cache files. Please generate cache files manually for better performance.', 'G3'))
                ->html('generator', __('LLM Generator', 'G3'), '<button class="j-button is-outline" type="button" id="generateLLM">' . __('Generate LLM Cache', 'G3') . '</button><p class="description">' . __('Click to generate llms.txt cache file.', 'G3') . '</p>')
                ->tab('sitemap', __('SiteMap', 'G3'), 'sitemap')
                ->callback('sitemap', 'WP SiteMap', fn() => $this->renderWpSitemapField())
                ->callback('g3-sitemap', 'G3-SiteMap', fn() => $this->renderG3SitemapField())
                ->callback('local-sitemap', 'Local Sitemap', fn() => $this->renderLocalSitemapField())
                ->html('sitemapGenerator', __('SiteMap Generator', 'G3'), '<p><button class="j-button is-outline" type="button" id="generateSitemap">' . __('Generate Sitemap', 'G3') . '</button></p><p class="description">' . __('Click to generate sitemap cache files.', 'G3') . '</p>'),
        ];
    }
    protected function end(): void
    {
        $this->linksHandle();
    }
    protected function adminPanelPage(): string
    {
        return 'g3-settings';
    }
    public function render(): void
    {
        $firstPanel = $this->firstPanel();
        if (!$firstPanel instanceof Panel) {
            return;
        }

        $this->createPanel()->render($this, $firstPanel, 'general');
    }
    private function renderWpSitemapField(): string
    {
        $sitemap = home_url('wp-sitemap.xml');
        return '<fieldset><legend class="screen-reader-text"><span>WP sitemap</span></legend><p><a href="' . esc_url($sitemap) . '" target="_blank">' . esc_html($sitemap) . '</a></p><p class="description">' . __('Starting from version 5.5 of WordPress, the functionality of multi-level and multi-page XML sitemaps has been loaded as a default core feature.', 'G3') . '</p></fieldset>';
    }
    private function renderG3SitemapField(): string
    {
        $g3Sitemap = home_url('helper/sitemap/endpoint/');
        return '<fieldset><legend class="screen-reader-text"><span>G3-Sitemap</span></legend><p><a href="' . esc_url($g3Sitemap) . '" target="_blank">' . esc_html($g3Sitemap) . '</a></p><p class="description">' . __('Real-time data', 'G3') . ': ' . __('You can visit the current address to generate local cache files of the sitemap.', 'G3') . '</p></fieldset>';
    }
    private function renderLocalSitemapField(): string
    {
        $localFile = home_url('sitemap/index.html');
        return '<fieldset><legend class="screen-reader-text"><span>Local Sitemap</span></legend><p><a href="' . esc_url($localFile) . '" target="_blank">' . esc_html($localFile) . '</a></p><p class="description">' . __('Cache Data', 'G3') . ': ' . __('<strong>Share it to your friends, robots or AI!</strong> You can access the local sitemap cache file through the current address.', 'G3') . '</p></fieldset>';
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
            Frontend::umd('g3.redirect');
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
        $v = State::get('Setting.option.redirectLink', '1');
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
        echo '<div class="flex-cols"><div class="input-group"><div class="el-addon"><div class="is-text">' . __('Keywords', 'G3') . '</div></div><input class="j-input" placeholder="' . __('Separate tags with commas.', 'G3') . '" type="text" id="seoKeywords" name="seoKeywords" value="' . $keywords . '"></div></div>';
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
        if (get_option('permalink_structure') === '/%postname%/') return;

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

    public static function onLLM(): bool
    {
        $v = State::get('Setting.llm.llm', '1');
        return $v === '1';
    }

    protected function ajax(): void
    {
        add_action('wp_ajax_g3_generate_llm', function () {
            $x = State::get('Setting.llm.llm', '1');
            if ($x !== '1') {
                Response::ajaxError(__('LLM feature is disabled.', 'G3'));
            }

            $nonce = $_POST['nonce'] ?? '';
            if (!wp_verify_nonce($nonce, 'g3_generate_llm') || !current_user_can('manage_options')) {
                Response::ajaxForbidden();
            }

            if ($this->container->has('llmService')) {
                $service = $this->container->get('llmService');
            } else {
                $this->container->setRawDefinition('llmService', LLMService::class);
                $service = $this->container->get('llmService');
            }

            $result = $service->writeStaticFiles();
            if ($result !== false) {
                Response::ajaxSuccess(__('LLM cache generated successfully.', 'G3'));
            }
            Response::ajaxError(__('Failed to generate LLM cache.', 'G3'));
        });
        add_action('wp_ajax_g3_generate_sitemap', function () {
            $nonce = $_POST['nonce'] ?? '';
            if (!wp_verify_nonce($nonce, 'g3_generate_sitemap') || !current_user_can('manage_options')) {
                Response::ajaxForbidden();
            }

            $y = get_option(SystemService::SECURITY_OPTION_KEY)['siteMapGenerator'] ?? '1';
            if ($y !== '1') {
                Response::ajaxError(__('Sitemap generation is disabled.', 'G3'));
            }

            if ($this->container->has('sitemapService')) {
                $service = $this->container->get('sitemapService');
            } else {
                $this->container->setRawDefinition('sitemapService', SitemapService::class);
                $service = $this->container->get('sitemapService');
            }

            $service->writeStaticFiles();

            Response::ajaxSuccess(__('Sitemap generated successfully.', 'G3'));
        });
    }
}
