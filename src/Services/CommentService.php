<?php
namespace JEALER\G3\Services;
use JEALER\G3\Core\Service\Service;
use JEALER\G3\Services\UserService;
use JEALER\G3\Traits\Cache;
use JEALER\G3\Utilities\Date;
use JEALER\G3\Utilities\Frontend;
use Redis;
use Throwable;
use WP_Comment;
use WP_Comment_Query;
use WP_Error;
use WP_Post;
use WP_User;

class CommentService extends Service {
    use Cache;

    const META_LIKE          = 'like';
    const META_DISLIKE       = 'dislike';
    const META_REPLY_TO      = 'g3_reply_to';
    const META_REACTION      = 'g3_reaction_';
    const MAX_CONTENT_LENGTH = 1200;
    const CONFIG_CACHE_TTL   = WEEK_IN_SECONDS * 1000;

    private UserService $userService;

    public function __construct()
    {
        parent::__construct();
        $this->userService = $this->container->get(UserService::class);
    }

    public static function commentBlock(int $postId, int $userId = 0, bool $customCSS = false): string
    {
        if (!$customCSS) {
            Frontend::css('g3.comment');
        }
        Frontend::esm('g3.comment');

        return '<div data-component="post-comment-block" class="g3-comment-block" data-post-id="' . esc_attr((string) $postId) . '" data-user-id="' . esc_attr((string) $userId) . '"></div>';
    }

    public function config(): array
    {
        return [
            'comments_per_page' => $this->commentsPerPage(),
            'max_length'        => self::MAX_CONTENT_LENGTH,
            'config_cache_ttl'  => self::CONFIG_CACHE_TTL,
        ];
    }

    public function list(int $postId, int $page = 1, string $sort = 'latest', int $userId = 0): array|WP_Error
    {
        $post = $this->publicPost($postId);
        if ($post instanceof WP_Error) return $post;

        $page    = max(1, $page);
        $perPage = $this->commentsPerPage();
        $sort    = $sort === 'popular' ? 'popular' : 'latest';
        $userId  = max(0, $userId);
        $key     = $this->generateArrayCacheKey([
            'post_id'  => $postId,
            'page'     => $page,
            'per_page' => $perPage,
            'sort'     => $sort,
            'scope'    => 'roots',
            'user_id'  => $userId,
        ]);

        $cached = $this->queryCacheGet($postId, $key);
        if ($cached !== false) return $this->withPostCommentState($cached, $postId);

        $query     = new WP_Comment_Query();
        $rootTotal = (int) $query->query([
            'post_id' => $postId,
            'status'  => 'approve',
            'type'    => 'comment',
            'parent'  => 0,
            'count'   => true,
        ]);

        $comments = $query->query([
            'post_id' => $postId,
            'status'  => 'approve',
            'type'    => 'comment',
            'parent'  => 0,
            'number'  => $perPage,
            'offset'  => ($page - 1) * $perPage,
            'orderby' => $sort === 'popular' ? 'comment_karma' : 'comment_date_gmt',
            'order'   => 'DESC',
        ]);

        $rootIds     = array_values(array_filter(array_map(
            static fn($comment) => $comment instanceof WP_Comment ? (int) $comment->comment_ID : 0,
            is_array($comments) ? $comments : []
        )));
        $replyCounts = $this->descendantCounts($rootIds, $postId);
        $items       = [];

        foreach (is_array($comments) ? $comments : [] as $comment) {
            if ($comment instanceof WP_Comment) {
                $items[] = $this->normalize($comment, [
                    'root_id'     => (int) $comment->comment_ID,
                    'reply_count' => $replyCounts[(int) $comment->comment_ID] ?? 0,
                    'include_to'  => false,
                    'user_id'     => $userId,
                ]);
            }
        }

        $result = [
            'items'      => $items,
            'pagination' => $this->pagination($page, $perPage, $rootTotal),
            'total'      => $this->totalComments($postId),
            'sort'       => $sort,
        ];

        $this->queryCacheSet($postId, $key, $result, HOUR_IN_SECONDS);
        return $this->withPostCommentState($result, $postId);
    }

