<?php
namespace JEALER\G3\Services;
use JEALER\G3\Core\IM\IM;
use JEALER\G3\Core\Service\Service;
use JEALER\G3\Services\RedisService;
use JEALER\G3\Services\IMRealtimeService;
use JEALER\G3\Utilities\Date;
use JEALER\G3\Utilities\System;
use JEALER\G3\Utilities\Type;
use Redis;
use Throwable;
use WP_Error;

class IMService extends Service {
    public const CACHE_GROUP             = 'g3_im';
    public const CONVERSATIONS_CACHE_KEY = 'g3_im:conversations';
    public const PARTICIPANTS_CACHE_KEY  = 'g3_im:participants';
    public const MESSAGES_CACHE_KEY      = 'g3_im:messages';
    public const EVENTS_CACHE_KEY        = 'g3_im:events';
    public const CACHE_TTL               = WEEK_IN_SECONDS;

    private const CONVERSATION_INDEX_KEY   = 'g3_im:conversations:index';
    private const EVENT_INDEX_KEY          = 'g3_im:events:index';
    private const CONVERSATION_SYNC_KEY    = 'g3_im:sync:conversations';
    private const PARTICIPANT_SYNC_KEY     = 'g3_im:sync:participants';
    private const MESSAGE_SYNC_KEY         = 'g3_im:sync:messages';
    private const CONVERSATION_COUNTER_KEY = 'g3_im:counters:conversation_id';
    private const PARTICIPANT_COUNTER_KEY  = 'g3_im:counters:participant_id';
    private const MESSAGE_COUNTER_KEY      = 'g3_im:counters:message_id';
    private const EVENT_COUNTER_KEY        = 'g3_im:counters:event_id';

    private string $conversationsTable;
    private string $participantsTable;
    private string $messagesTable;
    private ?Redis $redisClient        = null;

    protected function onInit(): void
    {
        $this->conversationsTable = $this->wpdb->prefix . 'g3_im_conversations';
        $this->participantsTable  = $this->wpdb->prefix . 'g3_im_participants';
        $this->messagesTable      = $this->wpdb->prefix . 'g3_im_messages';
    }

    public function createConversation(array $identity, array $data): int|WP_Error
    {
        if ($this->cacheStorageEnabled()) {
            return $this->createCachedConversation($identity, $data);
        }

        $now     = Date::utcDateTime();
        $type    = sanitize_key((string) ($data['type'] ?? IM::TYPE_CUSTOMER_SERVICE));
        $subject = trim(sanitize_text_field((string) ($data['subject'] ?? '')));
        if ($subject === '') {
            $subject = (string) ($identity['display_name'] ?? '');
        }

        $insert = [
            'type'       => $type,
            'subject'    => mb_substr($subject, 0, 255),
            'state'      => sanitize_key((string) ($data['state'] ?? IM::CONVERSATION_OPEN)),
            'source'     => sanitize_key((string) ($data['source'] ?? 'web')),
            'ip_address' => System::ip() ?: null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 255) : null,
            'meta'       => $this->encode($this->sanitizeMeta($data['meta'] ?? [])),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $result = $this->wpdb->insert($this->conversationsTable, $insert);
        if ($result === false) {
            return new WP_Error('db_insert_error', 'Failed to create conversation.', ['status' => 500]);
        }

        $conversationId = (int) $this->wpdb->insert_id;
        $this->ensureParticipant($conversationId, $identity);
        $this->publishEvent(IM::EVENT_CONVERSATION_CREATED, $conversationId, null, $identity, [
            'conversation_id' => $conversationId,
        ], $type);

        return $conversationId;
    }

    public function findOpenConversation(string $type, array $identity, array $states): ?array
    {
        $type         = sanitize_key($type);
        $states       = array_values(array_filter(array_map('sanitize_key', $states)));
        $placeholders = implode(',', array_fill(0, count($states), '%s'));
        if (!$type || !$states) {
            return null;
        }

        $sql    = "SELECT c.*
                   FROM {$this->conversationsTable} c
                   INNER JOIN {$this->participantsTable} p ON p.`conversation_id` = c.`id`
                   WHERE c.`type` = %s
                     AND c.`state` IN ({$placeholders})
                     AND p.`actor_type` = %s
                     AND p.`actor_id` = %s
                   ORDER BY c.`updated_at` DESC, c.`id` DESC LIMIT 1";
        $params = array_merge([$type], $states, [$identity['actor_type'], (string) $identity['actor_id']]);

        $row  = $this->wpdb->get_row($this->wpdb->prepare($sql, $params), ARRAY_A);
        $rows = is_array($row) ? [$row] : [];
        if ($this->cacheStorageEnabled()) {
            foreach ($this->cachedConversationRows($type) as $cached) {
                if (!in_array((string) ($cached['state'] ?? ''), $states, true)) {
                    continue;
                }
                if (!$this->cachedParticipantRowByActor((int) $cached['id'], $identity)) {
                    continue;
                }
                $rows[] = $cached;
            }
        }

        if (!$rows) {
            return null;
        }

        usort($rows, fn(array $a, array $b): int => strcmp(
            (string) ($b['updated_at'] ?? $b['created_at'] ?? ''),
            (string) ($a['updated_at'] ?? $a['created_at'] ?? '')
        ) ?: ((int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0)));

        return $rows[0];
    }

    public function sendMessage(int $conversationId, array $identity, string $msgType, mixed $content, array $options = []): array|WP_Error
    {
        $conversation = $this->getConversationRow($conversationId);
        if (!$conversation) {
            return new WP_Error('conversation_not_found', 'Conversation not found.', ['status' => 404]);
        }

        $msgType = sanitize_key($msgType) ?: IM::MESSAGE_TEXT;
        $body    = $this->normalizeMessageContent($msgType, $content);
        $preview = $this->messagePreview($msgType, $body, (string) ($options['preview'] ?? ''));
        if ($preview === '') {
            return new WP_Error('empty_message', __('Message content cannot be empty.', 'G3'), ['status' => 400]);
        }
        if (mb_strlen(wp_strip_all_tags($preview)) > 5000) {
            return new WP_Error('message_too_long', __('Message is too long.', 'G3'), ['status' => 400]);
        }

        if ($this->cacheStorageEnabled()) {
            return $this->sendCachedMessage($conversationId, $identity, $msgType, $body, $preview, $conversation, $options);
        }

        $now = Date::utcDateTime();

        $this->wpdb->query('START TRANSACTION');
        try {
            $locked = $this->wpdb->get_row(
                $this->wpdb->prepare("SELECT `id` FROM {$this->conversationsTable} WHERE `id` = %d FOR UPDATE", $conversationId),
                ARRAY_A
            );
            if (!$locked) {
                $this->wpdb->query('ROLLBACK');
                return new WP_Error('conversation_not_found', 'Conversation not found.', ['status' => 404]);
            }

            $seq = (int) $this->wpdb->get_var(
                $this->wpdb->prepare("SELECT COALESCE(MAX(`msg_seq`), 0) + 1 FROM {$this->messagesTable} WHERE `conversation_id` = %d", $conversationId)
            );

            $this->ensureParticipant($conversationId, $identity);
            $result = $this->wpdb->insert($this->messagesTable, [
                'conversation_id' => $conversationId,
                'msg_seq'         => $seq,
                'msg_type'        => $msgType,
                'sender_type'     => $identity['actor_type'],
                'sender_id'       => $identity['actor_id'],
                'sender_user_id'  => $identity['user_id'],
                'sender_name'     => $identity['display_name'],
                'content'         => $this->encode($body) ?: '{}',
                'preview'         => mb_substr(wp_strip_all_tags($preview), 0, 255),
                'search_text'     => $this->messageSearchText($body, $preview),
                'created_at'      => $now,
            ]);

            if ($result === false) {
                $this->wpdb->query('ROLLBACK');
                return new WP_Error('db_insert_error', 'Failed to save message.', ['status' => 500]);
            }

            $messageId = (int) $this->wpdb->insert_id;
            $this->wpdb->query($this->wpdb->prepare(
                "UPDATE {$this->conversationsTable}
                 SET `last_message_id` = %d,
                     `last_msg_seq` = %d,
                     `last_msg_type` = %s,
                     `last_msg_preview` = %s,
                     `last_message_at` = %s,
                     `updated_at` = %s
                 WHERE `id` = %d",
                $messageId,
                $seq,
                $msgType,
                mb_substr(wp_strip_all_tags($preview), 0, 255),
                $now,
                $now,
                $conversationId
            ));

            $this->wpdb->query('COMMIT');
        }
        catch (Throwable $throwable) {
            $this->wpdb->query('ROLLBACK');
            return new WP_Error('db_insert_error', $throwable->getMessage(), ['status' => 500]);
        }

        $message           = $this->getMessage($messageId);
        $conversation      = $this->getConversationRow($conversationId);
        $eventConversation = $conversation ? $this->formatConversation($conversation) : null;
        if ($message && is_callable($options['after_commit'] ?? null)) {
            $resolved = $options['after_commit']($message, $eventConversation);
            if (is_array($resolved)) {
                $eventConversation = $resolved;
            }
        }

        $this->publishEvent(IM::EVENT_MESSAGE_CREATED, $conversationId, $messageId, $identity, [
            'message'      => $message,
            'conversation' => $eventConversation,
        ], (string) ($conversation['type'] ?? IM::TYPE_CUSTOMER_SERVICE));

        return $message ?: new WP_Error('message_not_found', __('Message not found.', 'G3'), ['status' => 404]);
    }

