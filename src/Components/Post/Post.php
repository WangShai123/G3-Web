<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use JEALER\G3\Components\Share;
use JEALER\G3\Core\Admin\Panel;
use JEALER\G3\Services\MenuService;
use JEALER\G3\Services\ShareService;
use JEALER\G3\Services\UserService;
use JEALER\G3\Utilities\Common;
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Services\SidebarService;
use JEALER\G3\Services\PostService;
use JEALER\G3\Utilities\RobustEncoder;
use JEALER\G3\Utilities\System;
use Override;
use Exception;

class Post extends Components {
    #[Override]
    protected function hooks()
    {
        $this->filter([
            'locale'                 => [[$this, 'toggleLocale'], 10, 1],
            'option_timezone_string' => [[$this, 'customTimezone'], 10, 1],
        ]);
        $this->action([
            'save_post'          => [[$this, 'savePost'], 10, 2],
            'before_delete_post' => [[$this, 'cleanExtraData'], 10, 1],
            'wp_update_nav_menu' => [[$this, 'flushMenuCache'], 10, 1],
        ]);
    }
    private function default(): array
    {
        return [
            'enable'       => '1',
            'viewInterval' => '60',
            'notice'       => '',
            'autoNotice'   => '0',
            'copyright'    => '0',
            'paidReading'  => '0',
            'language'     => '0',
            'timezone'     => '0',
        ];
    }
    protected function defaultOption(): array
    {
        return [PostService::OPTION_KEY => $this->default()];
    }
    protected function form(): void
    {
        $this->postViewsControl();
        $taxonomies = get_taxonomies();
        foreach ($taxonomies as $taxonomy) {
            add_action($taxonomy . '_add_form_fields', [$this, 'addCoverFieldInAddForm']);
            add_action($taxonomy . '_edit_form_fields', [$this, 'addCoverFieldInEditForm']);
            add_action('created_' . $taxonomy, [$this, 'updateCoverField']);
            add_action('edited_' . $taxonomy, [$this, 'updateCoverField']);
        }
    }
    protected function system(): void
    {
        add_filter('nav_menu_css_class', [$this, 'renderMenuItemClasses'], 10, 3);
        add_filter('wp_nav_menu_objects', [$this, 'customFilterMenuItems'], 10, 2);
    }
    protected function init(): void
    {
        $this->removeAutoP();
        $this->initPostViews();
        add_filter('the_content', [$this, 'mountCopyright']);
        $this->registerCover();
        $this->menus();
    }
    protected function admin(): void
    {
        add_action('save_post', [$this, 'savePostPro']);
        add_action('admin_footer-post.php', [$this, 'modifyPostNewPage']);
        add_action('admin_footer-post-new.php', [$this, 'modifyPostNewPage']);
        add_filter('posts_where', [$this, 'enhanceAdminPostSearch'], 10, 2);

        if (isset($_GET['page']) && $_GET['page'] === 'post-reading' && current_user_can('manage_options')) {
            require_once __DIR__ . '/views/page-robustEncoder.php';
            if (isset($_GET['g3-test']) && $_GET['g3-test'] === 'robustEncoder') {
                g3TestRobustEncoderPerformance();
                exit;
            }
        }

        add_action('wp_nav_menu_item_custom_fields', [$this, 'initMenuItemFields'], 10, 4);
        add_action('wp_update_nav_menu_item', [$this, 'saveMenuItemFields'], 10, 3);
    }
    protected function adminMenu(): void
    {
        add_submenu_page(
            'g3-settings',
            __('Reading'),
            __('Reading'),
            'manage_options',
            'post-reading',
            [$this, 'render'],
            3
        );
    }
    protected function adminPanelPage(): string
    {
        return 'post-reading';
    }
    public function render()
    {
        $this->createPanel();
    }
    protected function adminPanels(): array
    {
        return [
            $this->panel('post-reading', __('Reading'))
                ->tab('general', __('General'))
                ->option(PostService::OPTION_KEY, $this->default())
                ->switch('enable', __('View Statistics', 'G3'), __('To add view statistics function to each post & page.', 'G3'))
                ->select('viewInterval', __('View Interval', 'G3'), [
                    '0'    => __('No Limit', 'G3'),
                    '1'    => __('a minute'),
                    '5'    => sprintf(__('%d minutes'), 5),
                    '10'   => sprintf(__('%d minutes'), 10),
                    '15'   => sprintf(__('%d minutes'), 15),
                    '30'   => sprintf(__('%d minutes'), 30),
                    '60'   => __('an hour'),
                    '120'  => sprintf(__('%d hours'), 2),
                    '180'  => sprintf(__('%d hours'), 3),
                    '240'  => sprintf(__('%d hours'), 4),
                    '360'  => sprintf(__('%d hours'), 6),
                    '720'  => sprintf(__('%d hours'), 12),
                    '1440' => __('a day'),
                ], __('Defines how long a visit from the same user must wait before it counts as a new view.', 'G3'))
                ->rowClass('advanced')
                ->textarea('notice', __('Copyright Notice', 'G3'), __('Copyright notice to display on each post & page. Suggest: All publicly displayed data on this platform is sourced from the public internet and is only used for functional testing purposes. They do not represent the views of this platform. We make no guarantees or commitments regarding the authenticity, timeliness, integrity, accuracy, or ownership of the text, images, and other content. Visitors and related parties are advised to verify the information themselves.', 'G3'))
                ->switch('autoNotice', __('Auto Notice', 'G3'), __('Automatically add a notice at the end of the article', 'G3'))
                ->switch('copyright', __('Copyright Protection', 'G3'), __('Automatically add your encoded & invisible brand mark in the article while saving to protect your content copyright.<br>It will cost several memory while saving. <a href="/wp-admin/admin.php?page=post-reading&g3-test=robustEncoder" target="_blank">Test Performance</a>', 'G3'))
                ->rowClass('advanced')
                ->switch('paidReading', __('Paid Reading', 'G3'), __('Before enabling the knowledge payment service, please ensure that payment and other related functional configurations have been completed.', 'G3'))
                ->rowClass('advanced')
                ->switch('language', __('Language'), __('Enable language switching based on user preferences. Only support Chinese and English.', 'G3'))
                ->rowClass('advanced')
                ->switch('timezone', __('Timezone'), __('After being enabled, it will display the local time based on the user\'s time zone, rather than the time zone set in the system settings.', 'G3'))
                ->rowClass('advanced')
        ];
    }
    public function toggleLocale($locale): string
    {
        $option = $this->option();
        if (!is_admin() && $this->loader->admin() && ($option['language'] ?? '0') === '1') {
            $cookie = UserService::G3_LANG_COOKIE;
            $v      = sanitize_text_field($_COOKIE[$cookie] ?? '');
            $v      = System::normalizeLocale($v);
            if ($v !== null) {
                return $v;
            }
        }
        return $locale;
    }