    public function replies(int $commentId, int $page = 1, int $userId = 0): array|WP_Error
    {
        $userId = max(0, $userId);
        if ($userId <= 0) {
            return new WP_Error('login_required', 'Please log in to view replies.', ['status' => 401]);
        }

        $root = get_comment($commentId);
        if (!$root instanceof WP_Comment || (int) $root->comment_parent > 0 || !$this->isApproved($root)) {
            return new WP_Error('comment_not_found', __('No Comments'), ['status' => 404]);
        }

        $post = $this->publicPost((int) $root->comment_post_ID);
        if ($post instanceof WP_Error) return $post;

        $page    = max(1, $page);
        $perPage = $this->commentsPerPage();
        $postId  = (int) $root->comment_post_ID;
        $key     = $this->generateArrayCacheKey([
            'comment_id' => $commentId,
            'page'       => $page,
            'per_page'   => $perPage,
            'scope'      => 'replies',
            'user_id'    => $userId,
        ]);

        $cached = $this->queryCacheGet($postId, $key);
        if ($cached !== false) return $cached;

        $all   = $this->flattenReplies($commentId, (int) $root->comment_post_ID);
        $total = count($all);
        $slice = array_slice($all, ($page - 1) * $perPage, $perPage);
        $items = [];

        foreach ($slice as $comment) {
            $replyToId = $this->replyToId($comment);
            $items[]   = $this->normalize($comment, [
                'root_id'     => $commentId,
                'reply_count' => 0,
                'reply_to'    => $replyToId > 0 ? $this->commentUser($replyToId) : null,
                'include_to'  => true,
                'user_id'     => $userId,
            ]);
        }

        $result = [
            'items'      => $items,
            'pagination' => $this->pagination($page, $perPage, $total),
            'total'      => $this->totalComments($postId),
        ];

        $this->queryCacheSet($postId, $key, $result, HOUR_IN_SECONDS);
        return $result;
    }

    public function create(array $data): array|WP_Error
    {
        $user = wp_get_current_user();
        if (!$user instanceof WP_User || (int) $user->ID <= 0) {
            return new WP_Error('login_required', 'Please log in before commenting.', ['status' => 401]);
        }

        $postId   = (int) ($data['post_id'] ?? 0);
        $parentId = max(0, (int) ($data['parent_id'] ?? 0));
        $content  = trim((string) ($data['content'] ?? ''));

        $post = $this->publicPost($postId);
        if ($post instanceof WP_Error) return $post;

        $allowed = $this->canComment($postId, $parentId, $content, $user);
        if ($allowed instanceof WP_Error) return $allowed;

        $rootId    = 0;
        $replyToId = 0;
        if ($parentId > 0) {
            $parent = get_comment($parentId);
            if (!$parent instanceof WP_Comment || !$this->isApproved($parent) || (int) $parent->comment_post_ID !== $postId) {
                return new WP_Error('parent_not_found', 'Parent comment not found.', ['status' => 404]);
            }
            $rootId    = $this->rootCommentId($parent);
            $replyToId = $parentId;
        }

        $commentId = wp_new_comment([
            'comment_post_ID'      => $postId,
            'comment_parent'       => $rootId,
            'comment_content'      => wp_kses_post($content),
            'comment_author'       => $user->display_name ?: $user->user_login,
            'comment_author_email' => $user->user_email,
            'comment_author_url'   => $user->user_url,
            'user_id'              => (int) $user->ID,
            'comment_type'         => 'comment',
        ], true);

        if ($commentId instanceof WP_Error) {
            return $this->withStatus($commentId);
        }

        if (!$commentId) {
            return new WP_Error('comment_create_failed', 'Failed to create comment.', ['status' => 500]);
        }

        if ($replyToId > 0) {
            update_comment_meta((int) $commentId, self::META_REPLY_TO, $replyToId);
            $this->recalculateKarma($rootId);
        } else {
            $this->recalculateKarma((int) $commentId);
        }
        $this->flushQueryCache($postId);

        $comment = get_comment((int) $commentId);
        if (!$comment instanceof WP_Comment) {
            return new WP_Error('comment_create_failed', 'Failed to create comment.', ['status' => 500]);
        }

        return [
            'comment' => $this->normalize($comment, [
                'root_id'     => $rootId ?: (int) $commentId,
                'reply_count' => 0,
                'reply_to'    => $replyToId > 0 ? $this->commentUser($replyToId) : null,
                'include_to'  => $replyToId > 0,
            ]),
            'status'  => $this->status($comment),
            'message' => $this->status($comment) === 'approved'
                ? __('Published')
                : __('Your comment is awaiting moderation.'),
        ];
    }

