<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use JEALER\G3\Core\Admin\Panel;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Services\LLMService;
use JEALER\G3\Services\SitemapService;
use JEALER\G3\Services\UserService;
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Services\PostService;
use JEALER\G3\Services\SystemService;
use JEALER\G3\Utilities\Message;
use JEALER\G3\Utilities\Response;
use Override;
use Redis;

class Setting extends Components {

    private function seoDefaults(): array
    {
        return [
            'seo'         => '1',
            'description' => '',
            'keywords'    => 'G3 Web,G3 System,JEALER',
        ];
    }

    private function rssDefaults(): array
    {
        return [
            'rss'  => '1',
            'rss1' => '',
            'rss2' => '',
            'atom' => '',
        ];
    }

    private function llmDefaults(): array
    {
        return [
            'llm'          => '1',
            'postsPerType' => 2000,
            'manual'       => '1',
        ];
    }
    protected function defaultOption(): array
    {
        return [SystemService::OPTION_KEY => SystemService::optionValue()];
    }
    private static function optionData(string $key): array
    {
        $option = get_option($key, []);
        return is_array($option) ? $option : [];
    }
    protected function system(): void
    {
        $this->rssHandle();
    }
    protected function form(): void
    {
        Frontend::css('jui');
        Frontend::umd('jui');
        $this->permalink();
        if ((self::optionData(SystemService::SEO_OPTION_KEY)['seo'] ?? '1') === '1') {
            // SEO: add field in edit form for post
            add_action('add_meta_boxes', [$this, 'initPostbox'], 10, 2);
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
        add_action('wp_footer', [$this, 'fingerScript']);
        $this->fingerId();
    }
    private function fingerId()
    {
        $v = $this->option();
        if ($v['online'] ?? '0' === '1' && $this->loader->admin()) {
            $ttl    = $v['onlineDelay'] ?? 30;
            $redis  = $this->container->get(Redis::class);
            $cookie = $_COOKIE[UserService::VISITOR_COOKIE] ?? '';
            if (!empty($cookie)) {
                $visitorId = sanitize_text_field($cookie);
                // case 1:
                // // for TTL
                // $redis->setex("g3:g3_online:{$visitorId}", $ttl * 60, time());
                // // for O(1) lightning-fast statistics
                // $redis->sadd('g3:g3_online:online', $visitorId);

                // case 2:
                // 0(1) lightning-fast write and read, and use HyperLogLog to count unique visitors
                // $redis->pfadd('g3:g3_hll:online', [$visitorId]);

                // case 3:
                // write and update active time (O(log(N)))
                $expireAt = time() + ($ttl * 60);
                $redis->zadd('g3:g3_zset:online', $expireAt, $visitorId);
                // clean up expired entries (O(log(N))
                $redis->zremrangebyscore('g3:g3_zset:online', '-inf', time());
            }
        }
    }
    public function fingerScript()
    {
        $v = $this->option();
        if ($v['online'] ?? '0' === '1' && $this->loader->admin()) {
            echo Frontend::configScript(UserService::VISITOR_SCRIPT_ID, $this->fingerScriptConfig());
        }
    }
    private function fingerScriptConfig(): array
    {
        return [
            'ttl' => $this->option()['onlineDelay'] ?? 30,
        ];
    }
    protected function adminMenu(): void
    {
        add_menu_page(
            'G3 Web',
            'G3 Web',
            "manage_options",
            "g3-settings",
            '__return_false',
            'dashicons-admin-settings',
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
                ->tab('general', __('General'))
                ->option(SystemService::OPTION_KEY, SystemService::optionValue())
                ->switch('sad', __('Sad Mod', 'G3'), __('The entire website will be immersed in a mournful mode with only black, white, and gray colors.', 'G3'))
                ->image('avatar', __('Default Avatar', 'G3'), __('Modify the default system avatar.', 'G3'))
                ->image('cover', __('Default Cover', 'G3'), __('Modify the default system cover.', 'G3'))
                ->input('icp', __('ICP Code', 'G3'), __('If you are conducting online business in mainland China, please be sure to input the ICP filing number issued by the Ministry of Industry and Information Technology of the People\'s Republic of China.', 'G3'))
                ->textarea('headerCode', __('Header Code', 'G3'), __('Enter the HTML, CSS, and JS code that you want to add to the website\'s header here.', 'G3'))
                ->textarea('footerCode', __('Footer Code', 'G3'), __('Enter the HTML, CSS, and JS code that you want to add to the website\'s footer here.', 'G3'))
                ->textarea('customCode', __('Custom Code', 'G3'), __('Enter any HTML, CSS, JS code here that you want to customize output.', 'G3'))
                ->switch('links', __('Links'), __('The links feature helps you manage your friendship links.', 'G3'))
                ->switch('redirectLink', __('Redirect Link', 'G3'), __('All outbound links will be intercepted by the system and redirected to the link middle page instead of the original target url.', 'G3'))
                ->switch('online', __('Online', 'G3') . ' ' . __('Status'), __('Perform user identification and count concurrent online users using browser fingerprints.', 'G3'))
                ->rowClass('advanced')
                ->input('onlineDelay', __('Delay', 'G3'), __('The delay time in minutes for updating the online status. Default: 30.', 'G3'))
                ->rowClass('advanced')
                ->tab('seo', 'SEO')
                ->option(SystemService::SEO_OPTION_KEY, $this->seoDefaults())
                ->switch('seo', 'SEO', __('You can add custom SEO data for each page.', 'G3'))
                ->input('keywords', __('Home') . ' ' . __('Keywords'), __('Separate tags with commas'))
                ->tab('rss', 'RSS')
                ->option(SystemService::RSS_OPTION_KEY, $this->rssDefaults(), false)
                ->switch('rss', 'RSS')
                ->readonlyUrl('rss1', 'RSS ' . __('URL'), get_bloginfo('rss_url'))
                ->readonlyUrl('rss2', 'RSS2 ' . __('URL'), get_bloginfo('rss2_url'))
                ->readonlyUrl('atom', 'Atom ' . __('URL'), get_bloginfo('atom_url'))
                ->tab('llm', 'LLM')
                ->option(SystemService::LLM_OPTION_KEY, $this->llmDefaults(), false)
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
                ->tab('sitemap', __('SiteMap', 'G3'))
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
        $this->createPanel();
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
        if (($this->option()['sad'] ?? '0') !== '1' || is_admin()) return;

        echo '<style>body{-webkit-filter: grayscale(100%);-ms-filter: progid:DXImageTransform.Microsoft.BasicImage(grayscale=1);filter:grayscale(100%);}</style>';
    }
    public function headerCodeHandle(): void
    {
        $v = $this->option()['headerCode'] ?? '';
        if (trim($v) === '') return;

        echo stripslashes($v);
    }
    public function footerCodeHandle(): void
    {
        $v = $this->option()['footerCode'] ?? '';
        if (trim($v) === '') return;

        echo stripslashes($v);
    }
    protected function scripts()
    {
        $v = $this->option();

        if ($v['online'] ?? '0' === '1' && $this->loader->admin()) {
            Frontend::esm('g3.visitor');
        }

        if (($v['redirectLink'] ?? '1') === '1') {
            add_action('save_post', [$this, 'modifyContentUrl'], 10, 3);
            if (preg_match('/^\/oa\//', $_SERVER['REQUEST_URI']) || is_admin()) {
                return;
            }
            Frontend::esm('g3.redirect');
        }
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
        $v = self::optionData(SystemService::OPTION_KEY)['redirectLink'] ?? '1';
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
        if (
            !isset($_POST['g3_seo_keywords'])
            || !isset($_POST['g3_seo_description'])
            || !isset($_POST['g3_seo_title'])
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        ) {
            return;
        }

        /** @var PostService $postService */
        $postService = $this->container->get(PostService::class);
        $postService->setExtra($post_id, [
            'seo_title'       => $_POST['g3_seo_title'] ?? '',
            'seo_description' => $_POST['g3_seo_description'] ?? '',
            'seo_keywords'    => $_POST['g3_seo_keywords'] ?? '',
        ]);
    }

    public function renderPostbox(object $post): void
    {
        $id = $post->ID ?? 0;
        /** @var PostService $postService */
        $postService = $this->container->get(PostService::class);
        $seo         = $postService->getExtra($id);
        $title       = $seo['seo_title'] ?? '';
        $description = $seo['seo_description'] ?? '';
        $keywords    = $seo['seo_keywords'] ?? '';

        echo '<div class="flex-container">';
        echo '<div class="flex-col-1 flex-col-xl-3 flex-col-lg-2 flex-col-md-1 flex-col-sm-1"><div class="input-group"><div class="el-addon"><div class="is-text">' . __('Title') . '</div></div><input class="j-input" type="text" id="seoTitle" name="g3_seo_title" value="' . $title . '"></div></div>';
        echo '<div class="flex-cols"><div class="input-group"><div class="el-addon"><div class="is-text">' . __('Keywords', 'G3') . '</div></div><input class="j-input" placeholder="' . __('Separate tags with commas.', 'G3') . '" type="text" id="seoKeywords" name="g3_seo_keywords" value="' . $keywords . '"></div></div>';
        echo '</div>';
        echo '<div class="flex-container">';
        echo '<div class="flex-col-1"><div class="input-group"><div class="el-addon"><div class="is-text">' . __('Description', 'G3') . '</div></div><input class="j-input" type="text" id="seoDescription" name="g3_seo_description" value="' . $description . '"></div></div>';
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
        echo 'SEO ' . __('Keywords', 'G3') . '</label><input name="g3_seo_keywords" id="seoKeywords" type="text" value="" size="40"><p>';
        echo __('Separate tags with commas.', 'G3') . '</p></div>';
    }
    public function editKeywordsField($tag): void
    {
        echo '<tr class="form-field"><th scope="row"><label for="seoKeywords">';
        echo 'SEO ' . __('Keywords', 'G3');
        echo '</label></th><td><input name="g3_seo_keywords" id="seoKeywords" type="text" value="';
        echo esc_attr(get_term_meta($tag->term_id, PostService::KEYWORDS_KEY, true));
        echo '" size="40"/><p class="description">' . __('Separate tags with commas.', 'G3') . '</p></td></tr>';
    }
    public function updateKeywordsField(int $term_id): bool|int
    {
        if (isset($_POST['g3_seo_keywords'])) {
            if (!current_user_can('manage_categories')) {
                return $term_id;
            }
            $v = $_POST['g3_seo_keywords'];
            update_term_meta($term_id, PostService::KEYWORDS_KEY, $v);
        }
        return true;
    }
    public function rssHandle(): void
    {
        if ((self::optionData(SystemService::RSS_OPTION_KEY)['rss'] ?? '1') !== '1') return;

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
        $v = self::optionData(SystemService::LLM_OPTION_KEY)['llm'] ?? '1';
        return $v === '1';
    }
    protected function ajax(): void
    {
        add_action('wp_ajax_g3_generate_llm', function () {
            if ((self::optionData(SystemService::LLM_OPTION_KEY)['llm'] ?? '1') !== '1') {
                Response::ajaxError(__('LLM feature is disabled.', 'G3'));
            }

            $nonce = $_POST['nonce'] ?? '';
            if (!wp_verify_nonce($nonce, 'g3_generate_llm') || !current_user_can('manage_options')) {
                Response::ajaxForbidden();
            }

            /** @var LLMService $service */
            $service = $this->container->get(LLMService::class);

            $result = $service->writeStaticFiles();
            if ($result !== false) {
                Response::ajaxSuccess(Message::generated());
            }
            Response::ajaxError(__('Failed to generate LLM cache.', 'G3'));
        });
        add_action('wp_ajax_g3_generate_sitemap', function () {
            $nonce = $_POST['nonce'] ?? '';
            if (!wp_verify_nonce($nonce, 'g3_generate_sitemap') || !current_user_can('manage_options')) {
                Response::ajaxForbidden();
            }

            $security = get_option(SystemService::SECURITY_OPTION_KEY, []);
            if (!is_array($security) || ($security['siteMapGenerator'] ?? '0') !== '1') {
                Response::ajaxError(__('Sitemap generation is disabled.', 'G3'));
            }

            /** @var SitemapService $service */
            $service = $this->container->get(SitemapService::class);

            $service->writeStaticFiles();

            Response::ajaxSuccess(Message::generated());
        });
    }
}