    public function customTimezone($timezone): string
    {
        $option = $this->option();
        if (!is_admin() && $this->loader->admin() && ($option['timezone'] ?? '0') === '1') {
            $cookie = UserService::G3_TIMEZONE_COOKIE;
            $v      = sanitize_text_field($_COOKIE[$cookie] ?? '');
            if (!empty($v) && in_array($v, timezone_identifiers_list(), true)) {
                return $v;
            } else {
                error_log('[G3 Error] Invalid or missing user timezone cookie: ' . $cookie);
            }
        }
        return $timezone;
    }
    private function removeAutoP(): void
    {
        /**
         * Remove automatically added paragraph tags from excerpts
         */
        remove_filter('the_excerpt', 'wpautop');

        /**
         * Remove automatically added paragraph tags from term descriptions
         */
        remove_filter('term_description', 'wpautop');
    }

    public function mountCopyright($content): string
    {
        $option = $this->option();
        if (($option['autoNotice'] ?? '0') === '1') {
            $notice  = $option['notice'] ?? '';
            $default = "<blockquote class='g3-copyright-notice'>$notice</blockquote>";
            /**
             * Filter: g3_filter_mount_copyright
             */
            $content .= apply_filters('g3_filter_mount_copyright', $default);
        }
        return $content;
    }
    private function initPostViews(): void
    {
        if (($this->option()['enable'] ?? '0') === '1') {
            add_action('wp_head', [$this, '_initPostViews']);
        }
    }
    private function postViewsControl(): void
    {
        if (($this->option()['enable'] ?? '0') !== '1') {
            return;
        }
        add_filter('manage_posts_columns', [$this, 'addViewsColumn'], 10, 2);
        add_action('manage_posts_custom_column', [$this, 'showViewsColumn'], 10, 2);
        add_filter('manage_pages_columns', [$this, 'addViewsColumn'], 10, 2);
        add_action('manage_pages_custom_column', [$this, 'showViewsColumn'], 10, 2);
        // add_filter('manage_edit-post_sortable_columns', [$this, 'addViewsSortableColumn']);
        // add_filter('manage_edit-page_sortable_columns', [$this, 'addViewsSortableColumn']);
        // add_action('pre_get_posts', [$this, 'viewsSorting']);
    }
    public function _initPostViews()
    {
        return $this->_setPostViews(get_the_ID());
    }
    private function _setPostViews($postId): void
    {
        if (!is_singular()) {
            return;
        }
        $intervalMinutes = $this->option()['viewInterval'] ?? '60';
        if ($intervalMinutes < 0) {
            $intervalMinutes = 0;
        }

        /** @var PostService $postService */
        $postService = $this->container->get(PostService::class);

        // No Limit
        $e = $this->loader->admin() && ($intervalMinutes > 0);
        if (!$e) {
            $count = $postService->getExtra($postId)['view_count'] ?? 0;
            $postService->setExtra($postId, ['view_count' => $count + 1]);
            return;
        }

        // With Interval
        $cookieExpireSeconds = $intervalMinutes * 60;
        $count               = $postService->getExtra($postId)['view_count'] ?? 0;

        $cookieKey   = PostService::READED_COOKIE;
        $viewed      = $_COOKIE[$cookieKey] ?? '';
        $viewedArray = explode(',', $viewed);
        // filter empty values
        $viewedArray = array_filter($viewedArray, function ($value) {
            return $value !== '';
        });

        if (in_array($postId, $viewedArray)) return;

        $viewedArray[] = $postId;
        $maxViewed     = PostService::MAX_VIEWED;
        if (count($viewedArray) > $maxViewed) {
            $viewedArray = array_slice($viewedArray, -$maxViewed);
        }

        $newViewed = implode(',', $viewedArray) . ',';

        // check as cookie length
        $maxCookieLength = 4096;
        if (strlen($newViewed) > $maxCookieLength) {
            // from back to front
            $newViewed = substr($newViewed, -$maxCookieLength);
            // last comma position
            $firstComma = strpos($newViewed, ',');
            if ($firstComma !== false) {
                // make sure the complete id
                $newViewed = substr($newViewed, $firstComma + 1);
            }
        }

        setcookie($cookieKey, $newViewed, time() + $cookieExpireSeconds, '/');
        $postService->setExtra($postId, ['view_count' => $count + 1]);
    }