    public function react(int $commentId, string $reaction): array|WP_Error
    {
        $userId = $this->currentUserId();
        if ($userId <= 0) {
            return new WP_Error('login_required', 'Please log in before reacting.', ['status' => 401]);
        }

        if (!in_array($reaction, ['like', 'dislike', 'none'], true)) {
            return new WP_Error('invalid_reaction', 'Invalid reaction.', ['status' => 400]);
        }

        $comment = get_comment($commentId);
        if (!$comment instanceof WP_Comment || !$this->isApproved($comment)) {
            return new WP_Error('comment_not_found', __('No Comments'), ['status' => 404]);
        }

        $metaKey = self::META_REACTION . $userId;
        $old     = (string) get_comment_meta($commentId, $metaKey, true);
        if (!in_array($old, ['like', 'dislike'], true)) {
            $old = 'none';
        }

        $next = $old === $reaction ? 'none' : $reaction;
        if ($old !== 'none') {
            $this->incrementMeta($commentId, $old, -1);
        }
        if ($next !== 'none') {
            $this->incrementMeta($commentId, $next, 1);
            update_comment_meta($commentId, $metaKey, $next);
        } else {
            delete_comment_meta($commentId, $metaKey);
        }

        $this->recalculateKarma($commentId);
        if ((int) $comment->comment_parent > 0) {
            $this->recalculateKarma($this->rootCommentId($comment));
        }
        $this->flushQueryCache((int) $comment->comment_post_ID);

        $updated = get_comment($commentId);

        return [
            'id'       => $commentId,
            'reaction' => $next,
            'like'     => $this->metaCount($commentId, self::META_LIKE),
            'dislike'  => $this->metaCount($commentId, self::META_DISLIKE),
            'karma'    => $updated instanceof WP_Comment ? (int) $updated->comment_karma : 0,
        ];
    }

    private function canComment(int $postId, int $parentId, string $content, WP_User $user): true|WP_Error
    {
        if (!comments_open($postId)) {
            return new WP_Error('comments_closed', __('Comments are closed.'), ['status' => 403]);
        }

        if (get_option('comment_registration') === '1' && (int) $user->ID <= 0) {
            return new WP_Error('login_required', 'Please log in before commenting.', ['status' => 401]);
        }

        if ($parentId > 0 && get_option('thread_comments') !== '1') {
            return new WP_Error('thread_comments_closed', 'Replies are disabled.', ['status' => 403]);
        }

        if ($content === '') {
            return new WP_Error('empty_comment', 'Comment content is empty.', ['status' => 400]);
        }

        if (mb_strlen(wp_strip_all_tags($content), 'UTF-8') > self::MAX_CONTENT_LENGTH) {
            return new WP_Error('comment_too_long', 'Comment content is too long.', ['status' => 400]);
        }

        $blocked = wp_check_comment_disallowed_list(
            $user->display_name ?: $user->user_login,
            $user->user_email,
            $user->user_url,
            $content,
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
        );

        if ($blocked) {
            return new WP_Error('comment_blocked', 'Comment contains disallowed words.', ['status' => 403]);
        }

        return true;
    }

    private function normalize(WP_Comment $comment, array $args = []): array
    {
        $id       = (int) $comment->comment_ID;
        $rootId   = (int) ($args['root_id'] ?? $this->rootCommentId($comment));
        $replyTo  = $args['reply_to'] ?? null;
        $reaction = $this->currentUserReaction($id, (int) ($args['user_id'] ?? 0));

        return [
            'id'           => $id,
            'post_id'      => (int) $comment->comment_post_ID,
            'parent_id'    => (int) $comment->comment_parent > 0 ? $rootId : 0,
            'root_id'      => $rootId,
            'reply_to_id'  => $this->replyToId($comment),
            'user'         => $this->user($comment),
            'reply_to'     => ($args['include_to'] ?? false) ? $replyTo : null,
            'content'      => wp_kses_post($comment->comment_content),
            'datetime'     => (string) $comment->comment_date,
            'human_time'   => Date::humanTime(strtotime((string) $comment->comment_date)),
            'datetime_utc' => (string) $comment->comment_date_gmt,
            'status'       => $this->status($comment),
            'like'         => $this->metaCount($id, self::META_LIKE),
            'dislike'      => $this->metaCount($id, self::META_DISLIKE),
            'reaction'     => $reaction,
            'reply_count'  => max(0, (int) ($args['reply_count'] ?? 0)),
            'karma'        => (int) $comment->comment_karma,
        ];
    }