    public function messages(int $conversationId, int $afterId = 0, int $limit = 50): array
    {
        $limit = min(100, max(1, $limit));
        $rows  = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->messagesTable}
                 WHERE `conversation_id` = %d AND `id` > %d AND `deleted_at` IS NULL
                 ORDER BY `id` ASC LIMIT %d",
                $conversationId,
                max(0, $afterId),
                $limit
            ),
            ARRAY_A
        ) ?: [];

        if ($this->cacheStorageEnabled()) {
            $rows = $this->mergeRowsById($rows, $this->cachedMessageRows($conversationId, $afterId, $limit));
        }

        usort($rows, fn(array $a, array $b): int => ((int) $a['id']) <=> ((int) $b['id']));
        $rows = array_slice($rows, 0, $limit);

        return array_map(fn(array $row): array => $this->formatMessage($row), $rows);
    }

    public function listConversations(string $type, array $args = []): array
    {
        $state  = (string) ($args['state'] ?? ($args['status'] ?? ''));
        $cursor = max(0, (int) ($args['cursor'] ?? 0));
        $limit  = min(100, max(1, (int) ($args['limit'] ?? 30)));
        $search = trim((string) ($args['search'] ?? ''));

        $where  = ['`type` = %s'];
        $params = [sanitize_key($type)];

        if ($state !== '') {
            $where[]  = '`state` = %s';
            $params[] = sanitize_key($state);
        }

        if ($cursor > 0) {
            $where[]  = '`id` < %d';
            $params[] = $cursor;
        }

        if ($search !== '') {
            $like    = '%' . $this->wpdb->esc_like($search) . '%';
            $where[] = '(`subject` LIKE %s OR `last_msg_preview` LIKE %s)';
            array_push($params, $like, $like);
        }

        $params[] = $limit + 1;
        $sql      = "SELECT * FROM {$this->conversationsTable} WHERE " . implode(' AND ', $where) . ' ORDER BY `last_message_at` DESC, `id` DESC LIMIT %d';
        $rows     = $this->wpdb->get_results($this->wpdb->prepare($sql, $params), ARRAY_A) ?: [];

        if ($this->cacheStorageEnabled()) {
            $rows = $this->mergeRowsById($rows, $this->filterCachedConversations(sanitize_key($type), [
                'state'  => $state !== '' ? sanitize_key($state) : '',
                'cursor' => $cursor,
                'search' => $search,
            ]));
            usort($rows, fn(array $a, array $b): int => strcmp(
                (string) ($b['last_message_at'] ?? $b['updated_at'] ?? $b['created_at'] ?? ''),
                (string) ($a['last_message_at'] ?? $a['updated_at'] ?? $a['created_at'] ?? '')
            ) ?: ((int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0)));
            $rows = array_slice($rows, 0, $limit + 1);
        }

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $items = array_map(fn(array $row): array => $this->formatConversation($row), $rows);
        $last  = $items ? $items[array_key_last($items)] : null;

        return [
            'items'       => $items,
            'next_cursor' => $hasMore && $last ? (int) $last['id'] : null,
            'has_more'    => $hasMore,
        ];
    }

    public function updateConversation(int $conversationId, array $data, array $identity): array|WP_Error
    {
        $update = [];
        if (isset($data['state'])) {
            $update['state']     = sanitize_key((string) $data['state']);
            $update['closed_at'] = $update['state'] === IM::CONVERSATION_CLOSED ? Date::utcDateTime() : null;
        }
        if (isset($data['priority'])) {
            $update['priority'] = max(0, min(9, (int) $data['priority']));
        }
        if (array_key_exists('subject', $data)) {
            $subject = trim(sanitize_text_field((string) $data['subject']));
            if ($subject === '') {
                return new WP_Error('invalid_subject', 'Conversation title cannot be empty.', ['status' => 400]);
            }
            $update['subject'] = mb_substr($subject, 0, 255);
        }

        if (!$update) {
            $conversation = $this->getConversationRow($conversationId);
            return $conversation ? $this->formatConversation($conversation) : new WP_Error('conversation_not_found', 'Conversation not found.', ['status' => 404]);
        }

        $update['updated_at'] = Date::utcDateTime();
        if ($this->cacheStorageEnabled()) {
            $conversation = $this->updateCachedConversationColumns($conversationId, $update);
            if (!$conversation) {
                return new WP_Error('conversation_not_found', 'Conversation not found.', ['status' => 404]);
            }

            $eventType = isset($update['state']) ? IM::EVENT_CONVERSATION_STATE_CHANGED : IM::EVENT_CONVERSATION_UPDATED;
            $this->publishEvent($eventType, $conversationId, null, $identity, [
                'update'       => $update,
                'conversation' => $this->formatConversation($conversation),
            ], (string) ($conversation['type'] ?? IM::TYPE_CUSTOMER_SERVICE));

            return $this->formatConversation($conversation);
        }

        $result = $this->wpdb->update($this->conversationsTable, $update, ['id' => $conversationId]);
        if ($result === false) {
            return new WP_Error('db_update_error', 'Failed to update conversation.', ['status' => 500]);
        }

        $conversation = $this->getConversationRow($conversationId);
        $eventType    = isset($update['state']) ? IM::EVENT_CONVERSATION_STATE_CHANGED : IM::EVENT_CONVERSATION_UPDATED;
        $this->publishEvent($eventType, $conversationId, null, $identity, [
            'update'       => $update,
            'conversation' => $conversation ? $this->formatConversation($conversation) : null,
        ], (string) ($conversation['type'] ?? IM::TYPE_CUSTOMER_SERVICE));

        return $conversation ? $this->formatConversation($conversation) : new WP_Error('conversation_not_found', 'Conversation not found.', ['status' => 404]);
    }

    public function markRead(int $conversationId, int $messageId, array $identity): array
    {
        if ($messageId <= 0) {
            $messageId = $this->latestMessageId($conversationId);
        }

        $this->ensureParticipant($conversationId, $identity);
        if ($this->cacheStorageEnabled()) {
            $this->updateCachedParticipantColumns($conversationId, $identity, [
                'last_read_message_id' => $messageId,
                'last_seen_at'         => Date::utcDateTime(),
            ]);

            $conversation = $this->getConversationRow($conversationId);
            $this->publishEvent(IM::EVENT_PARTICIPANT_READ, $conversationId, $messageId, $identity, [
                'message_id' => $messageId,
            ], (string) ($conversation['type'] ?? IM::TYPE_CUSTOMER_SERVICE));
            $this->cleanupReadEvents($conversationId, $messageId);

            return ['message_id' => $messageId];
        }

        $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->participantsTable}
             SET `last_read_message_id` = GREATEST(`last_read_message_id`, %d), `last_seen_at` = %s
             WHERE `conversation_id` = %d AND `actor_type` = %s AND `actor_id` = %s",
            $messageId,
            Date::utcDateTime(),
            $conversationId,
            $identity['actor_type'],
            $identity['actor_id']
        ));

        $conversation = $this->getConversationRow($conversationId);
        $this->publishEvent(IM::EVENT_PARTICIPANT_READ, $conversationId, $messageId, $identity, [
            'message_id' => $messageId,
        ], (string) ($conversation['type'] ?? IM::TYPE_CUSTOMER_SERVICE));
        $this->cleanupReadEvents($conversationId, $messageId);

        return ['message_id' => $messageId];
    }

    public function events(int $afterId = 0, ?int $conversationId = null, int $limit = 50): array
    {
        return array_map(
            fn(array $row): array => $this->formatEvent($row),
            $this->cachedEventRows($afterId, $conversationId, $limit)
        );
    }

    public function createStreamSession(string $conversationType, string $scope, ?int $conversationId, int $afterId, int $heartbeat, string $owner = ''): array|WP_Error
    {
        return $this->realtime()->createSession($conversationType, $scope, $conversationId, $afterId, $heartbeat, $owner);
    }

    public function latestEventId(string $conversationType): int
    {
        return $this->realtime()->latestEventId($conversationType);
    }

    public function touchPresence(string $scope, int|string $id): void
    {
        try {
            $redis = $this->redis();
            $redis?->setex(self::CACHE_GROUP . ':presence:' . sanitize_key($scope) . ':' . sanitize_key((string) $id), 60, (string) time());
        }
        catch (Throwable) {
        }
    }

    public function cleanupBeforeDays(string $type, int $days): array
    {
        $days   = max(1, min(3650, $days));
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        $ids = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT `id`
             FROM {$this->conversationsTable}
             WHERE `type` = %s
               AND ((`updated_at` IS NOT NULL AND `updated_at` < %s) OR (`updated_at` IS NULL AND `created_at` < %s))
             ORDER BY `updated_at` ASC, `id` ASC
             LIMIT 1000",
            sanitize_key($type),
            $cutoff,
            $cutoff
        )) ?: [];

        if (!$ids) {
            return ['cutoff' => $cutoff, 'conversations' => 0, 'messages' => 0, 'participants' => 0];
        }

        $ids           = array_map('intval', $ids);
        $placeholders  = implode(',', array_fill(0, count($ids), '%d'));
        $messages      = (int) $this->wpdb->query($this->wpdb->prepare("DELETE FROM {$this->messagesTable} WHERE `conversation_id` IN ({$placeholders})", $ids));
        $participants  = (int) $this->wpdb->query($this->wpdb->prepare("DELETE FROM {$this->participantsTable} WHERE `conversation_id` IN ({$placeholders})", $ids));
        $conversations = (int) $this->wpdb->query($this->wpdb->prepare("DELETE FROM {$this->conversationsTable} WHERE `id` IN ({$placeholders})", $ids));

        return [
            'cutoff'        => $cutoff,
            'conversations' => max(0, $conversations),
            'messages'      => max(0, $messages),
            'participants'  => max(0, $participants),
        ];
    }

    public function markTimeoutConversations(string $type, int $minutes, int $limit, array $identity): int
    {
        $minutes = max(1, min(14400, $minutes));
        $limit   = min(1000, max(1, $limit));
        $cutoff  = gmdate('Y-m-d H:i:s', time() - ($minutes * MINUTE_IN_SECONDS));
        $ids     = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT `id`
             FROM {$this->conversationsTable}
             WHERE `type` = %s
               AND `state` = %s
               AND COALESCE(`last_message_at`, `updated_at`, `created_at`) < %s
             ORDER BY COALESCE(`last_message_at`, `updated_at`, `created_at`) ASC
             LIMIT %d",
            sanitize_key($type),
            IM::CONVERSATION_OPEN,
            $cutoff,
            $limit
        )) ?: [];

        if ($this->cacheStorageEnabled()) {
            foreach ($this->cachedConversationRows(sanitize_key($type)) as $row) {
                $last = (string) ($row['last_message_at'] ?? $row['updated_at'] ?? $row['created_at'] ?? '');
                if ((string) ($row['state'] ?? '') === IM::CONVERSATION_OPEN && $last !== '' && $last < $cutoff) {
                    $ids[] = (int) $row['id'];
                }
            }
            $ids = array_slice(array_values(array_unique(array_map('intval', $ids))), 0, $limit);
        }

        foreach (array_map('intval', $ids) as $id) {
            $this->updateConversation($id, ['state' => IM::CONVERSATION_CLOSED], $identity);
        }

        return count($ids);
    }

    public function canAccessConversation(int $conversationId, array $identity): bool
    {
        $conversation = $this->getConversationRow($conversationId);
        if (!$conversation) {
            return false;
        }

        if ($this->cacheStorageEnabled() && $this->cachedParticipantRowByActor($conversationId, $identity)) {
            return true;
        }

        return (bool) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT 1 FROM {$this->participantsTable}
             WHERE `conversation_id` = %d AND `actor_type` = %s AND `actor_id` = %s
             LIMIT 1",
            $conversationId,
            $identity['actor_type'],
            (string) $identity['actor_id']
        ));
    }

    public function getConversationRow(int $conversationId): ?array
    {
        if ($this->cacheStorageEnabled()) {
            $row = $this->cachedConversationRow($conversationId);
            if ($row) {
                return $row;
            }
        }

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->conversationsTable} WHERE `id` = %d", $conversationId),
            ARRAY_A
        );
        return is_array($row) ? $row : null;
    }

    public function getConversation(int $conversationId): ?array
    {
        $row = $this->getConversationRow($conversationId);
        return $row ? $this->formatConversation($row) : null;
    }

    public function publishConversationEvent(string $eventType, int $conversationId, array $identity, array $conversation): void
    {
        $row = $this->getConversationRow($conversationId);
        $this->publishEvent($eventType, $conversationId, null, $identity, [
            'conversation' => $conversation,
        ], (string) ($row['type'] ?? IM::TYPE_CUSTOMER_SERVICE));
    }

    public function syncCachedMessages(int $limit = 200): array
    {
        return $this->syncCachedData($limit);
    }

    public function syncCachedData(int $limit = 200): array
    {
        $redis = $this->redis();
        if (!$redis) {
            return [
                'conversations' => 0,
                'participants'  => 0,
                'messages'      => 0,
                'skipped'       => 0,
                'failed'        => 0,
                'error'         => 'redis_unavailable',
            ];
        }

        $limit         = min(1000, max(1, $limit));
        $conversations = $this->syncCachedConversationRows($redis, $limit);
        $participants  = $this->syncCachedParticipantRows($redis, $limit);
        $messages      = $this->syncCachedMessageRows($redis, $limit);

        return [
            'conversations' => $conversations['synced'],
            'participants'  => $participants['synced'],
            'messages'      => $messages['synced'],
            'skipped'       => $conversations['skipped'] + $participants['skipped'] + $messages['skipped'],
            'failed'        => $conversations['failed'] + $participants['failed'] + $messages['failed'],
        ];
    }

    private function createCachedConversation(array $identity, array $data): int|WP_Error
    {
        $redis = $this->redis();
        if (!$redis) {
            return new WP_Error('redis_unavailable', 'IM cache storage requires Redis.', ['status' => 503]);
        }

        $now     = Date::utcDateTime();
        $type    = sanitize_key((string) ($data['type'] ?? IM::TYPE_CUSTOMER_SERVICE));
        $subject = trim(sanitize_text_field((string) ($data['subject'] ?? '')));
        if ($subject === '') {
            $subject = (string) ($identity['display_name'] ?? '');
        }

        $conversationId = $this->nextCachedId($redis, self::CONVERSATION_COUNTER_KEY, $this->conversationsTable);
        $row            = [
            'id'               => $conversationId,
            'type'             => $type,
            'subject'          => mb_substr($subject, 0, 255),
            'state'            => sanitize_key((string) ($data['state'] ?? IM::CONVERSATION_OPEN)),
            'priority'         => 0,
            'source'           => sanitize_key((string) ($data['source'] ?? 'web')),
            'last_message_id'  => null,
            'last_msg_seq'     => 0,
            'last_msg_type'    => null,
            'last_msg_preview' => null,
            'last_message_at'  => null,
            'ip_address'       => System::ip() ?: null,
            'user_agent'       => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 255) : null,
            'meta'             => $this->encode($this->sanitizeMeta($data['meta'] ?? [])),
            'created_at'       => $now,
            'updated_at'       => $now,
            'closed_at'        => null,
        ];

        if (!$this->storeCachedConversationRow($redis, $row)) {
            return new WP_Error('redis_write_failed', 'Failed to cache IM conversation.', ['status' => 500]);
        }

        $this->ensureParticipant($conversationId, $identity);
        $this->publishEvent(IM::EVENT_CONVERSATION_CREATED, $conversationId, null, $identity, [
            'conversation_id' => $conversationId,
        ], $type);

        return $conversationId;
    }

    private function sendCachedMessage(int $conversationId, array $identity, string $msgType, array $body, string $preview, array $conversation, array $options): array|WP_Error
    {
        $redis = $this->redis();
        if (!$redis) {
            return new WP_Error('redis_unavailable', 'IM cache storage requires Redis.', ['status' => 503]);
        }

        $now       = Date::utcDateTime();
        $messageId = $this->nextCachedId($redis, self::MESSAGE_COUNTER_KEY, $this->messagesTable);
        $seq       = $this->nextCachedMessageSeq($redis, $conversationId);
        $row       = [
            'id'              => $messageId,
            'conversation_id' => $conversationId,
            'msg_seq'         => $seq,
            'msg_type'        => $msgType,
            'sender_type'     => $identity['actor_type'],
            'sender_id'       => $identity['actor_id'],
            'sender_user_id'  => $identity['user_id'] ?: null,
            'sender_name'     => $identity['display_name'],
            'content'         => $this->encode($body) ?: '{}',
            'preview'         => mb_substr(wp_strip_all_tags($preview), 0, 255),
            'search_text'     => $this->messageSearchText($body, $preview),
            'status'          => IM::MESSAGE_SENT,
            'created_at'      => $now,
            'deleted_at'      => null,
        ];

        $this->ensureParticipant($conversationId, $identity);
        if (!$this->storeCachedMessageRow($redis, $row)) {
            return new WP_Error('redis_write_failed', 'Failed to cache IM message.', ['status' => 500]);
        }

        $conversation = $this->updateCachedConversationColumns($conversationId, [
            'last_message_id'  => $messageId,
            'last_msg_seq'     => $seq,
            'last_msg_type'    => $msgType,
            'last_msg_preview' => mb_substr(wp_strip_all_tags($preview), 0, 255),
            'last_message_at'  => $now,
            'updated_at'       => $now,
        ]);
        if (!$conversation) {
            $this->deleteCachedMessageRow($redis, $row);
            return new WP_Error('conversation_not_found', 'Conversation not found.', ['status' => 404]);
        }

        $message           = $this->formatMessage($row);
        $eventConversation = $conversation ? $this->formatConversation($conversation) : null;
        if ($message && is_callable($options['after_commit'] ?? null)) {
            $resolved = $options['after_commit']($message, $eventConversation);
            if (is_array($resolved)) {
                $eventConversation = $resolved;
            }
        }

        $this->publishEvent(IM::EVENT_MESSAGE_CREATED, $conversationId, $messageId, $identity, [
            'message'      => $message,
            'conversation' => $eventConversation,
        ], (string) ($conversation['type'] ?? IM::TYPE_CUSTOMER_SERVICE));

        return $message;
    }

    private function getMessage(int $messageId): ?array
    {
        if ($this->cacheStorageEnabled()) {
            $row = $this->cachedMessageRow($messageId);
            if ($row) {
                return $this->formatMessage($row);
            }
        }

        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->messagesTable} WHERE `id` = %d", $messageId),
            ARRAY_A
        );
        return is_array($row) ? $this->formatMessage($row) : null;
    }

    private function ensureParticipant(int $conversationId, array $identity): void
    {
        $identity = $this->participantIdentity($identity);

        if ($this->cacheStorageEnabled()) {
            $redis = $this->redis();
            if (!$redis) {
                return;
            }

            $now = Date::utcDateTime();
            $row = $this->cachedParticipantRowByActor($conversationId, $identity);
            if (!$row) {
                $row = $this->wpdb->get_row($this->wpdb->prepare(
                    "SELECT * FROM {$this->participantsTable}
                     WHERE `conversation_id` = %d AND `actor_type` = %s AND `actor_id` = %s LIMIT 1",
                    $conversationId,
                    $identity['actor_type'],
                    (string) $identity['actor_id']
                ), ARRAY_A);
            }

            if (is_array($row)) {
                $row['nickname']     = $identity['display_name'];
                $row['avatar']       = $identity['avatar'];
                $row['last_seen_at'] = $now;
            } else {
                $row = [
                    'id'                   => $this->nextCachedId($redis, self::PARTICIPANT_COUNTER_KEY, $this->participantsTable),
                    'conversation_id'      => $conversationId,
                    'actor_type'           => $identity['actor_type'],
                    'actor_id'             => $identity['actor_id'],
                    'user_id'              => $identity['user_id'] ?: null,
                    'role'                 => $identity['role'],
                    'nickname'             => $identity['display_name'],
                    'avatar'               => $identity['avatar'],
                    'last_read_message_id' => 0,
                    'last_seen_at'         => $now,
                    'created_at'           => $now,
                ];
            }

            $this->storeCachedParticipantRow($redis, $row);
            return;
        }

        $this->wpdb->query($this->wpdb->prepare(
            "INSERT INTO {$this->participantsTable}
                (`conversation_id`, `actor_type`, `actor_id`, `user_id`, `role`, `nickname`, `avatar`, `last_seen_at`, `created_at`)
             VALUES (%d, %s, %s, %d, %s, %s, %s, %s, %s)
             ON DUPLICATE KEY UPDATE
                `nickname` = VALUES(`nickname`),
                `avatar` = VALUES(`avatar`),
                `last_seen_at` = VALUES(`last_seen_at`)",
            $conversationId,
            $identity['actor_type'],
            $identity['actor_id'],
            $identity['user_id'] ?: 0,
            $identity['role'],
            $identity['display_name'],
            $identity['avatar'],
            Date::utcDateTime(),
            Date::utcDateTime()
        ));
    }

    private function participantIdentity(array $identity): array
    {
        $userId = (int) ($identity['user_id'] ?? 0);
        if ($userId <= 0) {
            return $identity;
        }

        /** @var UserService $userService */
        $userService = $this->container->get(UserService::class);
        $profile     = $userService->init($userId)?->cache();
        if (!is_array($profile)) {
            return $identity;
        }

        $displayName = trim((string) ($profile['nickname'] ?? ''));
        $avatar      = trim((string) ($profile['avatar'] ?? ''));
        if ($displayName !== '') {
            $identity['display_name'] = $displayName;
        }
        $identity['avatar'] = $avatar !== '' ? $avatar : UserService::getDefaultAvatar();

        return $identity;
    }

    private function publishEvent(string $type, ?int $conversationId, ?int $messageId, array $identity, array $payload, string $conversationType): void
    {
        $this->storeCachedEvent($type, $conversationId, $messageId, $identity, $payload);

        $meta = [
            'target_type' => 'im_conversation',
            'target_id'   => $conversationId ? (string) $conversationId : null,
            'actor_type'  => $identity['actor_type'],
            'actor_id'    => $identity['actor_id'],
        ];

        $this->realtime()->publish($conversationType, $conversationId, $type, $payload, $meta);
    }

    private function storeCachedConversationRow(Redis $redis, array $row): bool
    {
        $conversationId = (int) ($row['id'] ?? 0);
        if ($conversationId <= 0) {
            return false;
        }

        $encoded = Type::arrayToJson($row);
        if (!is_string($encoded) || $encoded === '') {
            return false;
        }

        $stored = $redis->hSet(self::CONVERSATIONS_CACHE_KEY, (string) $conversationId, $encoded) !== false;
        $redis->zAdd(self::CONVERSATION_INDEX_KEY, $conversationId, (string) $conversationId);
        $redis->zAdd($this->conversationTypeKey((string) ($row['type'] ?? IM::TYPE_CUSTOMER_SERVICE)), $conversationId, (string) $conversationId);
        $redis->zAdd(self::CONVERSATION_SYNC_KEY, $conversationId, (string) $conversationId);
        $this->touchCacheKeys($redis, [
            self::CONVERSATIONS_CACHE_KEY,
            self::CONVERSATION_INDEX_KEY,
            $this->conversationTypeKey((string) ($row['type'] ?? IM::TYPE_CUSTOMER_SERVICE)),
            self::CONVERSATION_SYNC_KEY,
        ]);

        return $stored;
    }

    private function updateCachedConversationColumns(int $conversationId, array $columns): ?array
    {
        $redis = $this->redis();
        if (!$redis || $conversationId <= 0) {
            return null;
        }

        $row = $this->cachedConversationRow($conversationId);
        if (!$row) {
            $row = $this->wpdb->get_row(
                $this->wpdb->prepare("SELECT * FROM {$this->conversationsTable} WHERE `id` = %d", $conversationId),
                ARRAY_A
            );
        }
        if (!is_array($row)) {
            return null;
        }

        foreach ($columns as $column => $value) {
            $row[sanitize_key((string) $column)] = $value;
        }

        return $this->storeCachedConversationRow($redis, $row) ? $row : null;
    }

    private function cachedConversationRow(int $conversationId): ?array
    {
        $redis = $this->redis();
        if (!$redis || $conversationId <= 0) {
            return null;
        }

        $raw = $redis->hGet(self::CONVERSATIONS_CACHE_KEY, (string) $conversationId);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $row = json_decode($raw, true);
        return is_array($row) ? $row : null;
    }

    private function cachedConversationRows(string $type = ''): array
    {
        $redis = $this->redis();
        if (!$redis) {
            return [];
        }

        $ids = $type !== ''
            ? ($redis->zRevRange($this->conversationTypeKey($type), 0, 499) ?: [])
            : ($redis->zRevRange(self::CONVERSATION_INDEX_KEY, 0, 499) ?: []);

        return $this->cachedRowsByIds($redis, self::CONVERSATIONS_CACHE_KEY, $ids);
    }

    private function filterCachedConversations(string $type, array $args = []): array
    {
        $state  = (string) ($args['state'] ?? '');
        $cursor = max(0, (int) ($args['cursor'] ?? 0));
        $search = mb_strtolower(trim((string) ($args['search'] ?? '')));

        return array_values(array_filter($this->cachedConversationRows($type), static function (array $row) use ($type, $state, $cursor, $search): bool {
            if ((string) ($row['type'] ?? '') !== $type) {
                return false;
            }
            if ($state !== '' && (string) ($row['state'] ?? '') !== $state) {
                return false;
            }
            if ($cursor > 0 && (int) ($row['id'] ?? 0) >= $cursor) {
                return false;
            }
            if ($search !== '') {
                $haystack = mb_strtolower((string) ($row['subject'] ?? '') . ' ' . (string) ($row['last_msg_preview'] ?? ''));
                return str_contains($haystack, $search);
            }
            return true;
        }));
    }

    private function storeCachedParticipantRow(Redis $redis, array $row): bool
    {
        $participantId  = (int) ($row['id'] ?? 0);
        $conversationId = (int) ($row['conversation_id'] ?? 0);
        $actorType      = sanitize_key((string) ($row['actor_type'] ?? ''));
        $actorId        = (string) ($row['actor_id'] ?? '');
        if ($participantId <= 0 || $conversationId <= 0 || $actorType === '' || $actorId === '') {
            return false;
        }

        $encoded = Type::arrayToJson($row);
        if (!is_string($encoded) || $encoded === '') {
            return false;
        }

        $stored = $redis->hSet(self::PARTICIPANTS_CACHE_KEY, (string) $participantId, $encoded) !== false;
        $redis->set($this->participantActorKey($conversationId, $actorType, $actorId), (string) $participantId);
        $redis->zAdd($this->conversationParticipantsKey($conversationId), $participantId, (string) $participantId);
        $redis->zAdd(self::PARTICIPANT_SYNC_KEY, $participantId, (string) $participantId);
        $this->touchCacheKeys($redis, [
            self::PARTICIPANTS_CACHE_KEY,
            $this->participantActorKey($conversationId, $actorType, $actorId),
            $this->conversationParticipantsKey($conversationId),
            self::PARTICIPANT_SYNC_KEY,
        ]);

        return $stored;
    }

    private function updateCachedParticipantColumns(int $conversationId, array $identity, array $columns): ?array
    {
        $redis = $this->redis();
        if (!$redis) {
            return null;
        }

        $row = $this->cachedParticipantRowByActor($conversationId, $identity);
        if (!$row) {
            $this->ensureParticipant($conversationId, $identity);
            $row = $this->cachedParticipantRowByActor($conversationId, $identity);
        }
        if (!$row) {
            return null;
        }

        foreach ($columns as $column => $value) {
            $column = sanitize_key((string) $column);
            if ($column === 'last_read_message_id') {
                $row[$column] = max((int) ($row[$column] ?? 0), (int) $value);
                continue;
            }
            $row[$column] = $value;
        }

        return $this->storeCachedParticipantRow($redis, $row) ? $row : null;
    }

    private function cachedParticipantRowByActor(int $conversationId, array $identity): ?array
    {
        $redis = $this->redis();
        if (!$redis || $conversationId <= 0) {
            return null;
        }

        $participantId = $redis->get($this->participantActorKey($conversationId, (string) $identity['actor_type'], (string) $identity['actor_id']));
        if (!is_scalar($participantId) || (int) $participantId <= 0) {
            return null;
        }

        $raw = $redis->hGet(self::PARTICIPANTS_CACHE_KEY, (string) $participantId);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $row = json_decode($raw, true);
        return is_array($row) ? $row : null;
    }

    private function storeCachedMessageRow(Redis $redis, array $row): bool
    {
        $messageId      = (int) ($row['id'] ?? 0);
        $conversationId = (int) ($row['conversation_id'] ?? 0);
        if ($messageId <= 0 || $conversationId <= 0) {
            return false;
        }

        $encoded = Type::arrayToJson($row);
        if (!is_string($encoded) || $encoded === '') {
            return false;
        }

        $conversationKey = $this->conversationMessagesKey($conversationId);
        $stored          = $redis->hSet(self::MESSAGES_CACHE_KEY, (string) $messageId, $encoded) !== false;
        $redis->zAdd($conversationKey, $messageId, (string) $messageId);
        $redis->zAdd(self::MESSAGE_SYNC_KEY, $messageId, (string) $messageId);
        $this->touchCacheKeys($redis, [self::MESSAGES_CACHE_KEY, $conversationKey, self::MESSAGE_SYNC_KEY]);

        return $stored;
    }

    private function deleteCachedMessageRow(Redis $redis, array $row): void
    {
        $messageId      = (int) ($row['id'] ?? 0);
        $conversationId = (int) ($row['conversation_id'] ?? 0);
        if ($messageId <= 0) {
            return;
        }

        $redis->hDel(self::MESSAGES_CACHE_KEY, (string) $messageId);
        $redis->zRem(self::MESSAGE_SYNC_KEY, (string) $messageId);
        if ($conversationId > 0) {
            $redis->zRem($this->conversationMessagesKey($conversationId), (string) $messageId);
        }
    }

    private function storeCachedEvent(string $type, ?int $conversationId, ?int $messageId, array $identity, array $payload): ?int
    {
        $redis = $this->redis();
        if (!$redis) {
            $this->logger->warning('IM event cache storage skipped because Redis is unavailable.', [
                'module' => self::CACHE_GROUP,
                'type'   => $type,
            ]);
            return null;
        }

        $eventId = $this->nextCachedEventId($redis);
        $row     = [
            'id'              => $eventId,
            'conversation_id' => $conversationId,
            'event_type'      => $type,
            'message_id'      => $messageId,
            'actor_type'      => $identity['actor_type'],
            'actor_id'        => $identity['actor_id'],
            'payload'         => $this->encode($payload),
            'created_at'      => Date::utcDateTime(),
        ];
        $encoded = Type::arrayToJson($row);
        if (!is_string($encoded) || $encoded === '') {
            return null;
        }

        $redis->hSet(self::EVENTS_CACHE_KEY, (string) $eventId, $encoded);
        $redis->zAdd(self::EVENT_INDEX_KEY, $eventId, (string) $eventId);
        if ($conversationId) {
            $redis->zAdd($this->conversationEventsKey($conversationId), $eventId, (string) $eventId);
        }

        return $eventId;
    }

    private function cachedMessageRow(int $messageId): ?array
    {
        $redis = $this->redis();
        if (!$redis || $messageId <= 0) {
            return null;
        }

        $raw = $redis->hGet(self::MESSAGES_CACHE_KEY, (string) $messageId);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $row = json_decode($raw, true);
        return is_array($row) ? $row : null;
    }

    private function cachedMessageRows(int $conversationId, int $afterId, int $limit): array
    {
        $redis = $this->redis();
        if (!$redis || $conversationId <= 0) {
            return [];
        }

        $ids = $redis->zRangeByScore(
            $this->conversationMessagesKey($conversationId),
            (string) (max(0, $afterId) + 1),
            '+inf',
            ['limit' => [0, min(100, max(1, $limit))]]
        ) ?: [];

        return array_values(array_filter(
            $this->cachedRowsByIds($redis, self::MESSAGES_CACHE_KEY, $ids),
            fn(array $row): bool => empty($row['deleted_at'])
        ));
    }

    private function cachedEventRows(int $afterId, ?int $conversationId, int $limit): array
    {
        $redis = $this->redis();
        if (!$redis) {
            return [];
        }

        $limit = min(200, max(1, $limit));
        $key   = $conversationId && $conversationId > 0 ? $this->conversationEventsKey($conversationId) : self::EVENT_INDEX_KEY;
        $ids   = $redis->zRangeByScore($key, (string) (max(0, $afterId) + 1), '+inf', ['limit' => [0, $limit]]) ?: [];
        $rows  = $this->cachedRowsByIds($redis, self::EVENTS_CACHE_KEY, $ids);
        if ($conversationId) {
            $rows = array_filter($rows, static function (array $row) use ($conversationId): bool {
                return $row['conversation_id'] === null || (int) $row['conversation_id'] === $conversationId;
            });
        }

        usort($rows, fn(array $a, array $b): int => ((int) $a['id']) <=> ((int) $b['id']));
        return array_slice($rows, 0, $limit);
    }

    private function cleanupReadEvents(int $conversationId, int $messageId): void
    {
        $redis = $this->redis();
        if (!$redis || $conversationId <= 0 || $messageId <= 0) {
            return;
        }

        $key = $this->conversationEventsKey($conversationId);
        $ids = $redis->zRange($key, 0, -1) ?: [];
        if (!$ids) {
            return;
        }

        $rows = $this->cachedRowsByIds($redis, self::EVENTS_CACHE_KEY, $ids);
        foreach ($rows as $row) {
            $eventId        = (int) ($row['id'] ?? 0);
            $eventMessageId = (int) ($row['message_id'] ?? 0);
            if ($eventId <= 0 || $eventMessageId <= 0 || $eventMessageId > $messageId) {
                continue;
            }

            $redis->hDel(self::EVENTS_CACHE_KEY, (string) $eventId);
            $redis->zRem(self::EVENT_INDEX_KEY, (string) $eventId);
            $redis->zRem($key, (string) $eventId);
        }
    }

    private function cachedRowsByIds(Redis $redis, string $key, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids), static fn(string $id): bool => $id !== '')));
        if (!$ids) {
            return [];
        }

        $values = $redis->hMGet($key, $ids);
        if (!is_array($values)) {
            return [];
        }

        $rows = [];
        foreach ($ids as $id) {
            $raw = $values[$id] ?? null;
            if (!is_string($raw) || $raw === '') {
                continue;
            }

            $row = json_decode($raw, true);
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function syncCachedConversationRows(Redis $redis, int $limit): array
    {
        $ids    = $redis->zRangeByScore(self::CONVERSATION_SYNC_KEY, '-inf', '+inf', ['limit' => [0, $limit]]) ?: [];
        $rows   = $this->cachedRowsByIds($redis, self::CONVERSATIONS_CACHE_KEY, $ids);
        $byId   = [];
        $result = ['synced' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            $byId[(string) ((int) ($row['id'] ?? 0))] = $row;
        }

        foreach ($ids as $id) {
            $conversationId = (int) $id;
            $row            = $byId[(string) $conversationId] ?? null;
            if (!$row) {
                $redis->zRem(self::CONVERSATION_SYNC_KEY, (string) $conversationId);
                $result['skipped']++;
                continue;
            }

            if ($this->upsertCachedConversationRow($row)) {
                $redis->zRem(self::CONVERSATION_SYNC_KEY, (string) $conversationId);
                $result['synced']++;
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    private function syncCachedParticipantRows(Redis $redis, int $limit): array
    {
        $ids    = $redis->zRangeByScore(self::PARTICIPANT_SYNC_KEY, '-inf', '+inf', ['limit' => [0, $limit]]) ?: [];
        $rows   = $this->cachedRowsByIds($redis, self::PARTICIPANTS_CACHE_KEY, $ids);
        $byId   = [];
        $result = ['synced' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            $byId[(string) ((int) ($row['id'] ?? 0))] = $row;
        }

        foreach ($ids as $id) {
            $participantId = (int) $id;
            $row           = $byId[(string) $participantId] ?? null;
            if (!$row) {
                $redis->zRem(self::PARTICIPANT_SYNC_KEY, (string) $participantId);
                $result['skipped']++;
                continue;
            }

            if ($this->upsertCachedParticipantRow($row)) {
                $redis->zRem(self::PARTICIPANT_SYNC_KEY, (string) $participantId);
                $result['synced']++;
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    private function syncCachedMessageRows(Redis $redis, int $limit): array
    {
        $ids    = $redis->zRangeByScore(self::MESSAGE_SYNC_KEY, '-inf', '+inf', ['limit' => [0, $limit]]) ?: [];
        $result = ['synced' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($ids as $id) {
            $messageId = (int) $id;
            $row       = $this->cachedMessageRow($messageId);
            if (!$row || $this->messageExists($messageId)) {
                $redis->zRem(self::MESSAGE_SYNC_KEY, (string) $messageId);
                $result['skipped']++;
                continue;
            }

            if ($this->insertCachedMessageRow($row)) {
                $redis->zRem(self::MESSAGE_SYNC_KEY, (string) $messageId);
                $result['synced']++;
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    private function upsertCachedConversationRow(array $row): bool
    {
        $conversationId = (int) ($row['id'] ?? 0);
        if ($conversationId <= 0) {
            return false;
        }

        $data = [
            'id'               => $conversationId,
            'type'             => sanitize_key((string) ($row['type'] ?? IM::TYPE_CUSTOMER_SERVICE)),
            'subject'          => isset($row['subject']) ? (string) $row['subject'] : null,
            'state'            => sanitize_key((string) ($row['state'] ?? IM::CONVERSATION_OPEN)),
            'priority'         => (int) ($row['priority'] ?? 0),
            'source'           => sanitize_key((string) ($row['source'] ?? 'web')),
            'last_message_id'  => isset($row['last_message_id']) ? (int) $row['last_message_id'] : null,
            'last_msg_seq'     => (int) ($row['last_msg_seq'] ?? 0),
            'last_msg_type'    => isset($row['last_msg_type']) ? sanitize_key((string) $row['last_msg_type']) : null,
            'last_msg_preview' => isset($row['last_msg_preview']) ? (string) $row['last_msg_preview'] : null,
            'last_message_at'  => $row['last_message_at'] ?? null,
            'ip_address'       => $row['ip_address'] ?? null,
            'user_agent'       => $row['user_agent'] ?? null,
            'meta'             => is_string($row['meta'] ?? null) ? $row['meta'] : null,
            'created_at'       => (string) ($row['created_at'] ?? Date::utcDateTime()),
            'updated_at'       => $row['updated_at'] ?? null,
            'closed_at'        => $row['closed_at'] ?? null,
        ];

        return $this->upsertRow($this->conversationsTable, $data, 'id', $conversationId);
    }

    private function upsertCachedParticipantRow(array $row): bool
    {
        $participantId = (int) ($row['id'] ?? 0);
        if ($participantId <= 0) {
            return false;
        }

        $data = [
            'id'                   => $participantId,
            'conversation_id'      => (int) ($row['conversation_id'] ?? 0),
            'actor_type'           => sanitize_key((string) ($row['actor_type'] ?? '')),
            'actor_id'             => (string) ($row['actor_id'] ?? ''),
            'user_id'              => isset($row['user_id']) ? (int) $row['user_id'] : null,
            'role'                 => sanitize_key((string) ($row['role'] ?? 'member')),
            'nickname'             => isset($row['nickname']) ? (string) $row['nickname'] : null,
            'avatar'               => isset($row['avatar']) ? (string) $row['avatar'] : null,
            'last_read_message_id' => (int) ($row['last_read_message_id'] ?? 0),
            'last_seen_at'         => $row['last_seen_at'] ?? null,
            'created_at'           => (string) ($row['created_at'] ?? Date::utcDateTime()),
        ];

        return $this->upsertRow($this->participantsTable, $data, 'id', $participantId);
    }

    private function insertCachedMessageRow(array $row): bool
    {
        $messageId = (int) ($row['id'] ?? 0);
        if ($messageId <= 0 || $this->messageExists($messageId)) {
            return $messageId > 0;
        }

        $result = $this->wpdb->insert($this->messagesTable, [
            'id'              => $messageId,
            'conversation_id' => (int) ($row['conversation_id'] ?? 0),
            'msg_seq'         => (int) ($row['msg_seq'] ?? 0),
            'msg_type'        => sanitize_key((string) ($row['msg_type'] ?? IM::MESSAGE_TEXT)),
            'sender_type'     => sanitize_key((string) ($row['sender_type'] ?? '')),
            'sender_id'       => (string) ($row['sender_id'] ?? ''),
            'sender_user_id'  => isset($row['sender_user_id']) ? (int) $row['sender_user_id'] : null,
            'sender_name'     => isset($row['sender_name']) ? (string) $row['sender_name'] : null,
            'content'         => is_string($row['content'] ?? null) ? $row['content'] : '{}',
            'preview'         => isset($row['preview']) ? (string) $row['preview'] : null,
            'search_text'     => isset($row['search_text']) ? (string) $row['search_text'] : null,
            'status'          => (int) ($row['status'] ?? IM::MESSAGE_SENT),
            'created_at'      => (string) ($row['created_at'] ?? Date::utcDateTime()),
            'deleted_at'      => $row['deleted_at'] ?? null,
        ]);

        return $result !== false || $this->messageExists($messageId);
    }

    private function formatConversation(array $row): array
    {
        return [
            'id'                    => (int) $row['id'],
            'type'                  => $row['type'],
            'subject'               => $row['subject'],
            'state'                 => $row['state'],
            'priority'              => (int) $row['priority'],
            'source'                => $row['source'],
            'last_message_id'       => $row['last_message_id'] !== null ? (int) $row['last_message_id'] : null,
            'last_msg_seq'          => (int) $row['last_msg_seq'],
            'last_msg_type'         => $row['last_msg_type'],
            'last_msg_preview'      => $row['last_msg_preview'],
            'last_message_at'       => $row['last_message_at'],
            'last_message_at_local' => $this->formatDateTime($row['last_message_at'] ?? null),
            'meta'                  => $this->decode($row['meta'] ?? ''),
            'created_at'            => $row['created_at'],
            'created_at_local'      => $this->formatDateTime($row['created_at'] ?? null),
            'updated_at'            => $row['updated_at'],
            'updated_at_local'      => $this->formatDateTime($row['updated_at'] ?? null),
            'closed_at'             => $row['closed_at'],
            'closed_at_local'       => $this->formatDateTime($row['closed_at'] ?? null),
        ];
    }

    private function formatMessage(array $row): array
    {
        $content = $this->decode($row['content'] ?? '');
        return [
            'id'               => (int) $row['id'],
            'conversation_id'  => (int) $row['conversation_id'],
            'msg_seq'          => (int) $row['msg_seq'],
            'msg_type'         => $row['msg_type'],
            'sender_type'      => $row['sender_type'],
            'sender_id'        => $row['sender_id'],
            'sender_user_id'   => $row['sender_user_id'] !== null ? (int) $row['sender_user_id'] : null,
            'sender_name'      => $row['sender_name'],
            'content'          => $content,
            'preview'          => $row['preview'],
            'search_text'      => $row['search_text'],
            'status'           => (int) $row['status'],
            'trusted_html'     => in_array($row['sender_type'], [IM::ACTOR_AGENT, IM::ACTOR_SYSTEM], true),
            'created_at'       => $row['created_at'],
            'created_at_local' => $this->formatDateTime($row['created_at'] ?? null),
        ];
    }

    private function formatEvent(array $row): array
    {
        return [
            'id'               => (int) $row['id'],
            'conversation_id'  => $row['conversation_id'] !== null ? (int) $row['conversation_id'] : null,
            'event_type'       => $row['event_type'],
            'message_id'       => $row['message_id'] !== null ? (int) $row['message_id'] : null,
            'actor_type'       => $row['actor_type'],
            'actor_id'         => $row['actor_id'],
            'payload'          => $this->decode($row['payload'] ?? ''),
            'created_at'       => $row['created_at'],
            'created_at_local' => $this->formatDateTime($row['created_at'] ?? null),
        ];
    }

    public function cacheStorageEnabled(): bool
    {
        $option = get_option(CustomerService::OPTION_KEY, []);
        if (!is_array($option)) {
            return false;
        }

        $enabled = $option['cacheStorage'] ?? CustomerService::defaultOption()['cacheStorage'];
        return $enabled === true || $enabled === 1 || $enabled === '1';
    }

    private function latestMessageId(int $conversationId): int
    {
        $latest = (int) $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT MAX(`id`) FROM {$this->messagesTable} WHERE `conversation_id` = %d", $conversationId)
        );

        if ($this->cacheStorageEnabled()) {
            $latest = max($latest, $this->latestCachedMessageId($conversationId));
        }

        return $latest;
    }

    private function latestCachedMessageId(int $conversationId): int
    {
        $redis = $this->redis();
        if (!$redis || $conversationId <= 0) {
            return 0;
        }

        $ids = $redis->zRevRange($this->conversationMessagesKey($conversationId), 0, 0) ?: [];
        return isset($ids[0]) ? (int) $ids[0] : 0;
    }

    private function nextCachedId(Redis $redis, string $counterKey, string $table): int
    {
        if ((int) $redis->exists($counterKey) <= 0) {
            $maxId = (int) $this->wpdb->get_var("SELECT MAX(`id`) FROM {$table}");
            $redis->setnx($counterKey, (string) $maxId);
        }

        return (int) $redis->incr($counterKey);
    }

    private function nextCachedEventId(Redis $redis): int
    {
        if ((int) $redis->exists(self::EVENT_COUNTER_KEY) <= 0) {
            $ids   = $redis->zRevRange(self::EVENT_INDEX_KEY, 0, 0) ?: [];
            $maxId = isset($ids[0]) ? (int) $ids[0] : 0;
            $redis->setnx(self::EVENT_COUNTER_KEY, (string) $maxId);
        }

        return (int) $redis->incr(self::EVENT_COUNTER_KEY);
    }

    private function nextCachedMessageSeq(Redis $redis, int $conversationId): int
    {
        $key = $this->conversationSeqKey($conversationId);
        if ((int) $redis->exists($key) <= 0) {
            $dbSeq = (int) $this->wpdb->get_var(
                $this->wpdb->prepare("SELECT COALESCE(MAX(`msg_seq`), 0) FROM {$this->messagesTable} WHERE `conversation_id` = %d", $conversationId)
            );
            $redis->setnx($key, (string) max($dbSeq, $this->latestCachedMessageSeq($redis, $conversationId)));
        }

        $seq = (int) $redis->incr($key);
        $redis->expire($key, self::CACHE_TTL);

        return $seq;
    }

    private function latestCachedMessageSeq(Redis $redis, int $conversationId): int
    {
        $ids = $redis->zRevRange($this->conversationMessagesKey($conversationId), 0, 0) ?: [];
        if (!$ids) {
            return 0;
        }

        $raw = $redis->hGet(self::MESSAGES_CACHE_KEY, (string) $ids[0]);
        if (!is_string($raw) || $raw === '') {
            return 0;
        }

        $row = json_decode($raw, true);
        return is_array($row) ? (int) ($row['msg_seq'] ?? 0) : 0;
    }

    private function messageExists(int $messageId): bool
    {
        if ($messageId <= 0) {
            return false;
        }

        return (bool) $this->wpdb->get_var(
            $this->wpdb->prepare("SELECT 1 FROM {$this->messagesTable} WHERE `id` = %d LIMIT 1", $messageId)
        );
    }

    private function upsertRow(string $table, array $data, string $primaryKey, int $primaryValue): bool
    {
        if ($primaryValue <= 0) {
            return false;
        }

        $exists = (bool) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT 1 FROM {$table} WHERE `{$primaryKey}` = %d LIMIT 1",
            $primaryValue
        ));

        if ($exists) {
            $update = $data;
            unset($update[$primaryKey]);
            $result = $this->wpdb->update($table, $update, [$primaryKey => $primaryValue]);
            return $result !== false;
        }

        $result = $this->wpdb->insert($table, $data);
        return $result !== false;
    }

    private function mergeRowsById(array $primary, array $secondary): array
    {
        $rows = [];
        foreach (array_merge($primary, $secondary) as $row) {
            if (!is_array($row) || !isset($row['id'])) {
                continue;
            }
            $rows[(int) $row['id']] = $row;
        }

        return array_values($rows);
    }

    private function touchCacheKeys(Redis $redis, array $keys): void
    {
        foreach (array_unique(array_filter($keys)) as $key) {
            $redis->expire((string) $key, self::CACHE_TTL);
        }
    }

    private function conversationMessagesKey(int $conversationId): string
    {
        return self::CACHE_GROUP . ':conversation:' . $conversationId . ':messages';
    }

    private function conversationParticipantsKey(int $conversationId): string
    {
        return self::CACHE_GROUP . ':conversation:' . $conversationId . ':participants';
    }

    private function conversationEventsKey(int $conversationId): string
    {
        return self::CACHE_GROUP . ':conversation:' . $conversationId . ':events';
    }

    private function conversationTypeKey(string $type): string
    {
        return self::CACHE_GROUP . ':conversations:type:' . sanitize_key($type);
    }

    private function participantActorKey(int $conversationId, string $actorType, string $actorId): string
    {
        return self::CACHE_GROUP . ':participant:' . $conversationId . ':' . sanitize_key($actorType) . ':' . sanitize_key($actorId);
    }

    private function conversationSeqKey(int $conversationId): string
    {
        return self::CACHE_GROUP . ':conversation:' . $conversationId . ':seq';
    }

    private function normalizeMessageContent(string $msgType, mixed $content): array
    {
        if (is_array($content)) {
            return array_replace(['version' => 1], $this->sanitizeBody($content));
        }

        return [
            'version' => 1,
            'text'    => (string) $content,
            'format'  => $msgType === IM::MESSAGE_TEXT ? 'plain' : $msgType,
        ];
    }

    private function sanitizeBody(array $body): array
    {
        $clean = [];
        foreach ($body as $key => $value) {
            $key = sanitize_key((string) $key);
            if ($key === '') {
                continue;
            }
            if (is_array($value)) {
                $clean[$key] = $this->sanitizeBody($value);
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $clean[$key] = is_string($value) ? trim($value) : $value;
            }
        }
        return $clean;
    }

    private function messagePreview(string $msgType, array $body, string $fallback): string
    {
        if ($fallback !== '') {
            return $fallback;
        }

        foreach (['text', 'caption', 'title', 'name'] as $key) {
            if (!empty($body[$key]) && is_scalar($body[$key])) {
                return (string) $body[$key];
            }
        }

        return '[' . $msgType . ']';
    }

    private function messageSearchText(array $body, string $preview): string
    {
        $parts = [$preview];
        foreach (['text', 'caption', 'title', 'name', 'description'] as $key) {
            if (!empty($body[$key]) && is_scalar($body[$key])) {
                $parts[] = (string) $body[$key];
            }
        }
        return trim(wp_strip_all_tags(implode(' ', array_unique($parts))));
    }

    private function sanitizeMeta(mixed $meta): array
    {
        if (!is_array($meta)) {
            return [];
        }

        $clean = [];
        foreach (array_slice($meta, 0, 50, true) as $key => $value) {
            if (!is_scalar($value) && $value !== null) {
                continue;
            }
            $clean[sanitize_key((string) $key)] = is_string($value) ? sanitize_text_field($value) : $value;
        }
        return $clean;
    }

    private function formatDateTime(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $timestamp = strtotime($value . ' UTC');
        if (!$timestamp) {
            return $value;
        }

        $formatted = Date::dateTime($timestamp);
        return is_string($formatted) ? $formatted : $value;
    }

    private function encode(mixed $value): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }

        return Type::arrayToJson(is_array($value) ? $value : [$value]);
    }

    private function decode(?string $value): array
    {
        if (!$value) {
            return [];
        }

        return Type::jsonToArray($value);
    }

    private function redis(): ?Redis
    {
        if ($this->redisClient instanceof Redis) {
            return $this->redisClient;
        }

        /** @var RedisService $redisService */
        $redisService      = $this->container->get(RedisService::class);
        $this->redisClient = $redisService->init(DBService::IM_REDIS_DB);
        return $this->redisClient;
    }

    private function realtime(): IMRealtimeService
    {
        return $this->container->get(IMRealtimeService::class);
    }
}
