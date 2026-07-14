<?php
namespace JEALER\G3\Services;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Core\IM\IM;
use JEALER\G3\Core\Service\Service;
use JEALER\G3\Utilities\Date;
use JEALER\G3\Utilities\State;
use JEALER\G3\Utilities\System;
use Redis;
use Throwable;
use WP_Error;
use wpdb;

class CustomerService extends Service {
    public const OPTION_KEY      = 'g3_option_customer_service';
    public const COOKIE_GUEST_ID = 'g3_cs_guest_id';
    public const CACHE_GROUP     = 'g3_customer_service';

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

    public static function defaultOption(): array
    {
        return [
            'enable'           => '0',
            'title'            => __('Online Service'),
            'announcement'     => '',
            'announcementLink' => '',
            'welcomeTip'       => __('Hello, how can we help you?'),
            'welcomeMessage'   => __('Welcome. Please leave your message here.'),
            'offlineMessage'   => __('Please leave a message. We will reply as soon as possible.'),
            'workDays'         => ['1', '2', '3', '4', '5'],
            'workStart'        => '09:00',
            'workEnd'          => '18:00',
            'guestName'        => __('Guest'),
            'retentionDays'    => 180,
            'heartbeatSeconds' => 45,
            'timeoutMinutes'   => 120,
            'fallbackMessage'  => __('The service is temporarily unavailable. Please try again later.'),
        ];
    }

    public function option(): array
    {
        $option = get_option(self::OPTION_KEY, null);
        return is_array($option) ? array_replace(self::defaultOption(), $option) : self::defaultOption();
    }

    public function enabled(): bool
    {
        return ($this->option()['enable'] ?? '0') === '1';
    }

    public function publicConfig(): array
    {
        $option = $this->option();
        $z      = $this->z();
        return [
            'enabled'          => $this->enabled(),
            'title'            => (string) $option['title'],
            'welcomeTip'       => $z ? (string) $option['welcomeTip'] : '',
            'welcomeMessage'   => $z ? (string) $option['welcomeMessage'] : '',
            'announcement'     => (string) $option['announcement'],
            'announcementLink' => (string) $option['announcementLink'],
            'offlineMessage'   => $z ? (string) $option['offlineMessage'] : '',
            'working'          => $this->withinWorkingHours(),
            'guestId'          => $this->guestId(false),
            'z'                => $z,
            'heartbeatSeconds' => (int) $option['heartbeatSeconds'],
        ];
    }

