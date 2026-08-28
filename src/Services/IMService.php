<?php
namespace JEALER\G3\Services;
use JEALER\G3\Core\IM\IM;
use JEALER\G3\Core\Service\Service;
use JEALER\G3\Utilities\Date;
use JEALER\G3\Utilities\System;
use JEALER\G3\Utilities\Type;
use Redis;
use Throwable;
use WP_Error;

class IMService extends Service {
    private string $conversationsTable;
    private string $participantsTable;
    private string $messagesTable;
    private string $eventsTable;

    public function __construct()
    {
        parent::__construct();
        $this->conversationsTable = $this->wpdb->prefix . 'g3_im_conversations';
        $this->participantsTable  = $this->wpdb->prefix . 'g3_im_participants';
        $this->messagesTable      = $this->wpdb->prefix . 'g3_im_messages';
        $this->eventsTable        = $this->wpdb->prefix . 'g3_im_events';
    }

    public function createConversation(array $identity, array $data): int|WP_Error
    {
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

        $row = $this->wpdb->get_row($this->wpdb->prepare($sql, $params), ARRAY_A);
        return is_array($row) ? $row : null;
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
        $result               = $this->wpdb->update($this->conversationsTable, $update, ['id' => $conversationId]);
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
            $messageId = (int) $this->wpdb->get_var(
                $this->wpdb->prepare("SELECT MAX(`id`) FROM {$this->messagesTable} WHERE `conversation_id` = %d", $conversationId)
            );
        }

        $this->ensureParticipant($conversationId, $identity);
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