    private function user(WP_Comment $comment): array
    {
        return $this->userById((int) $comment->user_id, (string) $comment->comment_author);
    }

    private function commentUser(int $commentId): ?array
    {
        $comment = get_comment($commentId);
        return $comment instanceof WP_Comment ? $this->user($comment) : null;
    }

    private function userById(int $userId, string $fallbackName = ''): array
    {
        if ($userId > 0) {
            $service = $this->userService->init($userId);
            if ($service instanceof UserService) {
                $user = $service->cache();
                return [
                    'user_id'  => (int) ($user['user_id'] ?? $userId),
                    'avatar'   => (string) ($user['avatar'] ?? ''),
                    'nickname' => (string) ($user['nickname'] ?? $fallbackName),
                    'url'      => $service->homeUrl(),
                    'slug'     => (string) ($user['slug'] ?? ''),
                ];
            }
        }

        return [
            'user_id'  => 0,
            'avatar'   => UserService::getDefaultAvatar(),
            'nickname' => $fallbackName !== '' ? $fallbackName : __('Guest', 'G3'),
            'url'      => '',
            'slug'     => '',
        ];
    }

    private function publicPost(int $postId): WP_Post|WP_Error
    {
        $post = get_post($postId);
        if (!$post instanceof WP_Post || $post->post_status !== 'publish') {
            return new WP_Error('post_not_found', 'Post not found.', ['status' => 404]);
        }
        return $post;
    }

    private function commentsPerPage(): int
    {
        $perPage = (int) get_option('comments_per_page');
        return min(100, max(1, $perPage > 0 ? $perPage : 10));
    }

    private function totalComments(int $postId): int
    {
        $query = new WP_Comment_Query();
        return (int) $query->query([
            'post_id' => $postId,
            'status'  => 'approve',
            'type'    => 'comment',
            'count'   => true,
        ]);
    }

    private function withPostCommentState(array $result, int $postId): array
    {
        unset($result['settings']);
        $result['comments_open'] = comments_open($postId);
        return $result;
    }

