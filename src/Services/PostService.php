<?php
namespace JEALER\G3\Services;
use JEALER\G3\Components\Components;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Core\Service\Service;
use JEALER\G3\Services\PageService;
use WP_Error;
use WP_Post;
use WP_Query;
use wpdb;
use Exception;

class PostService extends Service {
    // Option Key
    const OPTION_KEY = 'g3_option_reading';
    // SEO Keywords Key
    const KEYWORDS_KEY = 'g3_keywords';
    // Cover Key
    const COVER_KEY = 'g3_cover';
    // ext table
    const EXT_TABLE = 'g3_posts_extra';
    public string $extTable;
    const EXTRA_CACHE_GROUP = 'g3_posts_extra';
    const QUERY_CACHE_GROUP = 'g3_posts_query';
    const MAX_VIEWED        = 100;
    const READED_COOKIE     = 'g3_posts_readed';
    const SKU_NAME          = 'g3_post_sku';
    const PROPERTY_NAME     = 'g3_post_property';
    const GALLERY_NAME      = 'g3_post_gallery';
    private ?WP_Post $post  = null;
    private array    $extra = [];

    public function __construct()
    {
        parent::__construct();
        $this->extTable = $this->wpdb->prefix . self::EXT_TABLE;
    }

    /**
     * init post data
     * @param int|WP_Post|null $postId 
     * @return PostService|null
     * @since 1.0.0
     * @author Wang Shai
     */
    public function init(int|WP_Post|null $postId = null): ?PostService
    {
        if ($postId === null) {
            $postId = get_queried_object();
        }
        $post = $postId instanceof WP_Post ? $postId : get_post($postId);

        if ($post instanceof WP_Post) {
            $this->post  = $post;
            $this->extra = $this->getExtra($post->ID);
            $this->cache = array_merge($this->normalizePostData((array) $this->post), $this->extra);
            return $this;
        }
        return null;
    }