        return ['message_id' => $messageId];
    }

    public function events(int $afterId = 0, ?int $conversationId = null, int $limit = 50): array
    {
        $limit  = min(200, max(1, $limit));
        $where  = ['`id` > %d'];
        $params = [max(0, $afterId)];

        if ($conversationId) {
            $where[]  = '(`conversation_id` = %d OR `conversation_id` IS NULL)';
            $params[] = $conversationId;
        }

        $params[] = $limit;
        $sql      = "SELECT * FROM {$this->eventsTable} WHERE " . implode(' AND ', $where) . ' ORDER BY `id` ASC LIMIT %d';
        $rows     = $this->wpdb->get_results($this->wpdb->prepare($sql, $params), ARRAY_A) ?: [];

        return array_map(fn(array $row): array => $this->formatEvent($row), $rows);
    }

    public function createStreamSession(string $conversationType, string $scope, ?int $conversationId, int $afterId, int $heartbeat): array|WP_Error
    {
        return $this->realtime()->createSession($conversationType, $scope, $conversationId, $afterId, $heartbeat);
    }

    public function latestEventId(string $conversationType): int
    {
        return $this->realtime()->latestEventId($conversationType);
    }

    public function touchPresence(string $scope, int|string $id): void
    {
        try {
            $redis = $this->redis();
            $redis?->setex('g3:im:presence:' . sanitize_key($scope) . ':' . sanitize_key((string) $id), 60, (string) time());
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
            return ['cutoff' => $cutoff, 'conversations' => 0, 'messages' => 0, 'participants' => 0, 'events' => 0];
        }

        $ids           = array_map('intval', $ids);
        $placeholders  = implode(',', array_fill(0, count($ids), '%d'));
        $messages      = (int) $this->wpdb->query($this->wpdb->prepare("DELETE FROM {$this->messagesTable} WHERE `conversation_id` IN ({$placeholders})", $ids));
        $participants  = (int) $this->wpdb->query($this->wpdb->prepare("DELETE FROM {$this->participantsTable} WHERE `conversation_id` IN ({$placeholders})", $ids));
        $events        = (int) $this->wpdb->query($this->wpdb->prepare("DELETE FROM {$this->eventsTable} WHERE `conversation_id` IN ({$placeholders})", $ids));
        $conversations = (int) $this->wpdb->query($this->wpdb->prepare("DELETE FROM {$this->conversationsTable} WHERE `id` IN ({$placeholders})", $ids));

        return [
            'cutoff'        => $cutoff,
            'conversations' => max(0, $conversations),
            'messages'      => max(0, $messages),
            'participants'  => max(0, $participants),
            'events'        => max(0, $events),
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

    private function getMessage(int $messageId): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->messagesTable} WHERE `id` = %d", $messageId),
            ARRAY_A
        );
        return is_array($row) ? $this->formatMessage($row) : null;
    }

    private function ensureParticipant(int $conversationId, array $identity): void
    {
        $this->wpdb->query($this->wpdb->prepare(
            "INSERT INTO {$this->participantsTable}
                (`conversation_id`, `actor_type`, `actor_id`, `user_id`, `role`, `display_name`, `avatar`, `last_seen_at`, `created_at`)
             VALUES (%d, %s, %s, %d, %s, %s, %s, %s, %s)
             ON DUPLICATE KEY UPDATE
                `display_name` = VALUES(`display_name`),
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

    private function publishEvent(string $type, ?int $conversationId, ?int $messageId, array $identity, array $payload, string $conversationType): void
    {
        $this->wpdb->insert($this->eventsTable, [
            'conversation_id' => $conversationId,
            'event_type'      => $type,
            'message_id'      => $messageId,
            'actor_type'      => $identity['actor_type'],
            'actor_id'        => $identity['actor_id'],
            'payload'         => $this->encode($payload),
            'created_at'      => Date::utcDateTime(),
        ]);

        $eventId = (int) $this->wpdb->insert_id;
        $event   = [
            'id'              => $eventId,
            'conversation_id' => $conversationId,
            'event_type'      => $type,
            'message_id'      => $messageId,
            'actor_type'      => $identity['actor_type'],
            'actor_id'        => $identity['actor_id'],
            'payload'         => $payload,
            'created_at'      => Date::utcDateTime(),
        ];

        $notificationPayload = array_replace($payload, ['event' => $event]);
        $meta                = [
            'target_type' => 'im_conversation',
            'target_id'   => $conversationId ? (string) $conversationId : null,
            'actor_type'  => $identity['actor_type'],
            'actor_id'    => $identity['actor_id'],
        ];

        $this->realtime()->publish($conversationType, $conversationId, $type, $notificationPayload, $meta);
    }

    private function formatConversation(array $row): array
    {
        return [
            'id'                  => (int) $row['id'],
            'type'                => $row['type'],
            'subject'             => $row['subject'],
            'state'               => $row['state'],
            'priority'            => (int) $row['priority'],
            'source'              => $row['source'],
            'last_message_id'     => $row['last_message_id'] !== null ? (int) $row['last_message_id'] : null,
            'last_msg_seq'        => (int) $row['last_msg_seq'],
            'last_msg_type'       => $row['last_msg_type'],
            'last_msg_preview'    => $row['last_msg_preview'],
            'last_message_at'     => $this->formatDateTime($row['last_message_at'] ?? null),
            'last_message_at_utc' => $row['last_message_at'],
            'meta'                => $this->decode($row['meta'] ?? ''),
            'created_at'          => $this->formatDateTime($row['created_at'] ?? null),
            'created_at_utc'      => $row['created_at'],
            'updated_at'          => $this->formatDateTime($row['updated_at'] ?? null),
            'updated_at_utc'      => $row['updated_at'],
            'closed_at'           => $this->formatDateTime($row['closed_at'] ?? null),
            'closed_at_utc'       => $row['closed_at'],
        ];
    }

    private function formatMessage(array $row): array
    {
        $content = $this->decode($row['content'] ?? '');
        return [
            'id'              => (int) $row['id'],
            'conversation_id' => (int) $row['conversation_id'],
            'msg_seq'         => (int) $row['msg_seq'],
            'msg_type'        => $row['msg_type'],
            'sender_type'     => $row['sender_type'],
            'sender_id'       => $row['sender_id'],
            'sender_user_id'  => $row['sender_user_id'] !== null ? (int) $row['sender_user_id'] : null,
            'sender_name'     => $row['sender_name'],
            'content'         => $content,
            'preview'         => $row['preview'],
            'search_text'     => $row['search_text'],
            'status'          => (int) $row['status'],
            'trusted_html'    => in_array($row['sender_type'], [IM::ACTOR_AGENT, IM::ACTOR_SYSTEM], true),
            'created_at'      => $this->formatDateTime($row['created_at'] ?? null),
            'created_at_utc'  => $row['created_at'],
        ];
    }

    private function formatEvent(array $row): array
    {
        return [
            'id'              => (int) $row['id'],
            'conversation_id' => $row['conversation_id'] !== null ? (int) $row['conversation_id'] : null,
            'event_type'      => $row['event_type'],
            'message_id'      => $row['message_id'] !== null ? (int) $row['message_id'] : null,
            'actor_type'      => $row['actor_type'],
            'actor_id'        => $row['actor_id'],
            'payload'         => $this->decode($row['payload'] ?? ''),
            'created_at'      => $this->formatDateTime($row['created_at'] ?? null),
            'created_at_utc'  => $row['created_at'],
        ];
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
        try {
            $redis = $this->container->get(Redis::class);
            $redis->connect('127.0.0.1', 6379, 0.2);
            $redis->select(DBService::IM_REDIS_DB);
            return $redis;
        }
        catch (Throwable) {
            return null;
        }
    }

    private function realtime(): IMRealtimeService
    {
        return $this->container->get(IMRealtimeService::class);
    }
}