    public function guestId(bool $create = true): string
    {
        $guestId = isset($_COOKIE[self::COOKIE_GUEST_ID])
            ? sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE_GUEST_ID]))
            : '';

        if ($guestId !== '' && preg_match('/^[a-zA-Z0-9_\-]{16,64}$/', $guestId)) {
            return $guestId;
        }

        if (!$create) {
            return '';
        }

        $guestId = function_exists('wp_generate_uuid4')
            ? str_replace('-', '', wp_generate_uuid4())
            : bin2hex(random_bytes(16));

        $this->setGuestCookie($guestId);
        $_COOKIE[self::COOKIE_GUEST_ID] = $guestId;

        return $guestId;
    }

    public function startConversation(array $data): array|WP_Error
    {
        if (!$this->enabled()) {
            return new WP_Error('customer_service_disabled', 'Customer service is disabled.', ['status' => 403]);
        }

        $identity     = $this->customerIdentity();
        $conversation = $this->findOpenCustomerConversation($identity);
        $created      = false;

        if (!$conversation) {
            $conversationId = $this->createConversation($identity, $data);
            if (is_wp_error($conversationId)) {
                return $conversationId;
            }
            $conversation = $this->getConversation($conversationId);
            $created      = true;
        }

        if (!$conversation) {
            return new WP_Error('conversation_not_found', 'Conversation not found.', ['status' => 404]);
        }

        $content = trim((string) ($data['content'] ?? ''));
        if ($created) {
            $this->createOfflineMessage((int) $conversation['id']);
        }

        if ($content !== '') {
            $message = $this->addMessage((int) $conversation['id'], $identity, $content, IM::MESSAGE_TEXT);
            if (is_wp_error($message)) {
                return $message;
            }
        }

        return [
            'conversation' => $this->formatConversation($this->getConversation((int) $conversation['id']) ?: $conversation),
            'messages'     => $this->messages((int) $conversation['id'], 0, 50),
        ];
    }

    public function sendCustomerMessage(int $conversationId, array $data): array|WP_Error
    {
        if (!$this->enabled()) {
            return new WP_Error('customer_service_disabled', 'Customer service is disabled.', ['status' => 403]);
        }

        $identity = $this->customerIdentity();
        if (!$this->canAccessConversation($conversationId, $identity)) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        $conversation = $this->getConversation($conversationId);
        if (!$conversation) {
            return new WP_Error('conversation_not_found', 'Conversation not found.', ['status' => 404]);
        }

        if ($this->finalStatus((string) $conversation['status'])) {
            $conversationId = $this->createConversation($identity, [
                'source' => $conversation['source'] ?? 'web',
                'meta'   => $this->decode($conversation['meta'] ?? ''),
            ]);
            if (is_wp_error($conversationId)) {
                return $conversationId;
            }
            $this->createOfflineMessage($conversationId);
        }

        return $this->addMessage($conversationId, $identity, (string) ($data['content'] ?? ''), (string) ($data['message_type'] ?? IM::MESSAGE_TEXT));
    }

    public function sendAgentMessage(int $conversationId, array $data): array|WP_Error
    {
        if (!$this->canManage()) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        $identity     = $this->agentIdentity();
        $conversation = $this->getConversation($conversationId);
        if (!$conversation) {
            return new WP_Error('conversation_not_found', 'Conversation not found.', ['status' => 404]);
        }

        if (empty($conversation['assignee_user_id'])) {
            $this->assignConversation($conversationId, $this->currentUserId());
        }

        $message = $this->addMessage($conversationId, $identity, (string) ($data['content'] ?? ''), (string) ($data['message_type'] ?? IM::MESSAGE_TEXT));
        if (!is_wp_error($message) && in_array((string) $conversation['status'], [IM::STATUS_PENDING, IM::STATUS_BOT_HANDLED], true)) {
            $this->setConversationStatus($conversationId, IM::STATUS_HANDLED, $identity);
        }

        return $message;
    }
    /**
     * Get paginated customer service conversation list
     * 
     * 分页获取客服对话列表
     *
     * @param array $args {
     *     @type string $status 对话状态筛选：pending/bot-handled/handled/onHold/closed/timeout
     *     @type int $cursor 分页游标，从指定ID之前的记录开始查询
     *     @type int $limit 每页数量，范围1-100，默认30
     *     @type string $search 搜索关键词，匹配主题、消息摘要或客户访客ID
     * }
     * @return array|WP_Error 成功返回包含items、next_cursor、has_more的数组，失败返回WP_Error
     * @example
     *     // 获取待处理对话列表
     *     $result = $customerService->listConversations(['status' => 'pending']);
     *     // 获取搜索结果
     *     $result = $customerService->listConversations(['search' => '订单问题']);
     * @throws WP_Error 无权限时返回403错误
     * @since 1.0.0
     * @author WangShai
     */
    public function listConversations(array $args = []): array|WP_Error
    {
        if (!$this->canManage()) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        $status = (string) ($args['status'] ?? '');
        $cursor = max(0, (int) ($args['cursor'] ?? 0));
        $limit  = min(100, max(1, (int) ($args['limit'] ?? 30)));
        $search = trim((string) ($args['search'] ?? ''));

        $where  = ["`type` = %s"];
        $params = [IM::TYPE_CUSTOMER_SERVICE];

        if (in_array($status, $this->conversationStatuses(), true)) {
            $where[]  = "`status` = %s";
            $params[] = $status;
        }

        if ($cursor > 0) {
            $where[]  = "`id` < %d";
            $params[] = $cursor;
        }

        if ($search !== '') {
            $like    = '%' . $this->wpdb->esc_like($search) . '%';
            $where[] = "(`subject` LIKE %s OR `last_message_excerpt` LIKE %s OR `customer_guest_id` LIKE %s)";
            array_push($params, $like, $like, $like);
        }

        $params[] = $limit + 1;
        $sql      = "SELECT * FROM {$this->conversationsTable} WHERE " . implode(' AND ', $where) . " ORDER BY `last_message_at` DESC, `id` DESC LIMIT %d";
        $rows     = $this->wpdb->get_results($this->wpdb->prepare($sql, $params), ARRAY_A) ?: [];

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $items = array_map(fn(array $row): array => $this->formatConversation($row), $rows);

        $last = $items ? $items[array_key_last($items)] : null;

        return [
            'items'       => $items,
            'next_cursor' => $hasMore && $last ? (int) $last['id'] : null,
            'has_more'    => $hasMore,
        ];
    }

    public function getConversationForViewer(int $conversationId): array|WP_Error
    {
        $identity = $this->canManage() ? null : $this->customerIdentity(false);
        if (!$this->canManage() && (!$identity || !$this->canAccessConversation($conversationId, $identity))) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        $conversation = $this->getConversation($conversationId);
        if (!$conversation) {
            return new WP_Error('conversation_not_found', 'Conversation not found.', ['status' => 404]);
        }

        return $this->formatConversation($conversation);
    }

    public function messagesForViewer(int $conversationId, int $afterId = 0, int $limit = 50): array|WP_Error
    {
        $identity = $this->canManage() ? null : $this->customerIdentity(false);
        if (!$this->canManage() && (!$identity || !$this->canAccessConversation($conversationId, $identity))) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        return $this->messages($conversationId, $afterId, $limit);
    }

    public function updateConversation(int $conversationId, array $data): array|WP_Error
    {
        if (!$this->canManage()) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        $update = [];
        if (isset($data['status'])) {
            $status = (string) $data['status'];
            if (!in_array($status, [IM::STATUS_PENDING, IM::STATUS_HANDLED, IM::STATUS_ON_HOLD, IM::STATUS_CLOSED], true)) {
                return new WP_Error('invalid_status', 'Invalid status.', ['status' => 400]);
            }
            $update['status']    = $status;
            $update['closed_at'] = $status === IM::STATUS_CLOSED ? Date::utcDateTime() : null;
        }

        if (array_key_exists('assignee_user_id', $data)) {
            $update['assignee_user_id'] = $data['assignee_user_id'] ? (int) $data['assignee_user_id'] : null;
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
            return $this->getConversationForViewer($conversationId);
        }

        $update['updated_at'] = Date::utcDateTime();
        $result               = $this->wpdb->update($this->conversationsTable, $update, ['id' => $conversationId]);
        if ($result === false) {
            return new WP_Error('db_update_error', 'Failed to update conversation.', ['status' => 500]);
        }

        $conversation = $this->getConversation($conversationId);
        $eventType    = isset($update['status']) ? IM::EVENT_CONVERSATION_STATUS_CHANGED : IM::EVENT_CONVERSATION_UPDATED;
        $this->publishEvent($eventType, $conversationId, null, $this->agentIdentity(), [
            'update'       => $update,
            'conversation' => $conversation ? $this->formatConversation($conversation) : null,
        ]);

        return $this->getConversationForViewer($conversationId);
    }

    public function createStreamSession(array $data): array|WP_Error
    {
        $scope     = (string) ($data['scope'] ?? 'viewer');
        $afterId   = max(0, (int) ($data['after_id'] ?? 0));
        $heartbeat = min(60, max(30, (int) ($this->option()['heartbeatSeconds'] ?? 45)));

        if ($scope === 'admin') {
            if (!$this->canManage()) {
                return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
            }

            return $this->notifications()->createSession($this->adminChannel(), $afterId, $heartbeat);
        }

        $conversationId = max(0, (int) ($data['conversation_id'] ?? 0));
        if ($conversationId <= 0) {
            return new WP_Error('conversation_required', 'Conversation is required.', ['status' => 400]);
        }

        $identity = $this->customerIdentity(false);
        if (!$identity || !$this->canAccessConversation($conversationId, $identity)) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        return $this->notifications()->createSession($this->viewerChannel($conversationId), $afterId, $heartbeat);
    }

    public function markRead(int $conversationId, int $messageId = 0): array|WP_Error
    {
        $identity = $this->canManage() ? $this->agentIdentity() : $this->customerIdentity(false);
        if (!$identity || (!$this->canManage() && !$this->canAccessConversation($conversationId, $identity))) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

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

        $counter = $identity['role'] === IM::ROLE_AGENT ? 'unread_agent' : 'unread_customer';
        $this->wpdb->update($this->conversationsTable, [$counter => 0], ['id' => $conversationId]);
        $this->publishEvent(IM::EVENT_PARTICIPANT_READ, $conversationId, $messageId, $identity, ['message_id' => $messageId]);

        return ['message_id' => $messageId];
    }

    public function eventsForViewer(int $afterId = 0, ?int $conversationId = null, int $limit = 50): array|WP_Error
    {
        $limit = min(200, max(1, $limit));

        if (!$this->canManage()) {
            if (!$conversationId) {
                return new WP_Error('conversation_required', 'Conversation is required.', ['status' => 400]);
            }
            $identity = $this->customerIdentity(false);
            if (!$identity || !$this->canAccessConversation($conversationId, $identity)) {
                return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
            }
        }

        $where  = ['`id` > %d'];
        $params = [max(0, $afterId)];

        if ($conversationId) {
            $where[]  = '(`conversation_id` = %d OR `conversation_id` IS NULL)';
            $params[] = $conversationId;
        }

        $params[] = $limit;
        $sql      = "SELECT * FROM {$this->eventsTable} WHERE " . implode(' AND ', $where) . " ORDER BY `id` ASC LIMIT %d";
        $rows     = $this->wpdb->get_results($this->wpdb->prepare($sql, $params), ARRAY_A) ?: [];

        return array_map(fn(array $row): array => $this->formatEvent($row), $rows);
    }

    public function latestEventId(): int
    {
        try {
            return $this->notifications()->latestId($this->adminChannel());
        }
        catch (Throwable) {
            return 0;
        }
    }

    public function touchPresence(string $scope, int|string $id): void
    {
        try {
            $redis = $this->container->get(Redis::class);
            $redis->connect('127.0.0.1', 6379, 0.2);
            $redis->setex('g3:customer:presence:' . $scope . ':' . $id, 60, (string) time());
        }
        catch (Throwable) {
        }
    }

    public function canManage(): bool
    {
        return $this->isLoggedIn() && $this->currentUserCan('manage_options');
    }

    public function customerProfile(int $conversationId): array|WP_Error
    {
        if (!$this->canManage()) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        $conversation = $this->getConversation($conversationId);
        if (!$conversation) {
            return new WP_Error('conversation_not_found', 'Conversation not found.', ['status' => 404]);
        }

        $user = !empty($conversation['customer_user_id']) ? get_userdata((int) $conversation['customer_user_id']) : null;
        return [
            'conversation' => $this->formatConversation($conversation),
            'user'         => $user ? [
                'id'           => (int) $user->ID,
                'login'        => $user->user_login,
                'display_name' => $user->display_name,
                'email'        => $user->user_email,
                'registered'   => $user->user_registered,
            ] : null,
            'guest'        => empty($conversation['customer_user_id']) ? [
                'id'         => $conversation['customer_guest_id'],
                'ip_address' => $conversation['ip_address'],
                'user_agent' => $conversation['user_agent'],
            ] : null,
        ];
    }

    public function withinWorkingHours(): bool
    {
        if (!$this->z()) return true;
        $option   = $this->option();
        $workDays = $option['workDays'] ?? [];

        if (!is_array($workDays)) {
            return false;
        }

        $days = array_values(array_intersect(['1', '2', '3', '4', '5', '6', '7'], array_map('strval', $workDays)));
        if (!$days) {
            return false;
        }

        $now  = current_datetime();
        $day  = $now->format('N');
        $time = $now->format('H:i');

        if (!in_array((string) $day, $days, true)) {
            return false;
        }

        $start = $this->normalizeTime((string) ($option['workStart'] ?? '09:00'), '09:00');
        $end   = $this->normalizeTime((string) ($option['workEnd'] ?? '18:00'), '18:00');

        if ($start === $end) {
            return true;
        }

        if ($start < $end) {
            return $time >= $start && $time <= $end;
        }

        return $time >= $start || $time <= $end;
    }

    public function cleanupBeforeDays(int $days): array
    {
        $days   = max(1, min(3650, $days));
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        $ids = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT `id`
             FROM {$this->conversationsTable}
             WHERE `type` = %s
               AND (
                    (`updated_at` IS NOT NULL AND `updated_at` < %s)
                    OR (`updated_at` IS NULL AND `created_at` < %s)
               )
             ORDER BY `updated_at` ASC, `id` ASC
             LIMIT 1000",
            IM::TYPE_CUSTOMER_SERVICE,
            $cutoff,
            $cutoff
        )) ?: [];

        if (!$ids) {
            return [
                'cutoff'        => $cutoff,
                'conversations' => 0,
                'messages'      => 0,
                'participants'  => 0,
                'events'        => 0,
            ];
        }

        $ids          = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $messages = (int) $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->messagesTable} WHERE `conversation_id` IN ({$placeholders})",
            $ids
        ));

        $participants = (int) $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->participantsTable} WHERE `conversation_id` IN ({$placeholders})",
            $ids
        ));

        $events = (int) $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->eventsTable} WHERE `conversation_id` IN ({$placeholders})",
            $ids
        ));

        $conversations = (int) $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->conversationsTable} WHERE `id` IN ({$placeholders})",
            $ids
        ));

        return [
            'cutoff'        => $cutoff,
            'conversations' => max(0, $conversations),
            'messages'      => max(0, $messages),
            'participants'  => max(0, $participants),
            'events'        => max(0, $events),
        ];
    }

    public function markTimeoutConversations(int $minutes = 0, int $limit = 200): int
    {
        $minutes = $minutes > 0 ? $minutes : (int) ($this->option()['timeoutMinutes'] ?? 120);
        $minutes = max(1, min(14400, $minutes));
        $limit   = min(1000, max(1, $limit));
        $cutoff  = gmdate('Y-m-d H:i:s', time() - ($minutes * MINUTE_IN_SECONDS));

        $statuses     = [IM::STATUS_PENDING, IM::STATUS_BOT_HANDLED, IM::STATUS_HANDLED, IM::STATUS_ON_HOLD];
        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $ids          = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT `id`
             FROM {$this->conversationsTable}
             WHERE `type` = %s
               AND `status` IN ({$placeholders})
               AND COALESCE(`last_message_at`, `updated_at`, `created_at`) < %s
             ORDER BY COALESCE(`last_message_at`, `updated_at`, `created_at`) ASC
             LIMIT %d",
            array_merge([IM::TYPE_CUSTOMER_SERVICE], $statuses, [$cutoff, $limit])
        )) ?: [];

        if (!$ids) {
            return 0;
        }

        $identity = $this->systemIdentity();
        foreach (array_map('intval', $ids) as $id) {
            $this->setConversationStatus($id, IM::STATUS_TIMEOUT, $identity);
        }

        return count($ids);
    }

    private function createConversation(array $identity, array $data): int|WP_Error
    {
        $now     = Date::utcDateTime();
        $subject = sanitize_text_field((string) ($data['subject'] ?? ''));
        if ($subject === '') {
            $subject = $identity['display_name'];
        }

        $insert = [
            'type'              => IM::TYPE_CUSTOMER_SERVICE,
            'subject'           => mb_substr($subject, 0, 255),
            'customer_user_id'  => $identity['user_id'],
            'customer_guest_id' => $identity['actor_type'] === IM::ACTOR_GUEST ? $identity['actor_id'] : null,
            'status'            => IM::STATUS_PENDING,
            'source'            => sanitize_key((string) ($data['source'] ?? 'web')),
            'ip_address'        => System::ip() ?: null,
            'user_agent'        => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 255) : null,
            'meta'              => $this->encode($this->sanitizeMeta($data['meta'] ?? [])),
            'created_at'        => $now,
            'updated_at'        => $now,
        ];

        $result = $this->wpdb->insert($this->conversationsTable, $insert);
        if ($result === false) {
            return new WP_Error('db_insert_error', 'Failed to create conversation.', ['status' => 500]);
        }

        $conversationId = (int) $this->wpdb->insert_id;
        $this->ensureParticipant($conversationId, $identity);
        $this->publishEvent(IM::EVENT_CONVERSATION_CREATED, $conversationId, null, $identity, ['conversation_id' => $conversationId]);

        return $conversationId;
    }

    private function addMessage(int $conversationId, array $identity, string $content, string $messageType): array|WP_Error
    {
        $trusted = in_array($identity['role'] ?? '', [IM::ROLE_AGENT, IM::ROLE_SYSTEM], true);
        $content = $this->sanitizeMessageContent($content, $trusted);
        if ($content === '') {
            return new WP_Error('empty_message', __('Message content cannot be empty.', 'G3'), ['status' => 400]);
        }
        if (mb_strlen(wp_strip_all_tags($content)) > 5000) {
            return new WP_Error('message_too_long', __('Message is too long.', 'G3'), ['status' => 400]);
        }

        $messageType = sanitize_key($messageType) ?: IM::MESSAGE_TEXT;
        $now         = Date::utcDateTime();
        $this->ensureParticipant($conversationId, $identity);

        $result = $this->wpdb->insert($this->messagesTable, [
            'conversation_id' => $conversationId,
            'sender_type'     => $identity['actor_type'],
            'sender_id'       => $identity['actor_id'],
            'sender_user_id'  => $identity['user_id'],
            'sender_name'     => $identity['display_name'],
            'message_type'    => $messageType,
            'content'         => $content,
            'created_at'      => $now,
        ]);

        if ($result === false) {
            return new WP_Error('db_insert_error', 'Failed to save message.', ['status' => 500]);
        }

        $messageId   = (int) $this->wpdb->insert_id;
        $outbound    = in_array($identity['role'], [IM::ROLE_AGENT, IM::ROLE_SYSTEM], true);
        $unreadField = $outbound ? 'unread_customer' : 'unread_agent';
        $excerpt     = mb_substr(wp_strip_all_tags($content), 0, 120);

        $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->conversationsTable}
             SET `last_message_id` = %d,
                 `last_message_excerpt` = %s,
                 `last_message_at` = %s,
                 `updated_at` = %s,
                 `$unreadField` = `$unreadField` + 1
             WHERE `id` = %d",
            $messageId,
            $excerpt,
            $now,
            $now,
            $conversationId
        ));

        $message      = $this->getMessage($messageId);
        $conversation = $this->getConversation($conversationId);
        $this->publishEvent(IM::EVENT_MESSAGE_CREATED, $conversationId, $messageId, $identity, [
            'message'      => $message,
            'conversation' => $conversation ? $this->formatConversation($conversation) : null,
        ]);

        return $message ?: new WP_Error('message_not_found', __('Message not found.', 'G3'), ['status' => 404]);
    }

    private function messages(int $conversationId, int $afterId = 0, int $limit = 50): array
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

    private function getConversation(int $conversationId): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->conversationsTable} WHERE `id` = %d", $conversationId),
            ARRAY_A
        );
        return is_array($row) ? $row : null;
    }

    private function getMessage(int $messageId): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->messagesTable} WHERE `id` = %d", $messageId),
            ARRAY_A
        );
        return is_array($row) ? $this->formatMessage($row) : null;
    }

    private function createOfflineMessage(int $conversationId): void
    {
        $option = $this->option();

        if (!$this->z()) {
            return;
        }

        if ($this->withinWorkingHours()) {
            return;
        }

        $offline = trim((string) ($option['offlineMessage'] ?? ''));
        if ($offline !== '') {
            $identity = $this->systemIdentity();
            $message  = $this->addMessage($conversationId, $identity, $offline, IM::MESSAGE_OFFLINE);
            if (!is_wp_error($message)) {
                $this->setConversationStatus($conversationId, IM::STATUS_BOT_HANDLED, $identity);
            }
        }
    }

    private function findOpenCustomerConversation(array $identity): ?array
    {
        if ($identity['actor_type'] === IM::ACTOR_USER && $identity['user_id']) {
            $row = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->conversationsTable}
                     WHERE `type` = %s AND `customer_user_id` = %d AND `status` IN (%s, %s, %s, %s)
                     ORDER BY `updated_at` DESC, `id` DESC LIMIT 1",
                    IM::TYPE_CUSTOMER_SERVICE,
                    $identity['user_id'],
                    IM::STATUS_PENDING,
                    IM::STATUS_BOT_HANDLED,
                    IM::STATUS_HANDLED,
                    IM::STATUS_ON_HOLD
                ),
                ARRAY_A
            );
        } else {
            $row = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->conversationsTable}
                     WHERE `type` = %s AND `customer_guest_id` = %s AND `status` IN (%s, %s, %s, %s)
                     ORDER BY `updated_at` DESC, `id` DESC LIMIT 1",
                    IM::TYPE_CUSTOMER_SERVICE,
                    $identity['actor_id'],
                    IM::STATUS_PENDING,
                    IM::STATUS_BOT_HANDLED,
                    IM::STATUS_HANDLED,
                    IM::STATUS_ON_HOLD
                ),
                ARRAY_A
            );
        }

        return is_array($row) ? $row : null;
    }

    private function canAccessConversation(int $conversationId, array $identity): bool
    {
        if ($this->canManage()) {
            return true;
        }

        $conversation = $this->getConversation($conversationId);
        if (!$conversation) {
            return false;
        }

        if ($identity['actor_type'] === IM::ACTOR_USER) {
            return (int) ($conversation['customer_user_id'] ?? 0) === (int) $identity['user_id'];
        }

        return hash_equals((string) ($conversation['customer_guest_id'] ?? ''), (string) $identity['actor_id']);
    }

    private function customerIdentity(bool $createGuest = true): ?array
    {
        if ($this->isLoggedIn()) {
            $user = $this->currentUser();
            return [
                'actor_type'   => IM::ACTOR_USER,
                'actor_id'     => (string) $user->ID,
                'user_id'      => (int) $user->ID,
                'role'         => IM::ROLE_CUSTOMER,
                'display_name' => $user->display_name ?: $user->user_login,
                'avatar'       => $this->avatarUrl((int) $user->ID),
            ];
        }

        $guestId = $this->guestId($createGuest);
        if ($guestId === '') {
            return null;
        }

        return [
            'actor_type'   => IM::ACTOR_GUEST,
            'actor_id'     => $guestId,
            'user_id'      => null,
            'role'         => IM::ROLE_CUSTOMER,
            'display_name' => (string) ($this->option()['guestName'] ?? __('Guest', 'G3')),
            'avatar'       => '',
        ];
    }

    private function agentIdentity(): array
    {
        $user = $this->currentUser();
        return [
            'actor_type'   => IM::ACTOR_AGENT,
            'actor_id'     => (string) $user->ID,
            'user_id'      => (int) $user->ID,
            'role'         => IM::ROLE_AGENT,
            'display_name' => $user->display_name ?: $user->user_login,
            'avatar'       => $this->avatarUrl((int) $user->ID),
        ];
    }

    private function systemIdentity(): array
    {
        return [
            'actor_type'   => IM::ACTOR_SYSTEM,
            'actor_id'     => 'system',
            'user_id'      => null,
            'role'         => IM::ROLE_SYSTEM,
            'display_name' => (string) (function_exists('get_bloginfo') ? get_bloginfo('name') : 'System'),
            'avatar'       => '',
        ];
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

    private function assignConversation(int $conversationId, int $userId): void
    {
        $this->wpdb->update($this->conversationsTable, [
            'assignee_user_id' => $userId,
            'updated_at'       => Date::utcDateTime(),
        ], ['id' => $conversationId]);
    }

    private function setConversationStatus(int $conversationId, string $status, array $identity): void
    {
        if (!in_array($status, $this->conversationStatuses(), true)) {
            return;
        }

        $update = [
            'status'     => $status,
            'updated_at' => Date::utcDateTime(),
            'closed_at'  => $this->finalStatus($status) ? Date::utcDateTime() : null,
        ];

        $this->wpdb->update($this->conversationsTable, $update, ['id' => $conversationId]);
        $conversation = $this->getConversation($conversationId);
        $this->publishEvent(IM::EVENT_CONVERSATION_STATUS_CHANGED, $conversationId, null, $identity, [
            'update'       => $update,
            'conversation' => $conversation ? $this->formatConversation($conversation) : null,
        ]);
    }

    private function publishEvent(string $type, ?int $conversationId, ?int $messageId, array $identity, array $payload): void
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
            'target_type' => 'customer_conversation',
            'target_id'   => $conversationId ? (string) $conversationId : null,
            'actor_type'  => $identity['actor_type'],
            'actor_id'    => $identity['actor_id'],
        ];

        $this->notifications()->publish($this->adminChannel(), $type, $notificationPayload, $meta);
        if ($conversationId) {
            $this->notifications()->publish($this->viewerChannel($conversationId), $type, $notificationPayload, $meta);
        }
    }

    private function formatConversation(array $row): array
    {
        return [
            'id'                   => (int) $row['id'],
            'type'                 => $row['type'],
            'subject'              => $row['subject'],
            'customer_user_id'     => $row['customer_user_id'] !== null ? (int) $row['customer_user_id'] : null,
            'customer_guest_id'    => $row['customer_guest_id'],
            'assignee_user_id'     => $row['assignee_user_id'] !== null ? (int) $row['assignee_user_id'] : null,
            'status'               => $row['status'],
            'priority'             => (int) $row['priority'],
            'source'               => $row['source'],
            'last_message_id'      => $row['last_message_id'] !== null ? (int) $row['last_message_id'] : null,
            'last_message_excerpt' => $row['last_message_excerpt'],
            'last_message_at'      => $this->formatDateTime($row['last_message_at'] ?? null),
            'last_message_at_utc'  => $row['last_message_at'],
            'unread_customer'      => (int) $row['unread_customer'],
            'unread_agent'         => (int) $row['unread_agent'],
            'meta'                 => $this->decode($row['meta'] ?? ''),
            'created_at'           => $this->formatDateTime($row['created_at'] ?? null),
            'created_at_utc'       => $row['created_at'],
            'updated_at'           => $this->formatDateTime($row['updated_at'] ?? null),
            'updated_at_utc'       => $row['updated_at'],
            'closed_at'            => $this->formatDateTime($row['closed_at'] ?? null),
            'closed_at_utc'        => $row['closed_at'],
        ];
    }

    private function formatMessage(array $row): array
    {
        return [
            'id'              => (int) $row['id'],
            'conversation_id' => (int) $row['conversation_id'],
            'sender_type'     => $row['sender_type'],
            'sender_id'       => $row['sender_id'],
            'sender_user_id'  => $row['sender_user_id'] !== null ? (int) $row['sender_user_id'] : null,
            'sender_name'     => $row['sender_name'],
            'message_type'    => $row['message_type'],
            'content'         => $row['content'],
            'payload'         => $this->decode($row['payload'] ?? ''),
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

    private function z(): bool
    {
        try {
            return $this->container->get('loader')->admin();
        }
        catch (Throwable) {
            return false;
        }
    }

    private function notifications(): NotificationService
    {
        return $this->container->get(NotificationService::class);
    }

    private function adminChannel(): string
    {
        return 'customer.admin';
    }

    private function viewerChannel(int $conversationId): string
    {
        return 'customer.viewer.' . $conversationId;
    }

    private function finalStatus(string $status): bool
    {
        return in_array($status, [IM::STATUS_CLOSED, IM::STATUS_TIMEOUT], true);
    }

    private function conversationStatuses(): array
    {
        return [
            IM::STATUS_PENDING,
            IM::STATUS_BOT_HANDLED,
            IM::STATUS_HANDLED,
            IM::STATUS_ON_HOLD,
            IM::STATUS_CLOSED,
            IM::STATUS_TIMEOUT,
        ];
    }

    private function sanitizeMeta(mixed $meta): array
    {
        if (!is_array($meta)) {
            return [];
        }

        $clean = [];
        foreach (array_slice($meta, 0, 20, true) as $key => $value) {
            if (!is_scalar($value) && $value !== null) {
                continue;
            }
            $clean[sanitize_key((string) $key)] = is_string($value) ? sanitize_text_field($value) : $value;
        }
        return $clean;
    }

    private function sanitizeMessageContent(string $content, bool $trusted): string
    {
        $content = trim($content);
        if ($trusted) {
            return trim(function_exists('wp_kses_post') ? wp_kses_post($content) : strip_tags($content, '<p><br><strong><em><b><i><u><a><ul><ol><li><blockquote><code><pre>'));
        }

        return trim(sanitize_textarea_field($content));
    }

    private function normalizeTime(string $value, string $fallback): string
    {
        $value = trim($value);
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
            return $fallback;
        }

        return $value;
    }

    private function encode(mixed $value): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }

        return wp_json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    private function decode(?string $value): array
    {
        if (!$value) {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function setGuestCookie(string $guestId): void
    {
        if (headers_sent()) {
            return;
        }

        $path   = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
        $domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
        setcookie(self::COOKIE_GUEST_ID, $guestId, time() + YEAR_IN_SECONDS, $path, $domain, is_ssl(), true);
    }

    private function isLoggedIn(): bool
    {
        return function_exists('is_user_logged_in') && is_user_logged_in();
    }

    private function currentUserCan(string $capability): bool
    {
        return function_exists('current_user_can') && current_user_can($capability);
    }

    private function currentUserId(): int
    {
        return function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
    }

    private function currentUser(): object
    {
        if (function_exists('wp_get_current_user')) {
            return wp_get_current_user();
        }

        return (object) [
            'ID'           => 0,
            'user_login'   => '',
            'display_name' => '',
        ];
    }

    private function avatarUrl(int $userId): string
    {
        return function_exists('get_avatar_url') ? (string) get_avatar_url($userId) : '';
    }
}