    /**
     * Get post extra data
     * @param int|object $id postId / WP_Post
     * @return array|WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    public function getExtra(int|object $id): array|WP_Error
    {
        $postId = $this->postId($id);
        if ($postId <= 0) {
            return [];
        }

        $cached = wp_cache_get($postId, self::EXTRA_CACHE_GROUP);
        if (is_array($cached)) {
            return $cached;
        }

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM `{$this->extTable}` WHERE `post_id` = %d", $postId),
            ARRAY_A
        );

        $extra = $this->normalizeExtra(is_array($row) ? $row : ['post_id' => $postId]);
        // cache for 1 week
        $result = wp_cache_set($postId, $extra, self::EXTRA_CACHE_GROUP, WEEK_IN_SECONDS);

        if (false === $result) {
            return new WP_Error('cache_failed', 'cache failed for post extra data', [
                'status'  => 500,
                'post_id' => $postId,
            ]);
        }
        return $extra;
    }

    /**
     * Set post extra data
     * @param int|object $id postId / WP_Post
     * @param array $data extra data
     * @return bool|WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    public function setExtra(int|object $id, array $data): bool|WP_Error
    {
        $postId = $this->postId($id);
        if ($postId <= 0) {
            return new WP_Error('invalid_post_id', 'invalid post ID', [
                'status'  => 400,
                'post_id' => $postId,
            ]);
        }

        $data = $this->normalizeExtraForSave($data);
        if (!$data) {
            return new WP_Error('invalid_data', 'invalid extra data', [
                'status'  => 400,
                'post_id' => $postId,
            ]);
        }

        $exists = (bool) $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT `post_id` FROM `{$this->extTable}` WHERE `post_id` = %d", $postId)
        );

        $data['updated_at'] = gmdate('Y-m-d H:i:s');

        if ($exists) {
            $result = $this->wpdb->update(
                $this->extTable,
                $data,
                ['post_id' => $postId],
                $this->extraFormats(array_keys($data)),
                ['%d']
            );
        } else {
            $insert = ['post_id' => $postId] + $data;
            $result = $this->wpdb->insert(
                $this->extTable,
                $insert,
                array_merge(['%d'], $this->extraFormats(array_keys($data)))
            );
        }

        if (false === $result) {
            return new WP_Error('update_failed', 'update failed for post extra data', [
                'status'  => 500,
                'post_id' => $postId,
            ]);
        }

        $cached = wp_cache_delete($postId, self::EXTRA_CACHE_GROUP);
        if (false === $cached) {
            return new WP_Error('cache_failed', 'cache failed for post extra data', [
                'status'  => 500,
                'post_id' => $postId,
            ]);
        }
        return true;
    }

    /**
     * Delete post extra data
     * @param int|object $id postId / WP_Post
     * @return bool|WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    public function deleteExtra(int|object $id): bool|WP_Error
    {
        $postId = $this->postId($id);
        if ($postId <= 0) {
            return new WP_Error('invalid_post_id', 'invalid post ID', [
                'status'  => 400,
                'post_id' => $postId,
            ]);
        }

        $result = $this->wpdb->delete(
            $this->extTable,
            ['post_id' => $postId],
            ['%d']
        );

        if (false === $result) {
            return new WP_Error('delete_failed', 'delete failed for post extra data', [
                'status'  => 500,
                'post_id' => $postId,
            ]);
        }

        $cached = wp_cache_delete($postId, self::EXTRA_CACHE_GROUP);
        if (false === $cached) {
            return new WP_Error('cache_failed', 'cache failed for post extra data', [
                'status'  => 500,
                'post_id' => $postId,
            ]);
        }

        return true;
    }

    /**
     * Query posts with extra data and caching
     * 
     * 查询文章列表并合并扩展数据，支持缓存
     * 
     * @param  array $args WP_Query arguments
     * @return array|WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    public function query(?array $args): array|WP_Error
    {
        if (!$args || !is_array($args)) {
            return new WP_Error('invalid_args', 'Invalid query arguments', [
                'status' => 400,
            ]);
        }

        $cacheKey = '' . md5(serialize($args));

        $cachedResults = wp_cache_get($cacheKey, self::QUERY_CACHE_GROUP);
        if ($cachedResults !== false) {
            return $cachedResults;
        }

        $query = new WP_Query($args);
        if ($query->get('error')) {
            return new WP_Error(
                'query_failed',
                'Failed to query posts: ' . $query->get('error'),
                ['status' => 500]
            );
        }

        if (!is_array($query->posts) || empty($query->posts)) {
            return new WP_Error('no_post_found', 'No post found for the given query parameters.', [
                'status' => 404,
            ]);
        }

        $results = [];
        foreach ($query->posts as $post) {
            if (!($post instanceof WP_Post)) {
                continue;
            }

            $postData = (array) $post;

            $postData = $this->normalizePostData($postData);

            $extra = $this->getExtra($post->ID);
            if ($extra instanceof WP_Error) {
                // debug log
                error_log('Failed to get extra data for post ' . $post->ID . ': ' . $extra->get_error_message());
                $extra = [];
            } else {
                unset($extra['post_id']);
            }

            // Merge data
            $merged    = array_merge($postData, $extra);
            $results[] = $merged;
        }

        wp_reset_postdata();

        // add: found_posts, max_num_pages
        $result = [
            'data'          => $results,
            'found_posts'   => $query->found_posts,
            'max_num_pages' => $query->max_num_pages,
        ];

        // one day cache
        wp_cache_set($cacheKey, $result, self::QUERY_CACHE_GROUP, DAY_IN_SECONDS);
        return $result;
    }

    /**
     * Migrate view count from postmeta to g3_posts_extra table.
     * @param string $key The meta key in postmeta table
     * @return array
     * @since 1.0.0
     * @author Wang Shai
     */
    public function migrateViewCount(string $key): array
    {
        if (trim($key) === '') {
            return [
                'success' => false,
                'message' => __('Postmeta key is empty. Please provide a valid meta key for migration in the wp-config.php file using the <strong>G3_OLD_POST_VIEW_META_KEY</strong> constant.', 'G3'),
            ];
        }

        $startTime = microtime(true);
        // set SQL mode to allow inserting empty strings (to avoid STRICT_MODE errors)
        $this->wpdb->query("SET SESSION sql_mode = ''");

        // record the count
        $beforeCount = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM `{$this->extTable}`");

        // use INSERT ... ON DUPLICATE KEY UPDATE for efficient batch migration
        $sql = "
        INSERT INTO `{$this->extTable}` (post_id, view_count)
        SELECT 
            pm.post_id, 
            CAST(pm.meta_value AS UNSIGNED INTEGER) AS view_count
        FROM `{$this->wpdb->postmeta}` pm
        LEFT JOIN `{$this->extTable}` pe ON pm.post_id = pe.post_id
        WHERE 
            pm.meta_key = %s
            AND (pe.post_id IS NULL OR CAST(pm.meta_value AS UNSIGNED INTEGER) > COALESCE(pe.view_count, 0))
        ON DUPLICATE KEY UPDATE 
            view_count = VALUES(view_count)
    ";

        $sql    = $this->wpdb->prepare($sql, $key);
        $result = $this->wpdb->query($sql);

        // restore the default SQL mode
        $this->wpdb->query("SET SESSION sql_mode = DEFAULT");

        if ($result === false) {
            return [
                'success' => false,
                'message' => 'View count migration failed: ' . $this->wpdb->last_error,
            ];
        }

        $afterCount = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM `{$this->extTable}`");
        $inserted   = $afterCount - $beforeCount;

        // if no new records inserted, check if any records are updated
        if ($inserted === 0) {
            $updated  = (int) $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM `{$this->extTable}` pe
                INNER JOIN `{$this->wpdb->postmeta}` pm ON pe.post_id = pm.post_id
                WHERE pm.meta_key = %s AND pe.view_count = CAST(pm.meta_value AS UNSIGNED INTEGER)",
                    $key
                )
            );
            $migrated = $updated;
        } else {
            $migrated = $inserted;
        }

        wp_cache_flush_group(self::EXTRA_CACHE_GROUP);
        $endTime = microtime(true);

        return [
            'success' => true,
            'message' => sprintf(__('View count migration completed: %d records in %.2f seconds.', 'G3'), $migrated, $endTime - $startTime),
        ];
    }

    private function postId(int|object $id): int
    {
        if ($id instanceof WP_Post) {
            return (int) $id->ID;
        }

        return is_int($id) ? $id : 0;
    }
    private function normalizeExtra(array $row): array
    {
        return [
            'post_id'         => (int) ($row['post_id'] ?? 0),
            'view_count'      => (int) ($row['view_count'] ?? 0),
            'like_count'      => (int) ($row['like_count'] ?? 0),
            'dislike_count'   => (int) ($row['dislike_count'] ?? 0),
            'share_count'     => (int) ($row['share_count'] ?? 0),
            'favorite_count'  => (int) ($row['favorite_count'] ?? 0),
            'seo_title'       => (string) ($row['seo_title'] ?? ''),
            'seo_description' => (string) ($row['seo_description'] ?? ''),
            'seo_keywords'    => (string) ($row['seo_keywords'] ?? ''),
            'gallery'         => $this->decodeExtraValue($row['gallery'] ?? null),
            'property'        => $this->decodeExtraValue($row['property'] ?? null),
            'ext'             => $this->decodeExtraValue($row['ext'] ?? null),
        ];
    }
    private function normalizeExtraForSave(array $data): array
    {
        $normalized = [];

        foreach (['view_count', 'like_count', 'dislike_count', 'share_count', 'favorite_count'] as $key) {
            if (array_key_exists($key, $data)) {
                $normalized[$key] = max(0, (int) $data[$key]);
            }
        }

        foreach (['seo_title', 'seo_description', 'seo_keywords'] as $key) {
            if (array_key_exists($key, $data)) {
                $normalized[$key] = sanitize_text_field((string) $data[$key]);
            }
        }

        foreach (['gallery', 'property', 'ext'] as $key) {
            if (array_key_exists($key, $data)) {
                $normalized[$key] = $this->encodeExtraValue($data[$key]);
            }
        }

        return $normalized;
    }
    private function extraFormats(array $keys): array
    {
        $formats = [];
        foreach ($keys as $key) {
            $formats[] = in_array($key, ['view_count', 'like_count', 'dislike_count', 'share_count', 'favorite_count'], true) ? '%d' : '%s';
        }

        return $formats;
    }
    private function encodeExtraValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            return wp_json_encode($value, JSON_UNESCAPED_UNICODE) ?: null;
        }

        return (string) $value;
    }
    private function decodeExtraValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (!is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
    private function normalizePostData(array $postData): array
    {
        $uselessKeys = [
            'filter',
            'menu_order',
            'pinged',
            'post_content_filtered',
            'post_mime_type',
            'post_parent',
            'ping_status',
            'to_ping',
        ];

        foreach ($uselessKeys as $key) {
            unset($postData[$key]);
        }

        // post_excerpt 清理移除 HTML 标签
        $postData['post_excerpt'] = wp_strip_all_tags($postData['post_excerpt'] ?? '');

        return $postData;
    }

    public function getSeoItems(): array
    {
        return [
            'description' => $this->getDescription(),
            'keywords'    => $this->getKeywords(),
            'title'       => $this->getTitle(),
        ];
    }

    /**
     * Get SEO Title
     * 
     * 获取 SEO 标题
     * 
     * Filter: g3_filter_title
     * 
     * @return string
     */
    public function getTitle(): string
    {
        $title    = '';
        $siteName = get_bloginfo('name');
        $paged    = get_query_var("paged");

        if (is_home() || is_front_page()) {
            $title = $siteName;
            if ($paged > 1) {
                $title = sprintf(__('Page %s', 'G3'), $paged) . ' ' . $title;
            }
        } else {
            $title = wp_title('', false);
        }

        //  elseif (is_singular()) {
        //     $title = $this->getExtra(get_queried_object_id())['seo_title'] ?? '';
        //     if (empty($title)) {
        //         $title = get_the_title();
        //     }
        // } elseif (is_category() || is_tag() || is_tax()) {
        //     $title = single_term_title('', false);
        // } elseif (is_archive()) {
        //     $title = get_the_archive_title();
        // } elseif (is_search()) {
        //     $title = sprintf(__('Search results for %s', 'G3'), get_search_query());
        // } elseif (is_author()) {
        //     $title = get_the_author_meta('nickname', get_queried_object_id());
        // } elseif (is_date()) {
        //     $title = get_the_date();
        // } elseif (is_404()) {
        //     $title = '404';
        // }

        if (!is_404() && $paged > 1) {
            $afterTitle  = ' ' . sprintf(__('Page %s', 'G3'), $paged);
            $title      .= $afterTitle;
        }


        /**
         * Filter: g3_filter_title
         * @param  string $title The post title.
         * @return string The filtered post title.
         */
        $title = apply_filters('g3_filter_title', $title);

        /** @var PostService $postService */
        $postService = $this->container->get(PostService::class);

        $home = $_SERVER['REQUEST_URI'] === '/' || $_SERVER['REQUEST_URI'] === '/index.php' || is_home() || $postService->isOaLogin();

        return $home ? $title : $title . ' - ' . $siteName;
    }

    /**
     * Get Excerpt
     * 
     * 获取文章摘要
     * 
     * @param  int $maxLength The max length of excerpt.
     * @param  object $post The post object.
     * @return string
     */
    public function getExcerpt(int $maxLength = 150, $post = null): string
    {
        $currentPost = ($post instanceof WP_Post && $post->ID) ? $post : get_post();
        $excerpt     = $currentPost->post_excerpt;
        if (empty($excerpt)) {
            $excerpt = wp_strip_all_tags($currentPost->post_content);
        }
        return mb_strimwidth($excerpt, 0, $maxLength, "...");
    }

    /**
     * Get SEO Description
     * 
     * 获取 SEO 文章描述
     * 
     * Filter: g3_filter_description
     * 
     * @return string
     */
    public function getDescription(): string
    {
        $description = '';
        if (is_home() || is_front_page()) {
            $description = get_bloginfo('description');
        } elseif (is_singular()) {
            $description = $this->getExtra(get_queried_object_id())['seo_description'] ?? '';
            if (empty($description)) {
                $description = $this->getExcerpt();
            }
        } elseif (is_category() || is_tag() || is_tax()) {
            $description = strip_tags(term_description(get_queried_object_id()));
        } elseif (is_archive()) {
            $description = get_the_archive_description();
        } elseif (is_search()) {
            $description = sprintf(__('Search results for %s', 'G3'), get_search_query());
        } elseif (is_author() || $this->isUserPage()) {
            $description = get_the_author_meta('description', get_queried_object_id());
        } elseif (is_404()) {
            $description = '404';
        } else {
            $description = get_bloginfo('description');
        }

        /**
         * Filter: g3_filter_description
         * @param  string $description The post description.
         * @return string
         */
        $description = trim(apply_filters('g3_filter_description', $description));

        return $description;
    }

    /**
     * Get SEO Keywords
     * 
     * 获取 SEO 关键词
     * 
     * Filter: g3_filter_keywords
     * 
     * @return string
     */
    public function getKeywords(): string
    {
        $keywords = '';
        $siteName = get_bloginfo('name');

        if (is_home() || is_front_page()) {
            $seo      = get_option(SystemService::SEO_OPTION_KEY, []);
            $keywords = is_array($seo) ? ($seo['keywords'] ?? '') : '';
            $keywords = !empty($keywords) ? $keywords : $siteName;
        } elseif (is_singular()) {
            $keywords = $this->getExtra(get_the_ID())['seo_keywords'] ?? '';
            if (empty($keywords)) {
                // get post_tag if no data in self::KEYWORDS_KEY
                $terms = get_the_terms(get_the_ID(), 'post_tag');
                if (!empty($terms)) {
                    $keywords = array_column($terms, 'name');
                    $keywords = implode(', ', $keywords);
                }
            }
        } elseif (is_category() || is_tag() || is_tax()) {
            $keywords = get_term_meta(get_queried_object_id(), self::KEYWORDS_KEY, true);
        } elseif (is_search()) {
            $keywords = sprintf(__('Search results for %s', 'G3'), get_search_query());
        } elseif (is_author()) {
            $keywords = get_the_author_meta('display_name');
        } elseif (is_archive()) {
            $keywords = get_the_archive_title();
        } elseif (is_404()) {
            $keywords = '404';
        }

        /**
         * Filter: g3_filter_keywords
         */
        $keywords = apply_filters('g3_filter_keywords', $keywords);

        return $keywords;
    }

    public function isUserPage(): bool
    {
        return get_query_var('g3_var_user') !== null;
    }
    public function isLoginPage(): bool
    {
        return get_query_var('g3_var_user') === 'login';
    }
    public function isRegisterPage(): bool
    {
        return get_query_var('g3_var_user') === 'register';
    }
    public function isLostPasswordPage(): bool
    {
        return get_query_var('g3_var_user') === 'lost-password';
    }
    public function isResetPasswordPage(): bool
    {
        return get_query_var('g3_var_user') === 'reset-password';
    }
    public function isMyPage(): bool
    {
        return get_query_var('g3_var_my') !== null;
    }

    public function isOaLogin(): bool
    {
        $security = get_option(SystemService::SECURITY_OPTION_KEY, []);
        $v        = is_array($security) ? ($security['url'] ?? '') : '';
        return get_query_var('custom_admin_login') === $v;
    }

    public function breadcrumb(string $size = 'sm'): string|WP_Error
    {
        $size = in_array($size, ['sm', 'md', 'lg']) ? $size : 'sm';
        if (!$size) {
            return new WP_Error(
                'invalid_size',
                'Invalid breadcrumb size, expected sm, md, lg, but got ' . $size . '.',
                ['status' => 400]
            );
        }

        $items = [];

        $homeLabel = __('Home');
        $isHome    = is_home() || is_front_page();

        if ($isHome) {
            $items[] = [
                'label'  => $homeLabel,
                'url'    => home_url('/'),
                'active' => true,
            ];
        } else {
            $items[] = [
                'label'  => $homeLabel,
                'url'    => home_url('/'),
                'active' => false,
            ];
        }

        if (!$isHome) {
            if (is_singular()) {
                $post = get_queried_object();
                if ($post instanceof WP_Post) {
                    if ($post->post_type === 'page') {
                        $ancestors = array_reverse(get_post_ancestors($post));
                        foreach ($ancestors as $ancestorId) {
                            $items[] = [
                                'label'  => get_the_title($ancestorId),
                                'url'    => get_permalink($ancestorId),
                                'active' => false,
                            ];
                        }
                    } else {
                        $postType = get_post_type_object($post->post_type);
                        if ($postType && !empty($postType->has_archive)) {
                            $archiveLink = get_post_type_archive_link($post->post_type);
                            if ($archiveLink) {
                                $items[] = [
                                    'label'  => $postType->labels->name ?? $post->post_type,
                                    'url'    => $archiveLink,
                                    'active' => false,
                                ];
                            }
                        }

                        if ($post->post_type === 'post') {
                            $categories = get_the_category($post->ID);
                            if (!empty($categories) && !is_wp_error($categories)) {
                                $primaryCategory = $categories[0];
                                $ancestorIds     = array_reverse(get_ancestors($primaryCategory->term_id, 'category'));
                                foreach ($ancestorIds as $ancestorId) {
                                    $ancestor = get_term($ancestorId, 'category');
                                    if ($ancestor && !is_wp_error($ancestor)) {
                                        $items[] = [
                                            'label'  => $ancestor->name,
                                            'url'    => get_term_link($ancestor),
                                            'active' => false,
                                        ];
                                    }
                                }

                                $items[] = [
                                    'label'  => $primaryCategory->name,
                                    'url'    => get_term_link($primaryCategory),
                                    'active' => false,
                                ];
                            }
                        }
                    }

                    $items[] = [
                        'label'  => get_the_title($post),
                        'url'    => get_permalink($post),
                        'active' => true,
                    ];
                }
            } elseif (is_category() || is_tag() || is_tax()) {
                $term = get_queried_object();
                if ($term && isset($term->taxonomy, $term->term_id)) {
                    if (is_taxonomy_hierarchical($term->taxonomy)) {
                        $ancestorIds = array_reverse(get_ancestors((int) $term->term_id, (string) $term->taxonomy));
                        foreach ($ancestorIds as $ancestorId) {
                            $ancestor = get_term($ancestorId, $term->taxonomy);
                            if ($ancestor && !is_wp_error($ancestor)) {
                                $items[] = [
                                    'label'  => $ancestor->name,
                                    'url'    => get_term_link($ancestor),
                                    'active' => false,
                                ];
                            }
                        }
                    }

                    $items[] = [
                        'label'  => single_term_title('', false),
                        'url'    => get_term_link($term),
                        'active' => true,
                    ];
                }
            } elseif (is_search()) {
                $items[] = [
                    'label'  => __('Search') . ': ' . get_search_query(),
                    'url'    => get_search_link(),
                    'active' => true,
                ];
            } elseif (is_author() || $this->isUserPage()) {
                $authorId = get_queried_object_id();
                $name     = get_the_author_meta('display_name', $authorId);

                if ($this->isUserPage()) {
                    $items[] = [
                        'label'  => __('User'),
                        'url'    => '',
                        'active' => false,
                    ];
                }

                $items[] = [
                    'label'  => !empty($name) ? $name : __('Author'),
                    'url'    => $authorId > 0 ? get_author_posts_url($authorId) : '',
                    'active' => true,
                ];
            } elseif ($this->isMyPage()) {
                $items[] = [
                    'label'  => __('My Homepage', 'G3'),
                    'url'    => '',
                    'active' => true,
                ];
            } elseif (is_date()) {
                $year = (int) get_query_var('year');
                if ($year > 0) {
                    $items[] = [
                        'label'  => (string) $year,
                        'url'    => get_year_link($year),
                        'active' => is_year(),
                    ];
                }

                if (is_month() || is_day()) {
                    $month = (int) get_query_var('monthnum');
                    if ($year > 0 && $month > 0) {
                        $items[] = [
                            'label'  => sprintf('%d-%02d', $year, $month),
                            'url'    => get_month_link($year, $month),
                            'active' => is_month(),
                        ];
                    }
                }

                if (is_day()) {
                    $day = (int) get_query_var('day');
                    if ($day > 0) {
                        $items[] = [
                            'label'  => sprintf('%d-%02d-%02d', $year, (int) get_query_var('monthnum'), $day),
                            'url'    => '',
                            'active' => true,
                        ];
                    }
                }
            } elseif (is_404()) {
                $items[] = [
                    'label'  => '404',
                    'url'    => '',
                    'active' => true,
                ];
            } elseif (is_archive()) {
                $items[] = [
                    'label'  => get_the_archive_title(),
                    'url'    => '',
                    'active' => true,
                ];
            }
        }

        $items = apply_filters('g3_filter_breadcrumb_items', $items);
        if (!is_array($items) || !$items) {
            return '';
        }

        $html = '<div class="j-breadcrumb is-' . $size . '"><ul>';
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['label'])) {
                continue;
            }

            $label  = esc_html((string) $item['label']);
            $url    = isset($item['url']) ? (string) $item['url'] : '';
            $active = !empty($item['active']);

            if ($active) {
                $html .= '<li class="is-active">';
                if ($url !== '') {
                    $html .= '<a href="' . esc_url($url) . '">' . $label . '</a>';
                } else {
                    $html .= '<span>' . $label . '</span>';
                }
                $html .= '</li>';
            } else {
                $html .= '<li><a href="' . esc_url($url !== '' ? $url : '#') . '">' . $label . '</a></li>';
            }
        }
        $html .= '</ul></div>';

        return (string) apply_filters('g3_filter_breadcrumb_html', $html, $items);
    }
}