    private function pagination(int $page, int $perPage, int $total): array
    {
        return [
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    private function status(WP_Comment $comment): string
    {
        return match ((string) $comment->comment_approved) {
            '1'     => 'approved',
            '0'     => 'pending',
            'spam'  => 'spam',
            'trash' => 'trash',
            default => (string) $comment->comment_approved,
        };
    }

    private function isApproved(WP_Comment $comment): bool
    {
        return (string) $comment->comment_approved === '1';
    }

    private function rootCommentId(WP_Comment $comment): int
    {
        $current = $comment;
        $guard   = 0;
        while ((int) $current->comment_parent > 0 && $guard < 50) {
            $parent = get_comment((int) $current->comment_parent);
            if (!$parent instanceof WP_Comment) break;
            $current = $parent;
            $guard++;
        }
        return (int) $current->comment_ID;
    }

    private function replyToId(WP_Comment $comment): int
    {
        $meta = (int) get_comment_meta((int) $comment->comment_ID, self::META_REPLY_TO, true);
        return $meta > 0 ? $meta : (int) $comment->comment_parent;
    }

    private function descendantCounts(array $rootIds, int $postId): array
    {
        $rootIds = array_values(array_unique(array_filter(array_map('intval', $rootIds))));
        if (!$rootIds) return [];

        $counts    = array_fill_keys($rootIds, 0);
        $frontier  = $rootIds;
        $parentMap = [];
        foreach ($rootIds as $id) {
            $parentMap[$id] = $id;
        }

        while ($frontier) {
            $rows = $this->childRows($frontier, $postId);
            if (!$rows) break;

            $frontier = [];
            foreach ($rows as $row) {
                $id     = (int) $row['comment_ID'];
                $parent = (int) $row['comment_parent'];
                $root   = $parentMap[$parent] ?? 0;
                if ($root <= 0) continue;
                $counts[$root]  = ($counts[$root] ?? 0) + 1;
                $parentMap[$id] = $root;
                $frontier[]     = $id;
            }
        }

        return $counts;
    }

    /**
     * @return array<int,WP_Comment>
     */
    private function flattenReplies(int $rootId, int $postId): array
    {
        $items    = [];
        $frontier = [$rootId];
        $seen     = [$rootId => true];

        while ($frontier) {
            $rows = $this->childRows($frontier, $postId);
            if (!$rows) break;

            $frontier = [];
            foreach ($rows as $row) {
                $id = (int) $row['comment_ID'];
                if (isset($seen[$id])) continue;
                $seen[$id] = true;
                $comment   = get_comment($id);
                if ($comment instanceof WP_Comment) {
                    $items[]    = $comment;
                    $frontier[] = $id;
                }
            }
        }

        usort($items, static function (WP_Comment $a, WP_Comment $b): int {
            return strcmp((string) $a->comment_date_gmt, (string) $b->comment_date_gmt)
                ?: ((int) $a->comment_ID <=> (int) $b->comment_ID);
        });
        return $items;
    }

    private function childRows(array $parentIds, int $postId): array
    {
        $parentIds = array_values(array_unique(array_filter(array_map('intval', $parentIds))));
        if (!$parentIds) return [];

        $placeholders = implode(',', array_fill(0, count($parentIds), '%d'));
        $params       = array_merge([$postId], $parentIds);
        $sql          = $this->wpdb->prepare(
            "SELECT comment_ID, comment_parent FROM {$this->wpdb->comments}
             WHERE comment_post_ID = %d
             AND comment_parent IN ($placeholders)
             AND comment_approved = '1'
             AND comment_type = 'comment'
             ORDER BY comment_date_gmt ASC, comment_ID ASC",
            ...$params
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    private function incrementMeta(int $commentId, string $key, int $step): int
    {
        $next = max(0, $this->metaCount($commentId, $key) + $step);
        update_comment_meta($commentId, $key, $next);
        return $next;
    }

    private function metaCount(int $commentId, string $key): int
    {
        return max(0, (int) get_comment_meta($commentId, $key, true));
    }

    private function currentUserReaction(int $commentId, int $userId = 0): string
    {
        if ($userId <= 0) return 'none';
        $value = (string) get_comment_meta($commentId, self::META_REACTION . $userId, true);
        return in_array($value, ['like', 'dislike'], true) ? $value : 'none';
    }

    private function recalculateKarma(int $commentId): void
    {
        $comment = get_comment($commentId);
        if (!$comment instanceof WP_Comment) return;

        $rootId     = $this->rootCommentId($comment);
        $replyCount = $rootId === $commentId
            ? ($this->descendantCounts([$rootId], (int) $comment->comment_post_ID)[$rootId] ?? 0)
            : 0;
        $karma      = ($this->metaCount($commentId, self::META_LIKE) * 3)
            + ($replyCount * 2)
            - ($this->metaCount($commentId, self::META_DISLIKE) * 2);

        $this->wpdb->update(
            $this->wpdb->comments,
            ['comment_karma' => $karma],
            ['comment_ID' => $commentId],
            ['%d'],
            ['%d']
        );
        clean_comment_cache([$commentId]);
    }

    private function currentUserId(): int
    {
        return (int) get_current_user_id();
    }

    private function queryCacheGet(int $postId, string $key): mixed
    {
        $redis = $this->redis();
        if (!$redis) return false;

        try {
            $value = $redis->hGet($this->queryCacheGroup($postId), $key);
            if (!is_string($value) || $value === '') return false;

            $data = unserialize($value, ['allowed_classes' => false]);
            return is_array($data) ? $data : false;
        }
        catch (Throwable) {
            return false;
        }
    }

    private function queryCacheSet(int $postId, string $key, array $value, int $ttl): void
    {
        $redis = $this->redis();
        if (!$redis) return;

        try {
            $group = $this->queryCacheGroup($postId);
            $redis->hSet($group, $key, serialize($value));
            if ($ttl > 0) {
                $redis->expire($group, $ttl);
            }
        }
        catch (Throwable) {
        }
    }

    private function flushQueryCache(int $postId): void
    {
        $redis = $this->redis();
        if (!$redis) return;

        try {
            $redis->del($this->queryCacheGroup($postId));
        }
        catch (Throwable) {
        }
    }

    private function queryCacheGroup(int $postId): string
    {
        return 'g3_comments_query_' . max(0, $postId);
    }

    private function redis(): ?Redis
    {
        /** @var RedisService $redisService */
        $redisService = $this->container->get(RedisService::class);
        return $redisService->init(DBService::COMMENT_REDIS_DB);
    }

    private function withStatus(WP_Error $error): WP_Error
    {
        $data = $error->get_error_data();
        if (is_int($data)) {
            $error->add_data(['status' => $data]);
        }
        return $error;
    }
}