    public function postboxRender($post): void
    {
        $postId = $post->ID ?? 0;

        /** @var PostService $postService */
        $postService = $this->container->get(PostService::class);
        $data        = $postService->getExtra($postId);

        $viewCount     = $data['view_count'] ?? 0;
        $likeCount     = $data['like_count'] ?? 0;
        $dislikeCount  = $data['dislike_count'] ?? 0;
        $favoriteCount = $data['favorite_count'] ?? 0;
        $shareCount    = $data['share_count'] ?? 0;

        $html  = '<div class="grid-container grid-col-1 grid-col-sm-2 grid-col-md-3">';
        $html .= '<div class="input-group"><div class="el-addon"><div class="is-text">' . __('View', 'G3') . '</div></div><input class="j-input" type="number" id="views-count" name="g3_views_count" value="' . $viewCount . '" min="0"></div>';
        $html .= '<div class="input-group"><div class="el-addon"><div class="is-text">' . __('Favorite', 'G3') . '</div></div><input class="j-input" type="number" id="favorites-count" name="g3_favorite_count" value="' . $favoriteCount . '" min="0"></div>';
        $html .= '<div class="input-group"><div class="el-addon"><div class="is-text">' . __('Share', 'G3') . '</div></div><input class="j-input" type="number" id="share-count" name="g3_share_count" value="' . $shareCount . '" min="0"></div>';
        $html .= '<div class="input-group"><div class="el-addon"><div class="is-text">' . __('Like', 'G3') . '</div></div><input class="j-input" type="number" id="like-count" name="g3_like_count" value="' . $likeCount . '" min="0"></div>';
        $html .= '<div class="input-group"><div class="el-addon"><div class="is-text">' . __('Dislike', 'G3') . '</div></div><input class="j-input" type="number" id="dislike-count" name="g3_dislike_count" value="' . $dislikeCount . '" min="0"></div>';
        $html .= '</div>';
        $html .= '<div class="g3-metabox-description">' . __('Tip: You can customize the interaction data according to your operational needs.', 'G3') . '</div>';

        echo $html;

        /**
         * Custom Action: g3_action_post_views
         */
        do_action('g3_action_post_views', $post);
    }
    public function savePost($postId, $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        $viewCount      = $_POST['g3_views_count'] ?? 0;
        $likeCount      = $_POST['g3_like_count'] ?? 0;
        $dislikeCount   = $_POST['g3_dislike_count'] ?? 0;
        $shareCount     = $_POST['g3_share_count'] ?? 0;
        $favoriteCount  = $_POST['g3_favorite_count'] ?? 0;
        $seoTitle       = $_POST['g3_seo_title'] ?? '';
        $seoDescription = $_POST['g3_seo_description'] ?? '';
        $seoKeywords    = $_POST['g3_seo_keywords'] ?? '';
        $gallery        = $_POST['g3_post_gallery'] ?? [];
        $property       = $_POST['g3_post_property'] ?? [];

        // auto save seo data if empty
        // if (trim($seoTitle) === '') {
        //     $seoTitle = $post->post_title ?? '';
        // }
        // if (trim($seoDescription) === '') {
        //     $content        = wp_strip_all_tags($post->post_content ?? '');
        //     $seoDescription = Common::truncate($content, 150);
        // }
        // if (trim($seoKeywords) === '') {
        //     $tags        = wp_get_post_terms($post->ID, 'post_tag', ['fields' => 'names']);
        //     $seoKeywords = !empty($tags) && !is_wp_error($tags) ? implode(',', $tags) : '';
        // }

        /** @var PostService $postService */
        $postService = $this->container->get(PostService::class);
        $ext         = [];
        $test        = $postService->setExtra($postId, [
            'view_count'      => (int) $viewCount,
            'like_count'      => (int) $likeCount,
            'dislike_count'   => (int) $dislikeCount,
            'share_count'     => (int) $shareCount,
            'favorite_count'  => (int) $favoriteCount,
            'seo_title'       => $seoTitle,
            'seo_description' => $seoDescription,
            'seo_keywords'    => $seoKeywords,
            'gallery'         => array_values($gallery),
            'property'        => array_values($property),
            'ext'             => array_values($ext)
        ]);
    }
    public function cleanExtraData($postId): void
    {
        /** @var PostService $postService */
        $postService = $this->container->get(PostService::class);
        $postService->deleteExtra($postId);
    }
    public function flushMenuCache($menuId): void
    {
        wp_cache_flush_group(MenuService::MENU_HTML_CACHE_GROUP);
        wp_cache_flush_group(MenuService::MENU_JSON_CACHE_GROUP);
    }
    public function savePostPro($postId): void
    {
        $this->copyrightProtected($postId);
    }
    private function copyrightProtected($postId): void
    {
        if (($this->option()['copyright'] ?? '0') !== '1') {
            return;
        }

        // 防止无限循环和权限检查
        if (
            wp_is_post_revision($postId) ||
            wp_is_post_autosave($postId) ||
            !current_user_can('edit_post', $postId)
        ) {
            return;
        }

        // 获取文章对象，使用缓存
        static $processed_posts = [];
        if (isset($processed_posts[$postId])) {
            return; // 避免重复处理
        }
        $processed_posts[$postId] = true;

        $post = get_post($postId);
        if (!$post) {
            return;
        }

        // 忽略列表检查
        $ignoreList = [
            'revision',
            'nav_menu_item',
            'product',
            'attachment',
            'customize_changeset',
            'oembed_cache',
            'user_request',
            'wp_block'
        ];

        if (in_array($post->post_type, $ignoreList, true)) {
            return;
        }

        // 检查内容长度，避免处理空内容或过短内容
        if (empty($post->post_content) || mb_strlen($post->post_content, 'UTF-8') < 100) {
            return;
        }

        // 检查是否已经有幽灵块，避免重复处理
        if (RobustEncoder::hasGhostBlocks($post->post_content)) {
            $ghost_count = RobustEncoder::getGhostBlockCount($post->post_content);
            // 如果已经有足够的幽灵块，跳过处理（现在插入多个块）
            if ($ghost_count >= 2) {
                return;
            }
        }

        try {
            $siteData = json_encode([
                'siteName' => get_bloginfo('name'),
                'siteUrl'  => home_url(),
                'time'     => time(),
                'postId'   => $postId,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($siteData === false) {
                return;
            }

            // 清除旧幽灵块
            $cleanContent = RobustEncoder::removeAllGhostBlocks($post->post_content);

            // 检查清理后的内容长度
            if (mb_strlen($cleanContent, 'UTF-8') < 50) {
                return;
            }

            // 分散嵌入新数据
            $newContent = RobustEncoder::embedPayloadIntoContent($cleanContent, $siteData);

            // 检查内容是否实际发生了变化
            if ($newContent === $post->post_content) {
                return;
            }

            // 防止循环调用
            remove_action('save_post', [$this, 'copyrightProtected'], 10);

            // 使用更高效的更新方式
            $updated = wp_update_post([
                'ID'           => $postId,
                'post_content' => $newContent
            ], false); // false = 不触发wp_error

            // 恢复钩子
            add_action('save_post', [$this, 'copyrightProtected'], 10, 1);

            if (is_wp_error($updated)) {
                error_log('[G3 RobustEncoder] Failed to update post ' . $postId . ': ' . $updated->get_error_message());
            }
        }
        catch (Exception $e) {
            error_log('[G3 RobustEncoder] Exception in copyrightProtected ' . $postId . ': ' . $e->getMessage());
        }
    }
    public function addViewsColumn(array $columns): array
    {
        $newColumns = [];
        foreach ($columns as $key => $title) {
            // Put the Views column before the Comments column
            if ($key == 'comments')
                $newColumns['views'] = __('Views', 'G3');
            $newColumns[$key] = $title;
        }
        return $newColumns;
    }
    public function showViewsColumn($column_name, $id): void
    {
        if ($column_name === 'views') {
            echo $this->container->get(PostService::class)->getExtra($id)['view_count'] ?? 0;
        }
    }

    public function addViewsSortableColumn(array $columns): array
    {
        $columns['views'] = __('Views', 'G3');
        return $columns;
    }
    public function viewsSorting($query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        // if ($query->get('orderby') === 'views') {
        //     $query->set('meta_key', $this->viewsKey);
        //     $query->set('orderby', 'meta_value_num');
        // }
        if ($query->get('orderby') === 'views') {
            /** @var PostService $postService */
            $postService = $this->container->get(PostService::class);
            $extTable    = $postService->extTable;
            global $wpdb;

            // 添加 JOIN 子句
            add_filter('posts_join', function ($join) use ($extTable, $wpdb) {
                return $join . " LEFT JOIN `{$extTable}` ON {$wpdb->posts}.ID = {$extTable}.post_id";
            });

            // 修改排序字段
            $query->set('orderby', "{$extTable}.view_count");

            // 保持原有排序方向，默认 DESC
            if (!$query->get('order')) {
                $query->set('order', 'DESC');
            }
        }
    }
    public function modifyPostNewPage()
    {
        echo <<<HTML
<style>
#postexcerpt .inside p,
#commentstatusdiv .inside p.meta-options label:has(input[name="ping_status"]),
#trackbacksdiv .inside p:last-child,
#postcustom .inside > p {display: none}
</style>
HTML;
    }

    public function registerCover(): void
    {
        add_theme_support('post-thumbnails');
    }

    public function addCoverFieldInAddForm()
    {
        wp_enqueue_media();
        wp_enqueue_script('media-grid');
        wp_enqueue_script('media');
        Frontend::umd('g3.media.image');
        $cover  = __('Cover', 'G3');
        $upload = __('Select Image');
        echo <<<HTML
<div class="form-field">
    <label for="cover">$cover</label>
    <input class="g3-field_input_upload-url" type="text" name="cover" id="cover" value="">
    <input type="button" class="button g3-field_button_upload-image" value="$upload" style="margin-top:4px">
</div>
HTML;
    }
    public function addCoverFieldInEditForm($tag)
    {
        wp_enqueue_media();
        wp_enqueue_script('media-grid');
        wp_enqueue_script('media');
        Frontend::umd('g3.media.image');
        $cover = get_term_meta($tag->term_id, PostService::COVER_KEY, true);
        ?>
        <tr class="form-field">
            <th scope="row" valign="top">
                <label for="cover">
                    <?php _e('Cover', 'G3'); ?>
                </label>
            </th>
            <td>
                <input class="g3-field_input_upload-url" type="text" name="cover" id="cover" value="<?php echo $cover; ?>">
                <input type="button" class="button g3-field_button_upload-image" value="<?php _e('Select Image'); ?>"
                    style="margin-top:4px">
                <?php if ($cover) {
                    echo '<div class="g3-preview-wrap"><img src="' . $cover . '" class="g3-preview-image"  alt="cover" /></div>';
                } ?>
            </td>
        </tr>
        <?php
    }
    public function updateCoverField($term_id)
    {
        if (isset($_POST['cover'])) {
            if (!current_user_can('manage_categories')) {
                return $term_id;
            }
            update_term_meta($term_id, PostService::COVER_KEY, $_POST['cover']);
        }
    }
    protected function sidebar(): void
    {
        register_sidebar([
            'name'          => __('Homepage Sidebar', 'G3'),
            'id'            => 'home',
            'description'   => __('You can add widgets to homepage sidebar.', 'G3'),
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget'  => '</div>',
            'before_title'  => '<h3 class="widget-title">',
            'after_title'   => '</h3>',
        ]);
        register_sidebar([
            'name'          => __('Archive Sidebar', 'G3'),
            'id'            => 'archive',
            'description'   => __('You can add widgets to archive sidebar.', 'G3'),
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget'  => '</div>',
            'before_title'  => '<h3 class="widget-title">',
            'after_title'   => '</h3>',
        ]);
        register_sidebar([
            'name'          => __('Search Sidebar', 'G3'),
            'id'            => 'search',
            'description'   => __('You can add widgets to search sidebar.', 'G3'),
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget'  => '</div>',
            'before_title'  => '<h3 class="widget-title">',
            'after_title'   => '</h3>',
        ]);
        register_sidebar([
            'name'          => __('Post Sidebar', 'G3'),
            'id'            => 'post',
            'description'   => __('You can add widgets to post sidebar.', 'G3'),
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget'  => '</div>',
            'before_title'  => '<h3 class="widget-title">',
            'after_title'   => '</h3>',
        ]);
        register_sidebar([
            'name'          => __('Page Sidebar', 'G3'),
            'id'            => 'page',
            'description'   => __('You can add widgets to page sidebar.', 'G3'),
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget'  => '</div>',
            'before_title'  => '<h3 class="widget-title">',
            'after_title'   => '</h3>',
        ]);
    }
    protected function widgets(): void
    {
        // remove WP widget: recent posts
        unregister_widget('WP_Widget_Recent_Posts');

        // register custom widget
        SidebarService::registerWidget('PostWidget', __DIR__);
    }
    protected function metaBox(): void
    {
        add_meta_box(
            'post-custom-data',
            __('Custom interaction data', 'G3'),
            [$this, 'postboxRender'],
            get_post_types([], 'names'),
            'normal',
            'high'
        );

        $option = get_option(ShareService::OPTION_KEY, []);
        $option = is_array($option) ? $option : [];
        if (isset($option['enable']) && $option['enable'] === '1') {
            if (
                $option['wechatMediaLibrary'] === '0' &&
                $option['qqZone'] === '0' &&
                $option['douYin'] === '0'
            ) {
                return;
            }
            add_meta_box(
                'g3_metabox_share',
                __('Content Distribution', 'G3'),
                [Share::class, 'metaBoxRender'],
                get_post_types([], 'names'),
                'side',
                'high'
            );
        }
    }
    public function enhanceAdminPostSearch($where, $query)
    {
        if (!is_admin() || !$query->is_main_query()) {
            return $where;
        }
        // safe check
        $current_screen = get_current_screen();
        if ($current_screen->base !== 'edit') {
            return $where;
        }

        $searchKeyword = $query->get('s');
        if (empty($searchKeyword)) {
            return $where;
        }

        // enhance: ID Search Support
        if (ctype_digit($searchKeyword) && $searchKeyword > 0) {
            $postId = (int) $searchKeyword;
            // build ID search query
            global $wpdb;
            $core_condition = ltrim($where, ' AND');
            return " AND ( ({$core_condition}) OR {$wpdb->posts}.ID = {$postId} )";
        }
        return $where;
    }
    private function menus(): void
    {
        register_nav_menus([
            'desktop-header'           => __('Desktop Header Menu', 'G3'),
            'desktop-header-secondary' => __('Desktop Header Secondary Menu', 'G3'),
            'desktop-footer'           => __('Desktop Footer Menu', 'G3'),
            'desktop-footer-secondary' => __('Desktop Footer Secondary Menu', 'G3'),
            'mobile-menu'              => __('Mobile Menu', 'G3'),
            'shop-menu'                => __('Shop Menu', 'G3'),
        ]);
    }

    /**
     * Init Menu Item Fields
     * 
     * 初始化菜单项目字段
     * 
     * Filter: g3_filter_menu_type
     * Filter: g3_filter_menu_display_type
     * 
     * @param int $item_id
     * @param $item
     * @param int $depth
     * @param $args
     * @return void
     */
    public function initMenuItemFields($item_id, $item, $depth, $args): void
    {
        // one level menu, two level menu extension
        if ($depth === 0 || $depth === 1) {
            // extension: menu type
            $type = get_post_meta($item_id, '_menu_item_menu_type', true);
            $type = !empty($type) ? $type : '';
            $type = sanitize_html_class($type);

            $type_options = [
                ''               => __('General Menu', 'G3'),
                'list-card-menu' => __('List Card Menu', 'G3'),
                'card-menu'      => __('Card Menu', 'G3'),
            ];

            /**
             * Filter: g3_filter_menu_type
             */
            $type_options = apply_filters('g3_filter_menu_type', $type_options);
            ?>
            <p class="field-type description description-wide">
                <label for="edit-menu-item-menu-type-<?php echo $item_id; ?>">
                    <span class="advanced"><?php _e('Menu Type', 'G3'); ?></span><br>
                    <select id="edit-menu-item-menu-type-<?php echo $item_id; ?>" class="widefat code edit-menu-item-menu-type"
                        name="menu-item-menu-type[<?php echo $item_id; ?>]">
                        <?php foreach ($type_options as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($type, $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </p>
            <?php
        }
        // extension: display type
        $display_type         = get_post_meta($item_id, '_menu_item_display_type', true);
        $display_type         = !empty($display_type) ? $display_type : '';
        $display_type         = sanitize_html_class($display_type);
        $display_type_options = [
            ''              => __('General'),
            'logged-in'     => __('Visible only when logged in', 'G3'),
            'not-logged-in' => __('Visible only when not logged in', 'G3'),
        ];

        /**
         * Filter: g3_filter_menu_display_type
         */
        $display_type_options = apply_filters('g3_filter_menu_display_type', $display_type_options);
        ?>
        <p class="field-display-type description description-wide">
            <label for="edit-menu-item-display-type-<?php echo $item_id; ?>">
                <span class="advanced"><?php _e('Display Type', 'G3'); ?></span><br>
                <select id="edit-menu-item-display-type-<?php echo $item_id; ?>"
                    class="widefat code edit-menu-item-display-type" name="menu-item-display-type[<?php echo $item_id; ?>]">
                    <?php foreach ($display_type_options as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($display_type, $value); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </p>
        <?php
    }
    public function saveMenuItemFields($menu_id, $menu_item_db_id, $args): void
    {
        if (isset($_REQUEST['menu-item-menu-type'][$menu_item_db_id])) {
            $type = sanitize_text_field($_REQUEST['menu-item-menu-type'][$menu_item_db_id]);
            update_post_meta($menu_item_db_id, '_menu_item_menu_type', $type);
        }

        if (isset($_REQUEST['menu-item-display-type'][$menu_item_db_id])) {
            $display_type = sanitize_text_field($_REQUEST['menu-item-display-type'][$menu_item_db_id]);
            update_post_meta($menu_item_db_id, '_menu_item_display_type', $display_type);
        }
    }
    public function renderMenuItemClasses(array $classes, $item, $args): array
    {
        if ($item->menu_item_parent == 0) {
            $type = get_post_meta($item->ID, '_menu_item_menu_type', true);
            if (!empty($type)) {
                $classes[] = sanitize_html_class($type);
            }
        }
        return $classes;
    }
    public function customFilterMenuItems(array $items, $args): array
    {
        $status = is_user_logged_in();
        foreach ($items as $key => $item) {
            $display_type = get_post_meta($item->ID, '_menu_item_display_type', true);
            if (($display_type === 'logged-in' && !$status) || ($display_type === 'not-logged-in' && $status)) {
                unset($items[$key]);
            }
        }
        return $items;
    }
}
