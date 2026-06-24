<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components\Components;
use JEALER\G3\Components\Share;
use JEALER\G3\Services\ShareService;
use JEALER\G3\Utilities\Context;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Services\SidebarService;
use JEALER\G3\Services\PostService;
use JEALER\G3\Utilities\RobustEncoder;
use Override;

class Post extends Components {
    public array  $option   = [];
    public string $viewsKey;
    protected function options(): void
    {
        $this->option   = Option::get(PostService::OPTION_KEY, [
            'enable'       => '1',
            'viewInterval' => '60',
            'notice'       => __('All publicly displayed data on this platform is sourced from the public internet and is only used for functional testing purposes. They do not represent the views of this platform. We make no guarantees or commitments regarding the authenticity, timeliness, integrity, accuracy, or ownership of the text, images, and other content. Visitors and related parties are advised to verify the information themselves.', 'G3'),
            'autoNotice'   => '0',
            'copyright'    => '0',
            'paidReading'  => '0'
        ]);
        $this->viewsKey = PostService::getViewsKey();
    }
    protected function form(): void
    {
        $this->postViewsControl();
        add_action('save_post', [$this, 'savePost']);
        $taxonomies = get_taxonomies();
        foreach ($taxonomies as $taxonomy) {
            add_action($taxonomy . '_add_form_fields', [$this, 'addCoverFieldInAddForm']);
            add_action($taxonomy . '_edit_form_fields', [$this, 'addCoverFieldInEditForm']);
            add_action('created_' . $taxonomy, [$this, 'updateCoverField']);
            add_action('edited_' . $taxonomy, [$this, 'updateCoverField']);
        }
        if (!isset($_REQUEST['page']) || $_REQUEST['page'] !== 'post-reading') return;
        $this->option = Option::cache(PostService::OPTION_KEY, $this->option);
    }
    protected function system(): void
    {
        add_filter('nav_menu_css_class', [$this, 'renderMenuItemClasses'], 10, 3);
        add_filter('wp_nav_menu_objects', [$this, 'customFilterMenuItems'], 10, 2);
    }
    protected function init(): void
    {
        $this->initPostViews();
        $this->removeAutoP();
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
            require_once G3_PlUGIN_DIR . '/tests/robustEncoder.php';
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
    public function render(): void
    {
        echo '<div class="wrap"><h1>' . __('Reading') . '</h1>';
        $args = [
            'general' => __('General'),
        ];
        Element::tab('Post', 'general', $args);
        echo '</div>';
    }
    protected function settings(): void
    {
        register_setting(
            'reading',
            PostService::OPTION_KEY
        );
        add_settings_section(
            'reading',
            null,
            '__return_false',
            'post-reading'
        );
        Element::settingFields('post-reading', 'reading', [
            [
                'id'       => 'enable',
                'title'    => __('View Statistics', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        PostService::OPTION_KEY,
                        $this->option,
                        'enable',
                        __('View Statistics', 'G3'),
                        __('To add view statistics function to each post & page.', 'G3')
                    );
                }
            ],
            [
                'id'       => 'viewInterval',
                'title'    => __('View Interval', 'G3'),
                'callback' => function () {
                    echo Element::select(
                        PostService::OPTION_KEY,
                        $this->option,
                        'viewInterval',
                        __('View Interval', 'G3'),
                        __('Defines how long a visit from the same user must wait before it counts as a new view.', 'G3'),
                        '',
                        [
                            '1'    => __('1 Minute', 'G3'),
                            '5'    => __('5 Minutes', 'G3'),
                            '10'   => __('10 Minutes', 'G3'),
                            '15'   => __('15 Minutes', 'G3'),
                            '30'   => __('30 Minutes', 'G3'),
                            '60'   => __('1 Hour', 'G3'),
                            '120'  => __('2 Hours', 'G3'),
                            '180'  => __('3 Hours', 'G3'),
                            '240'  => __('4 Hours', 'G3'),
                            '360'  => __('6 Hours', 'G3'),
                            '720'  => __('12 Hours', 'G3'),
                            '1440' => __('1 Day', 'G3'),
                        ]
                    );
                },
                'args'     => [
                    'class'             => 'advanced',
                    'sanitize_callback' => 'absint',
                    'default'           => '60',
                ]
            ],
            [
                'id'       => 'notice',
                'title'    => __('Copyright Notice', 'G3'),
                'callback' => function () {
                    echo Element::textarea(
                        PostService::OPTION_KEY,
                        $this->option,
                        'notice',
                        __('Copyright Notice', 'G3')
                    );
                },
                'args'     => [
                    'sanitize_callback' => 'wp_kses_post',
                ]
            ],
            [
                'id'       => 'autoNotice',
                'title'    => __('Auto Notice', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        PostService::OPTION_KEY,
                        $this->option,
                        'autoNotice',
                        __('Auto Notice', 'G3'),
                        __('Automatically add a notice at the end of the article', 'G3'),
                    );
                }
            ],
            [
                'id'       => 'copyright',
                'title'    => __('Copyright Protection', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        PostService::OPTION_KEY,
                        $this->option,
                        'copyright',
                        __('Copyright Protection', 'G3'),
                        __('Automatically add your encoded & invisible brand mark in the article while saving to protect your content copyright.<br>It will cost several memory while saving. <a href="/wp-admin/admin.php?page=post-reading&g3-test=robustEncoder" target="_blank">Test Performance</a>', 'G3'),
                    );
                },
                'args'     => [
                    'class' => 'advanced'
                ]
            ],
            [
                'id'       => 'paidReading',
                'title'    => __('Paid Reading', 'G3'),
                'callback' => function () {
                    echo Element::switch(
                        PostService::OPTION_KEY,
                        $this->option,
                        'paidReading',
                        __('Paid Reading', 'G3'),
                        __('Launching a knowledge-based paid service.', 'G3')
                    );
                },
                'args'     => [
                    'class' => 'advanced'
                ]
            ]
        ]);
    }
    public function removeAutoP(): void
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
    public function mountCopyright($content)
    {
        if (isset($this->option['autoNotice']) && $this->option['autoNotice'] === '1') {
            $notice  = $this->option['notice'] ?? '';
            $default = "<blockquote class='g3-copyright-notice'>$notice</blockquote>";

            /**
             * Custom Filter: g3_filter_mount_copyright
             */
            $content .= apply_filters('g3_filter_mount_copyright', $default);
        }
        return $content;
    }
    private function initPostViews(): void
    {
        if (!isset($this->option['enable']) || $this->option['enable'] !== '1') {
            return;
        }
        add_action('wp_head', [$this, '_initPostViews']);
    }
    private function postViewsControl(): void
    {
        if (!isset($this->option['enable']) || $this->option['enable'] !== '1') {
            return;
        }
        add_filter('manage_posts_columns', [$this, 'addViewsColumn'], 10, 2);
        add_action('manage_posts_custom_column', [$this, 'showViewsColumn'], 10, 2);
        add_filter('manage_pages_columns', [$this, 'addViewsColumn'], 10, 2);
        add_action('manage_pages_custom_column', [$this, 'showViewsColumn'], 10, 2);
        add_filter('manage_edit-post_sortable_columns', [$this, 'addViewsSortableColumn']);
        add_filter('manage_edit-page_sortable_columns', [$this, 'addViewsSortableColumn']);
        add_action('pre_get_posts', [$this, 'viewsSorting']);
    }
    private function _setPostViews($postId): void
    {
        if ($this->loader->admin()) {
            $minute = (int) $this->option['viewInterval'] ?? 60;
        } else {
            $minute = 1;
        }

        $hour    = $minute && $minute > 0 ? $minute / 60 : 1;
        $metaKey = $this->viewsKey;
        if (is_singular()) {
            $count       = get_post_meta($postId, $metaKey, true);
            $viewed      = $_COOKIE[$metaKey] ?? '';
            $viewedArray = explode(',', $viewed);
            if (!in_array($postId, $viewedArray)) {
                $viewed = $viewed . $postId . ',';
                setcookie($metaKey, $viewed, time() + $hour * 3600, '/');
                switch ($count) {
                    case '':
                        delete_post_meta($postId, $metaKey);
                        add_post_meta($postId, $metaKey, '0');
                        break;
                    default:
                        $count++;
                        update_post_meta($postId, $metaKey, $count);
                        break;
                }
            }
        }
    }
    public function _initPostViews()
    {
        return $this->_setPostViews(get_the_ID());
    }
    public function postboxRender($post): void
    {
        $postId = $post->ID ?? 0;

        $viewCount      = get_post_meta($postId, $this->viewsKey, true) ?: 0;
        $likeCount      = get_post_meta($postId, PostService::LIKE_KEY, true) ?: 0;
        $dislikeCount   = get_post_meta($postId, PostService::DISLIKE_KEY, true) ?: 0;
        $favoritesCount = get_post_meta($postId, PostService::FAVORITE_KEY, true) ?: 0;

        $html  = '<div class="grid-container grid-col-1 grid-col-sm-2 grid-col-md-2 grid-col-lg-4">';
        $html .= '<div class="input-group"><div class="el-addon"><div class="is-text">' . __('View', 'G3') . '</div></div><input class="j-input" type="number" id="views-count" name="viewsCount" value="' . $viewCount . '" min="0"></div>';
        $html .= '<div class="input-group"><div class="el-addon"><div class="is-text">' . __('Favorite', 'G3') . '</div></div><input class="j-input" type="number" id="favorites-count" name="favoritesCount" value="' . $favoritesCount . '" min="0"></div>';
        $html .= '<div class="input-group"><div class="el-addon"><div class="is-text">' . __('Like', 'G3') . '</div></div><input class="j-input" type="number" id="like-count" name="likeCount" value="' . $likeCount . '" min="0"></div>';
        $html .= '<div class="input-group"><div class="el-addon"><div class="is-text">' . __('Dislike', 'G3') . '</div></div><input class="j-input" type="number" id="dislike-count" name="dislikeCount" value="' . $dislikeCount . '" min="0"></div>';
        $html .= '</div>';
        $html .= '<div class="g3-metabox-description">' . __('Tip: You can customize the interaction data according to your operational needs.', 'G3') . '</div>';

        echo $html;

        /**
         * Custom Action: g3_action_post_views
         */
        do_action('g3_action_post_views', $post);
    }
    public function savePost($postId): void
    {
        $this->saveCustomData($postId);
    }
    public function savePostPro($postId): void
    {
        $this->copyrightProtected($postId);
    }
    private function copyrightProtected($postId): void
    {
        if (!isset($this->option['copyright']) || $this->option['copyright'] !== '1') {
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
            // 构造数据 - 使用更紧凑的格式
            $siteData = json_encode([
                'siteName' => get_bloginfo('name'),
                'siteUrl'  => home_url(),
                'time'     => time(),
                'postId'   => $postId, // 添加文章ID用于追踪
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
                error_log('[G3] RobustEncoder: Failed to update post ' . $postId . ': ' . $updated->get_error_message());
            }
        }
        catch (\Exception $e) {
            error_log('' . $e->getMessage());
        }
    }
    private function saveCustomData($postId): void
    {
        if ((!isset($_POST['viewsCount'])) || (!isset($_POST['likeCount'])) || (!isset($_POST['dislikeCount'])) || (!isset($_POST['favoritesCount']))) {
            return;
        }
        $viewCount      = $_POST['viewsCount'];
        $favoritesCount = $_POST['favoritesCount'];
        $likeCount      = $_POST['likeCount'];
        $dislikeCount   = $_POST['dislikeCount'];
        update_post_meta($postId, $this->viewsKey, $viewCount);
        update_post_meta($postId, PostService::LIKE_KEY, $likeCount);
        update_post_meta($postId, PostService::DISLIKE_KEY, $dislikeCount);
        update_post_meta($postId, PostService::FAVORITE_KEY, $favoritesCount);
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
            echo get_post_meta($id, $this->viewsKey, true) ?: 0;
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
        if ($query->get('orderby') === 'views') {
            $query->set('meta_key', $this->viewsKey);
            $query->set('orderby', 'meta_value_num');
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
    <input class="field-upload-url" type="text" name="cover" id="cover" value="">
    <input type="button" class="button field-upload-image-button" value="$upload" style="margin:4px 0">
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
                <input class="field-upload-url" type="text" name="cover" id="cover" value="<?php echo $cover; ?>">
                <input type="button" class="button field-upload-image-button" value="<?php _e('Select Image'); ?>"
                    style="margin:4px 0">
                <?php if ($cover) {
                    echo '<p class="description preview-wrap"><img src="' . $cover . '" style="width:auto;height:120px;object-fit:cover;" alt="cover" /></p>';
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

        $option = Context::get(ShareService::OPTION_KEY);
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
     * Custom Filter: g3_filter_menu_type
     * 
     * Custom Filter: g3_filter_menu_display_type
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
             * Custom Filter: g3_filter_menu_type
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
         * Custom Filter: g3_filter_menu_display_type
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
