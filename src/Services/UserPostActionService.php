<?php
namespace JEALER\G3\Services;
use JEALER\G3\Core\Service\Service;
use JEALER\G3\Utilities\Date;
use WP_Error;
use WP_Post;

class UserPostActionService extends Service {
    const TABLE              = 'g3_user_post_action';
    const STATUS_CACHE_GROUP = 'g3_user_post_action';
    private const ACTIONS            = [
        'like'     => true,
        'dislike'  => true,
        'favorite' => true,
        'share'    => true,
    ];

    public string $table;

    public function __construct()
    {
        parent::__construct();
        $this->table = $this->wpdb->prefix . self::TABLE;
    }

    /**
     * Set User Post Action Status
     * 
     * 设置用户对文章的行为状态
     * 
     * @param int $userId 用户ID
     * @param int|WP_Post $post 文章ID或文章对象
     * @param string $action 行为类型
     * @param int $value 行为状态，1为有效，0为取消
     * @return array|WP_Error 行为状态数组或错误对象
     */
    public function set(int $userId, int|WP_Post $post, string $action, int $value): array|WP_Error
    {
        $userId = max(0, $userId);
        if ($userId <= 0) {
            return new WP_Error('invalid_user_id', 'invalid user ID', [
                'status'  => 400,
                'user_id' => $userId,
            ]);
        }

        $postId = $this->postId($post);
        if ($postId <= 0 || !get_post($postId)) {
            return new WP_Error('invalid_post_id', 'invalid post ID', [
                'status'  => 400,
                'post_id' => $postId,
            ]);
        }

        $action = sanitize_key($action);
        if (!isset(self::ACTIONS[$action])) {
            return new WP_Error('invalid_action', 'invalid post action', [
                'status' => 400,
                'action' => $action,
            ]);
        }

        $nextValue = $value > 0 ? 1 : 0;
        $status    = $this->allStatus($userId, $postId);
        $prevValue = !empty($status[$action]) ? 1 : 0;
        $now       = Date::utcDateTime();

        if ($prevValue === $nextValue && $nextValue === 0) {
            return [
                'user_id'    => $userId,
                'post_id'    => $postId,
                'action'     => $action,
                'active'     => false,
                'value'      => 0,
                'previous'   => $prevValue,
                'delta'      => 0,
                'changed'    => false,
                'updated_at' => $now,
            ];
        }

        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "INSERT INTO `{$this->table}` (`user_id`, `post_id`, `action`, `value`, `created_at`, `updated_at`)
                VALUES (%d, %d, %s, %d, %s, %s)
                ON DUPLICATE KEY UPDATE
                    `value` = VALUES(`value`),
                    `updated_at` = VALUES(`updated_at`)",
                $userId,
                $postId,
                $action,
                $nextValue,
                $now,
                $now
            )
        );

        if (false === $result) {
            return new WP_Error('update_failed', 'update failed for user post action', [
                'status'  => 500,
                'user_id' => $userId,
                'post_id' => $postId,
                'action'  => $action,
            ]);
        }

        $status[$action] = $nextValue === 1;
        $this->setStatusCache($userId, $postId, $status);

        return [
            'user_id'    => $userId,
            'post_id'    => $postId,
            'action'     => $action,
            'active'     => $nextValue === 1,
            'value'      => $nextValue,
            'previous'   => $prevValue,
            'delta'      => $nextValue - $prevValue,
            'changed'    => $nextValue !== $prevValue,
            'updated_at' => $now,
        ];
    }

    /**
     * Get User Post Action Status
     * 
     * 获取用户对文章的行为状态
     * 
     * @param int $userId 用户ID
     * @param int|WP_Post $post 文章ID或文章对象
     * @param string[] $actions 行为类型数组
     * @return array 行为状态数组
     */
    public function status(int $userId, int|WP_Post $post, array $actions = ['like', 'favorite']): array
    {
        $userId = max(0, $userId);
        $postId = $this->postId($post);

        $status = [];
        foreach ($actions as $action) {
            $action = sanitize_key((string) $action);
            if (isset(self::ACTIONS[$action])) {
                $status[$action] = false;
            }
        }

        if ($userId <= 0 || $postId <= 0 || !$status) return $status;

        $allStatus = $this->allStatus($userId, $postId);

        foreach (array_keys($status) as $action) {
            $status[$action] = !empty($allStatus[$action]);
        }

        return $status;
    }

    /**
     * List User Post Actions
     * 
     * 获取用户对文章的行为列表
     * 
     * @param int $userId 用户ID
     * @param string $action 行为类型
     * @param int $paged 页码
     * @param int $perPage 每页数量
     * @return array|WP_Error 行为列表数组或错误对象
     */
    public function listPosts(int $userId, string $action, int $paged = 1, int $perPage = 20): array|WP_Error
    {
        $userId = max(0, $userId);
        if ($userId <= 0) {
            return new WP_Error('invalid_user_id', 'invalid user ID', [
                'status'  => 400,
                'user_id' => $userId,
            ]);
        }

        $action = sanitize_key($action);
        if (!isset(self::ACTIONS[$action])) {
            return new WP_Error('invalid_action', 'invalid post action', [
                'status' => 400,
                'action' => $action,
            ]);
        }

        $paged   = max(1, $paged);
        $perPage = min(100, max(1, $perPage));
        $offset  = ($paged - 1) * $perPage;
        $total   = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM `{$this->table}` a
                INNER JOIN `{$this->wpdb->posts}` p ON p.`ID` = a.`post_id`
                WHERE a.`user_id` = %d
                    AND a.`action` = %s
                    AND a.`value` = 1
                    AND p.`post_status` = 'publish'",
                $userId,
                $action
            )
        );

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    a.`post_id`,
                    a.`action`,
                    a.`updated_at`,
                    p.`post_title`,
                    p.`post_name`,
                    p.`post_type`,
                    p.`post_date`,
                    p.`post_date_gmt`,
                    p.`post_author`
                FROM `{$this->table}` a
                INNER JOIN `{$this->wpdb->posts}` p ON p.`ID` = a.`post_id`
                WHERE a.`user_id` = %d
                    AND a.`action` = %s
                    AND a.`value` = 1
                    AND p.`post_status` = 'publish'
                ORDER BY a.`updated_at` DESC
                LIMIT %d OFFSET %d",
                $userId,
                $action,
                $perPage,
                $offset
            ),
            ARRAY_A
        );

        $data = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $postId = (int) ($row['post_id'] ?? 0);
            $data[] = [
                'post_id'       => $postId,
                'action'        => (string) ($row['action'] ?? ''),
                'updated_at'    => (string) ($row['updated_at'] ?? ''),
                'title'         => (string) ($row['post_title'] ?? ''),
                'slug'          => (string) ($row['post_name'] ?? ''),
                'post_type'     => (string) ($row['post_type'] ?? ''),
                'created_at'    => (string) ($row['post_date_gmt'] ?? ''),
                'created_local' => (string) ($row['post_date'] ?? ''),
                'author_id'     => (int) ($row['post_author'] ?? 0),
                'url'           => $postId > 0 ? get_permalink($postId) : '',
                'cover'         => $postId > 0 ? get_the_post_thumbnail_url($postId) : '',
            ];
        }

        return [
            'data'           => $data,
            'found_posts'    => $total,
            'max_num_pages'  => (int) ceil($total / $perPage),
            'paged'          => $paged,
            'posts_per_page' => $perPage,
        ];
    }

    private function allStatus(int $userId, int $postId): array
    {
        $cached = wp_cache_get($this->statusCacheKey($userId, $postId), self::STATUS_CACHE_GROUP);
        if (is_array($cached)) {
            return $this->normalizeStatus($cached);
        }

        $status = $this->emptyStatus();
        $rows   = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT `action`, `value` FROM `{$this->table}` WHERE `user_id` = %d AND `post_id` = %d",
                $userId,
                $postId
            ),
            ARRAY_A
        );

        foreach (is_array($rows) ? $rows : [] as $row) {
            $action = sanitize_key((string) ($row['action'] ?? ''));
            if (isset(self::ACTIONS[$action])) {
                $status[$action] = (int) ($row['value'] ?? 0) === 1;
            }
        }

        $this->setStatusCache($userId, $postId, $status);
        return $status;
    }

    private function emptyStatus(): array
    {
        return array_fill_keys(array_keys(self::ACTIONS), false);
    }

    private function normalizeStatus(array $status): array
    {
        $normalized = $this->emptyStatus();

        foreach ($normalized as $action => $_) {
            $normalized[$action] = !empty($status[$action]);
        }

        return $normalized;
    }

    private function setStatusCache(int $userId, int $postId, array $status): void
    {
        wp_cache_set(
            $this->statusCacheKey($userId, $postId),
            $this->normalizeStatus($status),
            self::STATUS_CACHE_GROUP,
            WEEK_IN_SECONDS
        );
    }

    private function statusCacheKey(int $userId, int $postId): string
    {
        return $userId . ':' . $postId;
    }

    private function postId(int|WP_Post $post): int
    {
        if ($post instanceof WP_Post) {
            return (int) $post->ID;
        }

        return is_int($post) ? $post : 0;
    }
}
