<?php
namespace JEALER\G3\Components;
use JEALER\G3\Components;
use JEALER\G3\Utilities\Option;
use JEALER\G3\Utilities\Container;
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Services\SidebarService;
use JEALER\G3\Services\PostService;

class Post extends Components {
    public array $option = [];
    public string $viewsKey;

    #[\Override]
    protected function options(): void
    {
        $default      = Option::get(PostService::OPTION_KEY, [
            'enable'       => '1',
            'viewInterval' => '60',
            'copyright'    => __('All publicly displayed data on this platform is sourced from the public internet and is only used for functional testing purposes. They do not represent the views of this platform. We make no guarantees or commitments regarding the authenticity, timeliness, integrity, accuracy, or ownership of the text, images, and other content. Visitors and related parties are advised to verify the information themselves.', 'G3'),
            'autoNotice'   => '0',
        ]);
        $this->option = Option::cache(PostService::OPTION_KEY, $default);

        $this->viewsKey = PostService::getViewsKey();
    }
    #[\Override]
    protected function system(): void
    {
    }
    #[\Override]
    protected function init(): void
    {
        $this->postViews();
        $this->removeAutoP();
        add_filter('the_content', [$this, 'mountCopyright']);
        $this->registerCover();
    }
    #[\Override]
    protected function admin(): void
    {
        add_action('admin_footer-post.php', [$this, 'modifyPostNewPage']);
        add_action('admin_footer-post-new.php', [$this, 'modifyPostNewPage']);

        $taxonomies = get_taxonomies();
        foreach ($taxonomies as $taxonomy) {
            add_action($taxonomy . '_add_form_fields', [$this, 'addCoverFieldInAddForm']);
            add_action($taxonomy . '_edit_form_fields', [$this, 'addCoverFieldInEditForm']);
            add_action('created_' . $taxonomy, [$this, 'updateCoverField']);
            add_action('edited_' . $taxonomy, [$this, 'updateCoverField']);
        }
    }
    #[\Override]
    protected function adminMenu(): void
    {
        add_submenu_page(
            'g3-settings',
            __('Reading'),
            __('Reading'),
            'manage_options',
            'post-reading',
            [$this, 'render'],
            2
        );
    }
    public function render(): void
    {
        echo '<div class="wrap"><h1>' . __('Reading') . '</h1>';
        $args = [
            'general' => __('General'),
        ];
        Container::tab('Post', 'general', $args);
        echo '</div>';
    }
    #[\Override]
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
        Container::settingFields('post-reading', 'reading', [
            [
                'id'       => 'enable',
                'title'    => __('View Statistics', 'G3'),
                'callback' => function () {
                    echo Container::enable(
                        PostService::OPTION_KEY,
                        $this->option,
                        'enable',
                        __('View Statistics', 'G3'),
                        __('To add view statistics function to each post & page.', 'G3')
                    );
                },
                'args'     => [
                    'label_for' => 'enable',
                    'class'     => 'readingEnable'
                ]
            ],
            [
                'id'       => 'viewInterval',
                'title'    => __('View Interval', 'G3'),
                'callback' => function () {
                    echo Container::select(
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
                    'label_for'         => 'viewInterval',
                    'class'             => 'readingSafeTime',
                    'sanitize_callback' => 'absint',
                    'default'           => '60',
                ]
            ],
            [
                'id'       => 'copyright',
                'title'    => __('Copyright Notice', 'G3'),
                'callback' => function () {
                    echo Container::textarea(
                        PostService::OPTION_KEY,
                        $this->option,
                        'copyright',
                        __('Copyright Notice', 'G3')
                    );
                },
                'args'     => [
                    'label_for'         => 'copyright',
                    'class'             => 'readingCopyright',
                    'sanitize_callback' => 'wp_kses_post',
                ]
            ],
            [
                'id'       => 'autoNotice',
                'title'    => __('Auto Notice', 'G3'),
                'callback' => function () {
                    echo Container::enable(
                        PostService::OPTION_KEY,
                        $this->option,
                        'autoNotice',
                        __('Auto Notice', 'G3'),
                        __('Automatically add a notice at the end of the article', 'G3'),
                    );
                    echo '<script>
                        const _t = document.querySelector(".option-site-visibility");
                        const p1 = document.querySelector("tr.option-site-visibility td fieldset p.description");
                        p1.style.display = "none";
                        const p2 = _t.previousElementSibling.querySelector("p.description");
                        p2.style.display = "none";
                        </script>';
                },
                'args'     => [
                    'label_for' => 'autoNotice',
                    'class'     => 'readingAutoNotice',
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
            $copyright = $this->option['copyright'] ?? '';
            $default   = "<blockquote class='g3-copyright-notice'>$copyright</blockquote>";

            /**
             * Custom Filter: g3_filter_mount_copyright
             */
            $content .= apply_filters('g3_filter_mount_copyright', $default);
        }
        return $content;
    }
    private function postViews(): void
    {
        if (!isset($this->option['enable']) || $this->option['enable'] !== '1') {
            return;
        }
        add_action('wp_head', [$this, 'setPostViews']);
        add_action('add_meta_boxes', [$this, 'initPostbox'], 10, 2);
        add_action('save_post', [$this, 'updatePostboxViews'], 10, 2);
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
        $minute  = (int) $this->option['viewInterval'] ?? 60;
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
    public function setPostViews()
    {
        return $this->_setPostViews(get_the_ID());
    }
    public function initPostbox(): void
    {
        add_meta_box('post-custom-data', __('Custom interaction data', 'G3'), [$this, 'postboxRender'], 'post', 'normal', 'high');
        add_meta_box('post-custom-data', __('Custom interaction data', 'G3'), [$this, 'postboxRender'], 'page', 'normal', 'high');
        add_meta_box('post-custom-data', __('Custom interaction data', 'G3'), [$this, 'postboxRender'], 'news', 'normal', 'high');
        add_meta_box('post-custom-data', __('Custom interaction data', 'G3'), [$this, 'postboxRender'], 'activity', 'normal', 'high');
        add_meta_box('post-custom-data', __('Custom interaction data', 'G3'), [$this, 'postboxRender'], 'forum', 'normal', 'high');
        add_meta_box('post-custom-data', __('Custom interaction data', 'G3'), [$this, 'postboxRender'], 'circle', 'normal', 'high');
        add_meta_box('post-custom-data', __('Custom interaction data', 'G3'), [$this, 'postboxRender'], 'product', 'normal', 'high');
        add_meta_box('post-custom-data', __('Custom interaction data', 'G3'), [$this, 'postboxRender'], 'announcement', 'normal', 'high');
    }
    public function postboxRender($post): void
    {
        $postId = $post->ID ?? 0;

        $viewCount      = get_post_meta($postId, $this->viewsKey, true) ?: 0;
        $likeCount      = get_post_meta($postId, PostService::LIKE_KEY, true) ?: 0;
        $dislikeCount   = get_post_meta($postId, PostService::DISLIKE_KEY, true) ?: 0;
        $favoritesCount = get_post_meta($postId, PostService::FAVORITE_KEY, true) ?: 0;

        $html  = '<div class="j-input-group is-2">';
        $html .= '<div class="input-group-item"><label for="views-count">' . __('View', 'G3') . '</label><input type="number" id="views-count" name="viewsCount" value="' . $viewCount . '" min="0"></div>';
        $html .= '<div class="input-group-item"><label for="favorites-count">' . __('Favorite', 'G3') . '</label><input type="number" id="favorites-count" name="favoritesCount" value="' . $favoritesCount . '" min="0"></div>';
        $html .= '</div><div class="j-input-group is-2">';
        $html .= '<div class="input-group-item"><label for="like-count">' . __('Like', 'G3') . '</label><input type="number" id="like-count" name="likeCount" value="' . $likeCount . '" min="0"></div>';
        $html .= '<div class="input-group-item"><label for="dislike-count">' . __('Dislike', 'G3') . '</label><input type="number" id="dislike-count" name="dislikeCount" value="' . $dislikeCount . '" min="0"></div>';
        $html .= '</div>';
        $html .= '<div class="g3-metabox-description">' . __('Tip: You can customize the interaction data according to your operational needs.', 'G3') . '</div>';

        echo $html;

        /**
         * Custom Action: g3_action_post_views
         */
        do_action('g3_action_post_views', $post);
    }
    public function updatePostboxViews($postId): void
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
        update_post_meta($postId, PostService::DISLIKE_KEY, $favoritesCount);
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
        // $postTypes = get_post_types(['public' => true, '_builtin' => false], 'names');
        // add_theme_support(
        //     'post-thumbnails',
        //     array_merge(['post'], array_keys($postTypes))
        // );
        add_theme_support('post-thumbnails');
    }

    public function addCoverFieldInAddForm()
    {
        wp_enqueue_media();
        wp_enqueue_script('media-grid');
        wp_enqueue_script('media');
        Frontend::loadScript('media.image');
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
        Frontend::loadScript('media.image');
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
        $option = Components::getProperty('Share', 'option');
        if (!isset($option['enable']) || $option['enable'] !== '1') return;
        add_meta_box('g3_metabox_share', __('Content Distribution', 'G3'), [\JEALER\G3\Components\Share::class, 'metaBoxRender'], 'post', 'side', 'high');
        add_meta_box('g3_metabox_share', __('Content Distribution', 'G3'), [\JEALER\G3\Components\Share::class, 'metaBoxRender'], 'page', 'side', 'high');
    }
}