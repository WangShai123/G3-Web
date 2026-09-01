<?php
namespace JEALER\G3\Services;
use JEALER\G3\Components\Components;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Core\Service\Service;
use JEALER\G3\Services\PageService;
use JEALER\G3\Traits\Cache;
use JEALER\G3\Utilities\Type;
use WP_Error;
use WP_Post;
use WP_Query;
use wpdb;
use Exception;
use WP_Term;
use Closure;

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
    private array $post  = [];
    private array $extra = [];
    private const READING_TECH_KEYWORDS = [
        'abstract'  => true,
        'async'     => true,
        'await'     => true,
        'break'     => true,
        'catch'     => true,
        'const'     => true,
        'continue'  => true,
        'die'       => true,
        'echo'      => true,
        'eval'      => true,
        'exit'      => true,
        'final'     => true,
        'finally'   => true,
        'function'  => true,
        'include'   => true,
        'interface' => true,
        'let'       => true,
        'namespace' => true,
        'print'     => true,
        'private'   => true,
        'protected' => true,
        'public'    => true,
        'require'   => true,
        'return'    => true,
        'self'      => true,
        'static'    => true,
        'throw'     => true,
        'trait'     => true,
        'try'       => true,
        'var'       => true,
    ];

    use Cache;

    public function __construct()
    {
        parent::__construct();
        $this->extTable = $this->wpdb->prefix . self::EXT_TABLE;
    }

    /**
     * init post data
     * 
     * 初始化文章数据
     * 
     * @param int|WP_Post|null $postId 
     * @return PostService|null
     */
    public function init(int|WP_Post|null $postId = null): ?PostService
    {
        if ($postId === null) {
            $postId = get_queried_object();
        }
        $post = $postId instanceof WP_Post ? $postId : get_post($postId);

        if ($post instanceof WP_Post) {
            $this->post  = $this->normalizePost((array) $post);
            $this->extra = $this->getExtra($post->ID);
            $this->cache = array_merge($this->post, $this->extra);
            return $this;
        }
        return null;
    }

    /**
     * Get post extra data
     * @param int|WP_Post $id
     * @return array|WP_Error
     */
    public function getExtra(int|WP_Post $id): array|WP_Error
    {
        $postId = $this->postId($id);
        if ($postId <= 0) return [];

        $cached = wp_cache_get($postId, self::EXTRA_CACHE_GROUP);
        if (is_array($cached)) {
            $reInitResult = $this->reInitOldData($postId, $cached);
            if (false !== $reInitResult) {
                return $reInitResult;
            }
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
     * reInit old data
     * @param  int $postId
     * @param  array $data
     * @return array|bool
     */
    private function reInitOldData(int $postId, array $data): array|bool
    {
        // reading_time
        if (isset($data['reading_time']) && $data['reading_time'] > 0) return $data;

        $post                 = get_post($postId);
        $readingTime          = $this->calculateReadingTime($post->post_content);
        $data['reading_time'] = $readingTime;
        $result               = $this->setExtra($postId, $data);

        if (is_wp_error($result)) return false;

        return $data;
    }

    /**
     * Set post extra data
     * @param int|WP_Post $id
     * @param array $data extra data
     * @return bool|WP_Error
     */
    public function setExtra(int|WP_Post $id, array $data): bool|WP_Error
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
     * @param int|WP_Post $id
     * @return bool|WP_Error
     */
    public function deleteExtra(int|WP_Post $id): bool|WP_Error
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
     * @param  array $args
     * @param  array|null $unset
     * @return array|WP_Error
     */
    public function query(?array $args, ?array $unset = null): array|WP_Error
    {
        if (!$args || !is_array($args)) {
            return new WP_Error('invalid_args', 'Invalid query arguments', [
                'status' => 400,
            ]);
        }

        $cacheParams = $args;
        if (!empty($unset)) {
            $cacheParams['_unset'] = $unset;
        }
        $cacheKey = $this->generateArrayCacheKey($cacheParams);

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

            $extra = $this->getExtra($post->ID);
            if ($extra instanceof WP_Error) {
                error_log('Failed to get extra data for post ' . $post->ID . ': ' . $extra->get_error_message());
                $extra = [];
            } else {
                unset($extra['post_id']);
            }
            $postData  = $this->normalizePost((array) $post);
            $merged    = array_merge($postData, $extra);
            $merged    = Type::arrayExcept($merged, $unset);
            $results[] = $merged;
        }

        wp_reset_postdata();

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

    private function postId(int|WP_Post $id): int
    {
        if ($id instanceof WP_Post) {
            return (int) $id->ID;
        }

        return is_int($id) ? $id : 0;
    }

    private function normalizePost(array $post): array
    {
        $respect = [
            'post_id'          => (int) ($post['ID'] ?? 0),
            'slug'             => (string) ($post['post_name'] ?? ''),
            'post_type'        => (string) ($post['post_type'] ?? ''),

            'created_at'       => (string) ($post['post_date_gmt'] ?? ''),
            'created_at_local' => (string) ($post['post_date'] ?? ''),
            'updated_at'       => (string) ($post['post_modified_gmt'] ?? ''),
            'updated_at_local' => (string) ($post['post_modified'] ?? ''),

            'user_id'          => (int) ($post['post_author'] ?? 0),
            'title'            => (string) ($post['post_title'] ?? ''),
            'url'              => (string) ($post['guid'] ?? ''),
            'content'          => (string) ($post['post_content'] ?? ''),
            'excerpt'          => wp_strip_all_tags($post['post_excerpt'] ?? '', true),
            'cover'            => get_the_post_thumbnail_url($post['ID']),
            'taxonomy'         => $this->filterTerms($post),

            'password'         => (string) ($post['post_password'] ?? ''),
            'status'           => (string) ($post['post_status'] ?? ''),
            'comment_status'   => (string) ($post['comment_status'] ?? ''),
            'comment_count'    => (int) ($post['comment_count'] ?? 0),
        ];
        return $respect;
    }

    private function normalizeExtra(array $row): array
    {
        $respect = [
            'post_id'         => (int) ($row['post_id'] ?? 0),
            'view_count'      => (int) ($row['view_count'] ?? 0),
            'like_count'      => (int) ($row['like_count'] ?? 0),
            'dislike_count'   => (int) ($row['dislike_count'] ?? 0),
            'share_count'     => (int) ($row['share_count'] ?? 0),
            'favorite_count'  => (int) ($row['favorite_count'] ?? 0),
            'reading_time'    => (int) ($row['reading_time'] ?? 0),
            'seo_title'       => (string) ($row['seo_title'] ?? ''),
            'seo_description' => (string) ($row['seo_description'] ?? ''),
            'seo_keywords'    => (string) ($row['seo_keywords'] ?? ''),
            'gallery'         => Type::jsonToArray($row['gallery'] ?? ''),
            'property'        => Type::jsonToArray($row['property'] ?? ''),
            'ext'             => Type::jsonToArray($row['ext'] ?? ''),
        ];
        return $respect;
    }

    private function normalizeExtraForSave(array $data): array
    {
        $normalized = [];

        foreach (['view_count', 'like_count', 'dislike_count', 'share_count', 'favorite_count', 'reading_time'] as $key) {
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
                $normalized[$key] = Type::arrayToJson($data[$key]);
            }
        }

        return $normalized;
    }

    private function extraFormats(array $keys): array
    {
        $formats = [];
        foreach ($keys as $key) {
            $formats[] = in_array($key, ['view_count', 'like_count', 'dislike_count', 'share_count', 'favorite_count', 'reading_time'], true) ? '%d' : '%s';
        }

        return $formats;
    }

    private function filterTerms(array $postData): array
    {
        $result = [];

        if ($postData['ID'] > 0 && $postData['post_type'] !== '') {
            $taxonomyNames = get_object_taxonomies($postData['post_type'], 'names');
            if (is_array($taxonomyNames)) {
                foreach ($taxonomyNames as $taxonomyName) {
                    $terms = get_the_terms($postData['ID'], $taxonomyName);
                    if (empty($terms) || is_wp_error($terms)) {
                        continue;
                    }

                    $result[$taxonomyName] = [];
                    foreach ($terms as $term) {
                        if (!($term instanceof WP_Term)) {
                            continue;
                        }

                        $termLink = get_term_link($term);
                        if (is_wp_error($termLink)) {
                            $termLink = '';
                        }

                        $result[$taxonomyName][] = [
                            'name' => $term->name,
                            'url'  => (string) $termLink,
                        ];
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Get SEO Title
     * 
     * 获取 SEO 标题
     * 
     * Filter: g3_filter_title
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

        if (!is_404() && $paged > 1) {
            $afterTitle  = ' ' . sprintf(__('Page %s', 'G3'), $paged);
            $title      .= $afterTitle;
        }

        /**
         * Filter: g3_filter_title
         * @param  string $title
         * @return string
         */
        $title = apply_filters('g3_filter_title', $title);

        $home = $_SERVER['REQUEST_URI'] === '/' || $_SERVER['REQUEST_URI'] === '/index.php' || is_home() || $this->isOaLogin();

        return $home ? $title : $title . ' - ' . $siteName;
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
            $post        = $this->init(get_queried_object_id())->cache();
            $description = empty($post['seo_description']) ? $post['excerpt'] : $post['seo_description'];
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
         * @param  string $description
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
     * @return string
     */
    public function getKeywords(): string
    {
        $keywords = '';
        $siteName = get_bloginfo('name');

        if (is_home() || is_front_page()) {
            $seo      = get_option(SystemService::SEO_OPTION_KEY, []);
            $keywords = $seo['keywords'] ?? '';
            $keywords = empty($keywords) ? $siteName : $keywords;
        } elseif (is_singular()) {
            $keywords = $this->getExtra(get_queried_object_id())['seo_keywords'] ?? '';
            if (empty($keywords)) {
                // get post_tag if no data in self::KEYWORDS_KEY
                $terms = get_the_terms(get_queried_object_id(), 'post_tag');
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

        /**
         * Custom filter: g3_filter_breadcrumb_items
         * @param array $items
         * [
         *     ["label"=>'', "url"=>'', "active"=>false],
         *     ...
         * ]
         * @return array
         */
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
                $html .= '<li class="is-active"><a>' . __('Details') . '</a></li>';
            } else {
                $html .= '<li><a href="' . esc_url($url !== '' ? $url : '#') . '">' . $label . '</a></li>';
            }
        }
        $html .= '</ul></div>';

        /**
         * Custom filter: g3_filter_breadcrumb_html
         * 
         * @param string $html Breadcrumb HTML
         * @param array $items Breadcrumb items
         * @return string Modified breadcrumb HTML
         */
        return (string) apply_filters('g3_filter_breadcrumb_html', $html, $items);
    }

    /**
     * Calculate reading time
     * 
     * 计算文章阅读时间（秒）
     * 
     * @param string $content Support HTML
     * @param int $cnSpeed Letters per minute. Default 400
     * @param int $enSpeed Words per minute. Default 200
     * @return int Minimum reading time is 1 second
     */
    public function calculateReadingTime(string $content, int $cnSpeed = 400, int $enSpeed = 200): int
    {
        $prepared = $this->prepareReadingContent($content);
        $text     = $prepared['text'];

        if ($text === '') {
            return 0;
        }

        $stats   = $this->scanReadingText($text, (int) $prepared['code_blocks']);
        $cnSpeed = $this->readingSpeed($cnSpeed, $stats);
        $enSpeed = $this->readingSpeed($enSpeed, $stats);

        $totalMinutes = ($stats['cn'] / $cnSpeed) + ($stats['en'] / $enSpeed);
        $totalSeconds = (int) ceil($totalMinutes * 60);

        return max(1, $totalSeconds);
    }

    /**
     * Prepare content for reading-time calculation.
     */
    private function prepareReadingContent(string $content): array
    {
        $codeBlocks  = 0;
        $codeBlocks += preg_match_all('/```[\s\S]*?```/u', $content) ?: 0;
        $codeBlocks += preg_match_all('/<\s*(?:pre|code)\b[^>]*>[\s\S]*?<\s*\/\s*(?:pre|code)\s*>/iu', $content) ?: 0;

        $text = preg_replace('/<\s*(?:script|style)\b[^>]*>[\s\S]*?<\s*\/\s*(?:script|style)\s*>/iu', ' ', $content) ?? '';
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\[[^\]]+\]/', '', $text) ?? '';
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

        return [
            'text'        => $text,
            'code_blocks' => $codeBlocks,
        ];
    }

    /**
     * Scan normalized text for Chinese characters, English words and complexity hints.
     */
    private function scanReadingText(string $text, int $codeBlocks = 0): array
    {
        $cnCount          = preg_match_all('/\p{Han}/u', $text) ?: 0;
        $punctuationCount = preg_match_all('/[\x{3001}\x{3002}\x{FF01}\x{FF1F}\x{FF1A}\x{FF1B}\x{201C}\x{201D}\x{2018}\x{2019}\x{FF08}\x{FF09}]/u', $text) ?: 0;
        $codeSymbolCount  = preg_match_all('/[{}()\[\];=<>+*\/]|::|->|=>/u', $text) ?: 0;
        $enCount          = 0;
        $technicalTokens  = 0;
        $camelTokens      = 0;

        preg_match_all('/[A-Za-z]+(?:[\'-][A-Za-z]+)*|[0-9]+(?:\.[0-9]+)?/u', $text, $matches);
        foreach ($matches[0] as $token) {
            $enCount += $this->englishTokenWeight($token);

            $lower = strtolower($token);
            if (isset(self::READING_TECH_KEYWORDS[$lower])) {
                $technicalTokens++;
                continue;
            }

            if (preg_match('/[a-z][A-Z]/', $token)) {
                $camelTokens++;
            }
        }

        $techScore = min(6, ($codeBlocks * 2) + $technicalTokens + $camelTokens);

        return [
            'cn'                => $cnCount,
            'en'                => $enCount,
            'tech_score'        => $techScore + min(2, intdiv($codeSymbolCount, 6)),
            'punctuation_count' => $punctuationCount,
            'code_symbol_count' => $codeSymbolCount,
            'code_blocks'       => $codeBlocks,
        ];
    }

    /**
     * Count English words in text, including camel case words
     */
    private function englishTokenWeight(string $token): int
    {
        $count = 1;

        if (preg_match('/[a-z][A-Z]/', $token)) {
            $split  = preg_split(
                '/(?<=[a-z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/',
                $token
            );
            $count += max(0, count($split ?: []) - 1);
        }

        return $count;
    }

    /**
     * Adjust reading speed based on text complexity.
     */
    private function readingSpeed(int $baseSpeed, array $stats): int
    {
        $speed     = max(50, min(1000, $baseSpeed));
        $techScore = (int) ($stats['tech_score'] ?? 0);

        if ($techScore >= 4) {
            $speed = (int) ($speed * 0.6);
        } elseif ($techScore >= 2) {
            $speed = (int) ($speed * 0.75);
        } elseif ($techScore >= 1) {
            $speed = (int) ($speed * 0.9);
        }

        if ($techScore === 0 && (int) ($stats['punctuation_count'] ?? 0) > 50) {
            $speed = (int) ($speed * 1.1);
        }

        return max(50, min(1000, $speed));
    }

    public function htmlTaxonomy(string $term = 'category', bool $isBlank = false)
    {
        if (
            !isset($this->cache['taxonomy'][$term])
            || empty($this->cache['taxonomy'][$term])
        ) {
            return '';
        }

        $target = $isBlank ? '_blank' : '_self';

        $html = '';
        foreach ($this->cache['taxonomy'][$term] as $termItem) {
            $url   = $termItem['url'];
            $name  = $termItem['name'];
            $html .= <<<HTML
            <a class="post-taxonomy-{$term}" href="{$url}" target="{$target}" title="{$name}">{$name}</a>
        HTML;
        }
        return $html;
    }
}
