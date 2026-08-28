<?php
namespace JEALER\G3\Services;

use JEALER\G3\Core\Customer\CustomerConversation;
use JEALER\G3\Core\IM\IM;
use JEALER\G3\Core\Service\Service;
use JEALER\G3\Utilities\Date;
use Throwable;
use WP_Error;

class CustomerService extends Service {
    public const OPTION_KEY      = 'g3_option_customer_service';
    public const COOKIE_GUEST_ID = 'g3_cs_guest_id';
    public const CACHE_GROUP     = 'g3_customer_service';

    private IMService $im;
    private string    $customerConversationsTable;

    public function __construct()
    {
        parent::__construct();
        $this->im                         = $this->container->get(IMService::class);
        $this->customerConversationsTable = $this->wpdb->prefix . 'g3_customer_conversations';
    }

    public static function defaultOption(): array
    {
        return [
            'enable'           => '0',
            'title'            => 'Online Service',
            'announcement'     => '',
            'announcementLink' => '',
            'welcomeTip'       => 'Hello, how can we help you?',
            'welcomeMessage'   => '',
            'offlineMessage'   => 'Please leave a message. This is outside of working hours. We will reply as soon as possible.',
            'workDays'         => ['1', '2', '3', '4', '5'],
            'workStart'        => '09:00',
            'workEnd'          => '18:00',
            'guestName'        => 'Guest',
            'retentionDays'    => 180,
            'heartbeatSeconds' => 45,
            'timeoutMinutes'   => 120,
            'fallbackMessage'  => 'The service is temporarily unavailable. Please try again later.',
            'icon'             => '1',
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

    public function startConversation(array $data): array|WP_Error
    {
        if (!$this->enabled()) {
            return new WP_Error('customer_service_disabled', 'Customer service is disabled.', ['status' => 403]);
        }

        $identity     = $this->customerIdentity();
        $conversation = $this->findOpenCustomerConversation($identity);
        $created      = false;

        if (!$conversation) {
            $conversationId = $this->im->createConversation($identity, [
                'type'    => IM::TYPE_CUSTOMER_SERVICE,
                'subject' => $data['subject'] ?? '',
                'source'  => $data['source'] ?? 'web',
                'meta'    => $data['meta'] ?? [],
            ]);
            if (is_wp_error($conversationId)) {
                return $conversationId;
            }
            $this->createCustomerConversation($conversationId, $identity);
            $conversation = $this->getCustomerConversation($conversationId);
            $created      = true;
        }

        if (!$conversation) {
            return new WP_Error('conversation_not_found', 'Conversation not found.', ['status' => 404]);
        }

        if ($created) {
            $this->createOfflineMessage((int) $conversation['id']);
        }

        $content = trim((string) ($data['content'] ?? ''));
        if ($content !== '') {
            $message = $this->sendMessage((int) $conversation['id'], $identity, $content, IM::MESSAGE_TEXT, false);
            if (is_wp_error($message)) {
                return $message;
            }
        }

        return [
            'conversation' => $this->getCustomerConversation((int) $conversation['id']),
            'messages'     => $this->im->messages((int) $conversation['id'], 0, 50),
        ];
    }

    public function sendCustomerMessage(int $conversationId, array $data): array|WP_Error
    {
        if (!$this->enabled()) {
            return new WP_Error('customer_service_disabled', 'Customer service is disabled.', ['status' => 403]);
        }

        $identity = $this->customerIdentity();
        if (!$this->canAccessCustomerConversation($conversationId, $identity)) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        $conversation = $this->getCustomerConversation($conversationId);
        if (!$conversation) {
            return new WP_Error('conversation_not_found', 'Conversation not found.', ['status' => 404]);
        }

        if ($this->finalStatus((string) $conversation['status'])) {
            $conversationId = $this->im->createConversation($identity, [
                'type'   => IM::TYPE_CUSTOMER_SERVICE,
                'source' => $conversation['source'] ?? 'web',
                'meta'   => $this->decodeMeta($conversation['meta'] ?? null),
            ]);
            if (is_wp_error($conversationId)) {
                return $conversationId;
            }
            $this->createCustomerConversation($conversationId, $identity);
            $this->createOfflineMessage($conversationId);
        }

        $msgType = $this->requestMessageType($data);
        if (is_wp_error($msgType)) {
            return $msgType;
        }

        return $this->sendMessage($conversationId, $identity, (string) ($data['content'] ?? ''), $msgType, false);
    }

    public function sendAgentMessage(int $conversationId, array $data): array|WP_Error
    {
        if (!$this->canManage()) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        $identity     = $this->agentIdentity();
        $conversation = $this->getCustomerConversation($conversationId);
        if (!$conversation) {
            return new WP_Error('conversation_not_found', 'Conversation not found.', ['status' => 404]);
        }

        if (empty($conversation['assignee_user_id'])) {
            $this->assignConversation($conversationId, $this->currentUserId());
        }

        $msgType = $this->requestMessageType($data);
        if (is_wp_error($msgType)) {
            return $msgType;
        }

        return $this->sendMessage($conversationId, $identity, (string) ($data['content'] ?? ''), $msgType, true);
    }

    public function listConversations(array $args = []): array|WP_Error
    {
        if (!$this->canManage()) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        if (isset($args['status']) && !in_array((string) $args['status'], $this->conversationStatuses(), true)) {
            unset($args['status']);
        }

        return $this->listCustomerConversations($args);
    }

    public function getConversationForViewer(int $conversationId): array|WP_Error
    {
        $identity = $this->canManage() ? null : $this->customerIdentity(false);
        if (!$this->canManage() && (!$identity || !$this->canAccessCustomerConversation($conversationId, $identity))) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        $conversation = $this->getCustomerConversation($conversationId);
        return $conversation ?: new WP_Error('conversation_not_found', 'Conversation not found.', ['status' => 404]);
    }

    public function messagesForViewer(int $conversationId, int $afterId = 0, int $limit = 50): array|WP_Error
    {
        $identity = $this->canManage() ? null : $this->customerIdentity(false);
        if (!$this->canManage() && (!$identity || !$this->canAccessCustomerConversation($conversationId, $identity))) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        return $this->im->messages($conversationId, $afterId, $limit);
    }

    public function updateConversation(int $conversationId, array $data): array|WP_Error
    {
        if (!$this->canManage()) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        if (isset($data['status']) && !in_array((string) $data['status'], $this->conversationStatuses(), true)) {
            return new WP_Error('invalid_status', 'Invalid status.', ['status' => 400]);
        }

        if (array_key_exists('subject', $data)) {
            $result = $this->im->updateConversation($conversationId, ['subject' => $data['subject']], $this->agentIdentity());
            if (is_wp_error($result)) {
                return $result;
            }
        }

        if (isset($data['status'])) {
            return $this->updateCustomerStatus($conversationId, (string) $data['status'], $this->agentIdentity(), $data);
        }

        $conversation = $this->getCustomerConversation($conversationId);
        return $conversation ?: new WP_Error('conversation_not_found', 'Conversation not found.', ['status' => 404]);
    }

    public function createViewerStreamSession(array $data): array|WP_Error
    {
        $afterId        = max(0, (int) ($data['after_id'] ?? 0));
        $heartbeat      = min(60, max(30, (int) ($this->option()['heartbeatSeconds'] ?? 45)));
        $conversationId = max(0, (int) ($data['conversation_id'] ?? 0));
        $identity       = $this->customerIdentity(false);
        if ($conversationId <= 0 || !$identity || !$this->canAccessCustomerConversation($conversationId, $identity)) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        return $this->im->createStreamSession(IM::TYPE_CUSTOMER_SERVICE, 'viewer', $conversationId, $afterId, $heartbeat);
    }

    public function createAdminStreamSession(array $data): array|WP_Error
    {
        if (!$this->canManage()) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        $afterId   = max(0, (int) ($data['after_id'] ?? 0));
        $heartbeat = min(60, max(30, (int) ($this->option()['heartbeatSeconds'] ?? 45)));

        return $this->im->createStreamSession(IM::TYPE_CUSTOMER_SERVICE, 'admin', null, $afterId, $heartbeat);
    }

    public function markRead(int $conversationId, int $messageId = 0): array|WP_Error
    {
        $identity = $this->canManage() ? $this->agentIdentity() : $this->customerIdentity(false);
        if (!$identity || (!$this->canManage() && !$this->im->canAccessConversation($conversationId, $identity))) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        $result  = $this->im->markRead($conversationId, $messageId, $identity);
        $counter = $identity['role'] === IM::ROLE_AGENT ? 'unread_agent' : 'unread_customer';
        $this->wpdb->update($this->customerConversationsTable, [
            $counter     => 0,
            'updated_at' => Date::utcDateTime(),
        ], ['conversation_id' => $conversationId]);

        return $result;
    }

    public function eventsForViewer(int $afterId = 0, ?int $conversationId = null, int $limit = 50): array|WP_Error
    {
        if (!$this->canManage()) {
            if (!$conversationId) {
                return new WP_Error('conversation_required', 'Conversation is required.', ['status' => 400]);
            }
            $identity = $this->customerIdentity(false);
            if (!$identity || !$this->canAccessCustomerConversation($conversationId, $identity)) {
                return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
            }
        }

        return $this->im->events($afterId, $conversationId, $limit);
    }

    public function latestEventId(): int
    {
        try {
            return $this->im->latestEventId(IM::TYPE_CUSTOMER_SERVICE);
        }
        catch (Throwable) {
            return 0;
        }
    }

    public function touchPresence(string $scope, int|string $id): void
    {
        $this->im->touchPresence($scope, $id);
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

        $conversation = $this->getCustomerConversation($conversationId);
        if (!$conversation) {
            return new WP_Error('conversation_not_found', 'Conversation not found.', ['status' => 404]);
        }

        $user = !empty($conversation['customer_user_id']) ? get_userdata((int) $conversation['customer_user_id']) : null;
        return [
            'conversation' => $conversation,
            'user'         => $user ? [
                'id'           => (int) $user->ID,
                'login'        => $user->user_login,
                'display_name' => $user->display_name,
                'email'        => $user->user_email,
                'registered'   => $user->user_registered,
            ] : null,
            'guest'        => empty($conversation['customer_user_id']) ? [
                'id'         => $conversation['customer_guest_id'] ?? null,
                'ip_address' => $conversation['ip_address'] ?? null,
                'user_agent' => $conversation['user_agent'] ?? null,
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
        $ids    = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT cc.`conversation_id`
             FROM {$this->customerConversationsTable} cc
             INNER JOIN {$this->wpdb->prefix}g3_im_conversations c ON c.`id` = cc.`conversation_id`
             WHERE (cc.`updated_at` IS NOT NULL AND cc.`updated_at` < %s)
                OR (cc.`updated_at` IS NULL AND cc.`created_at` < %s)
             LIMIT 1000",
            $cutoff,
            $cutoff
        )) ?: [];

        if ($ids) {
            $ids          = array_map('intval', $ids);
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $this->wpdb->query($this->wpdb->prepare(
                "DELETE FROM {$this->customerConversationsTable} WHERE `conversation_id` IN ({$placeholders})",
                $ids
            ));
        }

        return $this->im->cleanupBeforeDays(IM::TYPE_CUSTOMER_SERVICE, $days);
    }

    public function markTimeoutConversations(int $minutes = 0, int $limit = 200): int
    {
        $minutes = $minutes > 0 ? $minutes : (int) ($this->option()['timeoutMinutes'] ?? 120);
        return $this->markCustomerTimeoutConversations($minutes, $limit);
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

    private function findOpenCustomerConversation(array $identity): ?array
    {
        $statuses     = $this->openStatuses();
        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $where        = $identity['actor_type'] === IM::ACTOR_USER && !empty($identity['user_id'])
            ? 'cc.`customer_user_id` = %d'
            : 'cc.`customer_guest_id` = %s';
        $actor        = $identity['actor_type'] === IM::ACTOR_USER && !empty($identity['user_id'])
            ? (int) $identity['user_id']
            : (string) $identity['actor_id'];

        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT c.*, cc.*
             FROM {$this->wpdb->prefix}g3_im_conversations c
             INNER JOIN {$this->customerConversationsTable} cc ON cc.`conversation_id` = c.`id`
             WHERE c.`type` = %s
               AND c.`state` = %s
               AND cc.`status` IN ({$placeholders})
               AND {$where}
             ORDER BY c.`updated_at` DESC, c.`id` DESC
             LIMIT 1",
            array_merge([IM::TYPE_CUSTOMER_SERVICE, IM::CONVERSATION_OPEN], $statuses, [$actor])
        ), ARRAY_A);

        return is_array($row) ? $this->formatCustomerConversation($row) : null;
    }

    private function createCustomerConversation(int $conversationId, array $identity): void
    {
        $now = Date::utcDateTime();
        $this->wpdb->insert($this->customerConversationsTable, [
            'conversation_id'      => $conversationId,
            'customer_user_id'     => $identity['actor_type'] === IM::ACTOR_USER ? ($identity['user_id'] ?: null) : null,
            'customer_guest_id'    => $identity['actor_type'] === IM::ACTOR_GUEST ? $identity['actor_id'] : null,
            'status'               => CustomerConversation::STATUS_PENDING,
            'wrap_lock_mode'       => CustomerConversation::WRAP_LOCK_NONE,
            'last_customer_msg_at' => null,
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);
    }

    private function listCustomerConversations(array $args): array
    {
        $status = (string) ($args['status'] ?? '');
        $cursor = max(0, (int) ($args['cursor'] ?? 0));
        $limit  = min(100, max(1, (int) ($args['limit'] ?? 30)));
        $search = trim((string) ($args['search'] ?? ''));

        $where  = ['c.`type` = %s'];
        $params = [IM::TYPE_CUSTOMER_SERVICE];

        if ($status !== '') {
            $where[]  = 'cc.`status` = %s';
            $params[] = sanitize_key($status);
        }
        if ($cursor > 0) {
            $where[]  = 'c.`id` < %d';
            $params[] = $cursor;
        }
        if ($search !== '') {
            $like    = '%' . $this->wpdb->esc_like($search) . '%';
            $where[] = '(c.`subject` LIKE %s OR c.`last_msg_preview` LIKE %s OR cc.`customer_guest_id` LIKE %s)';
            array_push($params, $like, $like, $like);
        }

        $params[] = $limit + 1;
        $rows     = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT c.*, cc.*
             FROM {$this->wpdb->prefix}g3_im_conversations c
             INNER JOIN {$this->customerConversationsTable} cc ON cc.`conversation_id` = c.`id`
             WHERE " . implode(' AND ', $where) . "
             ORDER BY COALESCE(c.`last_message_at`, c.`updated_at`, c.`created_at`) DESC, c.`id` DESC
             LIMIT %d",
            $params
        ), ARRAY_A) ?: [];

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $items = array_map(fn(array $row): array => $this->formatCustomerConversation($row), $rows);
        $last  = $items ? $items[array_key_last($items)] : null;

        return [
            'items'       => $items,
            'next_cursor' => $hasMore && $last ? (int) $last['id'] : null,
            'has_more'    => $hasMore,
        ];
    }

    private function getCustomerConversation(int $conversationId): ?array
    {
        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT c.*, cc.*
             FROM {$this->wpdb->prefix}g3_im_conversations c
             INNER JOIN {$this->customerConversationsTable} cc ON cc.`conversation_id` = c.`id`
             WHERE c.`id` = %d",
            $conversationId
        ), ARRAY_A);

        return is_array($row) ? $this->formatCustomerConversation($row) : null;
    }

    private function canAccessCustomerConversation(int $conversationId, array $identity): bool
    {
        if ($identity['actor_type'] === IM::ACTOR_USER) {
            return (bool) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT 1 FROM {$this->customerConversationsTable}
                 WHERE `conversation_id` = %d AND `customer_user_id` = %d
                 LIMIT 1",
                $conversationId,
                (int) ($identity['user_id'] ?? 0)
            ));
        }

        return (bool) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT 1 FROM {$this->customerConversationsTable}
             WHERE `conversation_id` = %d AND `customer_guest_id` = %s
             LIMIT 1",
            $conversationId,
            (string) ($identity['actor_id'] ?? '')
        ));
    }

    private function assignConversation(int $conversationId, int $userId): void
    {
        $this->wpdb->update($this->customerConversationsTable, [
            'assignee_user_id' => $userId > 0 ? $userId : null,
            'updated_at'       => Date::utcDateTime(),
        ], ['conversation_id' => $conversationId]);
    }

    private function updateCustomerStatus(int $conversationId, string $status, array $identity, array $data = []): array|WP_Error
    {
        if (!in_array($status, $this->conversationStatuses(), true)) {
            return new WP_Error('invalid_status', 'Invalid status.', ['status' => 400]);
        }

        $current = $this->getCustomerConversation($conversationId);
        if (!$current) {
            return new WP_Error('conversation_not_found', 'Conversation not found.', ['status' => 404]);
        }

        $now         = Date::utcDateTime();
        $targetState = $status === CustomerConversation::STATUS_CLOSED ? IM::CONVERSATION_CLOSED : IM::CONVERSATION_OPEN;
        $update      = [
            'status'     => $status,
            'updated_at' => $now,
        ];

        if ($status === CustomerConversation::STATUS_CLOSED) {
            $update['closed_at']    = $now;
            $update['close_reason'] = sanitize_key((string) ($data['close_reason'] ?? CustomerConversation::CLOSE_BY_AGENT));
        } else {
            $update['closed_at']    = null;
            $update['close_reason'] = null;
        }

        if (($current['state'] ?? '') !== $targetState) {
            $this->im->updateConversation($conversationId, ['state' => $targetState], $identity);
        }

        if ($status === CustomerConversation::STATUS_WRAP_UP) {
            $update['wrap_lock_mode']  = sanitize_key((string) ($data['wrap_lock_mode'] ?? CustomerConversation::WRAP_LOCK_NONE));
            $update['wrap_lock_until'] = isset($data['wrap_lock_until']) ? sanitize_text_field((string) $data['wrap_lock_until']) : null;
        }

        $result = $this->wpdb->update($this->customerConversationsTable, $update, ['conversation_id' => $conversationId]);
        if ($result === false) {
            return new WP_Error('db_update_error', 'Failed to update customer conversation.', ['status' => 500]);
        }

        $conversation = $this->getCustomerConversation($conversationId);
        if ($conversation) {
            $this->im->publishConversationEvent(IM::EVENT_CONVERSATION_STATUS_CHANGED, $conversationId, $identity, $conversation);
        }

        return $conversation ?: new WP_Error('conversation_not_found', 'Conversation not found.', ['status' => 404]);
    }

    private function markCustomerTimeoutConversations(int $minutes, int $limit): int
    {
        $minutes = max(1, min(14400, $minutes));
        $limit   = min(1000, max(1, $limit));
        $cutoff  = gmdate('Y-m-d H:i:s', time() - ($minutes * MINUTE_IN_SECONDS));

        $ids = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT cc.`conversation_id`
             FROM {$this->customerConversationsTable} cc
             INNER JOIN {$this->wpdb->prefix}g3_im_conversations c ON c.`id` = cc.`conversation_id`
             WHERE cc.`status` IN (%s, %s, %s)
               AND COALESCE(c.`last_message_at`, cc.`updated_at`, cc.`created_at`) < %s
             ORDER BY COALESCE(c.`last_message_at`, cc.`updated_at`, cc.`created_at`) ASC
             LIMIT %d",
            CustomerConversation::STATUS_PENDING,
            CustomerConversation::STATUS_ACTIVE,
            CustomerConversation::STATUS_WRAP_UP,
            $cutoff,
            $limit
        )) ?: [];

        foreach (array_map('intval', $ids) as $id) {
            $this->updateCustomerStatus($id, CustomerConversation::STATUS_CLOSED, $this->systemIdentity(), [
                'close_reason' => CustomerConversation::CLOSE_BY_TIMEOUT,
            ]);
        }

        return count($ids);
    }

    private function afterMessageCommitted(int $conversationId, array $identity, array $message, ?array $conversation): ?array
    {
        $now      = Date::utcDateTime();
        $isAgent  = $identity['role'] === IM::ROLE_AGENT;
        $isSystem = $identity['role'] === IM::ROLE_SYSTEM;
        $update   = [
            'updated_at' => $now,
        ];

        if ($isAgent || $isSystem) {
            $update['last_agent_msg_at'] = $now;
            $update['unread_customer']   = $this->rawSqlIncrement('unread_customer');
            if ($isAgent) {
                $row = $this->customerConversationRow($conversationId);
                if ($row && empty($row['first_response_at'])) {
                    $update['first_response_at'] = $now;
                }
                if ($row && (string) $row['status'] === CustomerConversation::STATUS_PENDING) {
                    $update['status'] = CustomerConversation::STATUS_ACTIVE;
                }
            }
        } else {
            $update['last_customer_msg_at'] = $now;
            $update['unread_agent']         = $this->rawSqlIncrement('unread_agent');
        }

        $this->updateCustomerConversationColumns($conversationId, $update);
        return $this->getCustomerConversation($conversationId) ?: $conversation;
    }

    private function customerConversationRow(int $conversationId): ?array
    {
        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->customerConversationsTable} WHERE `conversation_id` = %d",
            $conversationId
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    private function rawSqlIncrement(string $column): array
    {
        return ['__increment' => $column];
    }

    private function updateCustomerConversationColumns(int $conversationId, array $columns): void
    {
        if (!$columns) {
            return;
        }

        $sets   = [];
        $params = [];
        foreach ($columns as $column => $value) {
            $column = sanitize_key((string) $column);
            if ($column === '') {
                continue;
            }

            if (is_array($value) && isset($value['__increment'])) {
                $sets[] = "`$column` = `$column` + 1";
                continue;
            }

            $sets[]   = "`$column` = %s";
            $params[] = $value;
        }

        if (!$sets) {
            return;
        }

        $params[] = $conversationId;
        $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->customerConversationsTable} SET " . implode(', ', $sets) . " WHERE `conversation_id` = %d",
            $params
        ));
    }

    private function formatCustomerConversation(array $row): array
    {
        $status = (string) ($row['status'] ?? CustomerConversation::STATUS_PENDING);
        return [
            'id'                   => (int) ($row['id'] ?? $row['conversation_id']),
            'conversation_id'      => (int) ($row['conversation_id'] ?? $row['id']),
            'type'                 => (string) ($row['type'] ?? IM::TYPE_CUSTOMER_SERVICE),
            'subject'              => $row['subject'] ?? null,
            'state'                => (string) ($row['state'] ?? IM::CONVERSATION_OPEN),
            'status'               => $status,
            'customer_user_id'     => isset($row['customer_user_id']) && $row['customer_user_id'] !== null ? (int) $row['customer_user_id'] : null,
            'customer_guest_id'    => $row['customer_guest_id'] ?? null,
            'assignee_user_id'     => isset($row['assignee_user_id']) && $row['assignee_user_id'] !== null ? (int) $row['assignee_user_id'] : null,
            'close_reason'         => $row['close_reason'] ?? null,
            'wrap_lock_mode'       => $row['wrap_lock_mode'] ?? CustomerConversation::WRAP_LOCK_NONE,
            'wrap_lock_until'      => $row['wrap_lock_until'] ?? null,
            'first_response_at'    => $row['first_response_at'] ?? null,
            'last_customer_msg_at' => $row['last_customer_msg_at'] ?? null,
            'last_agent_msg_at'    => $row['last_agent_msg_at'] ?? null,
            'priority'             => (int) ($row['priority'] ?? 0),
            'source'               => (string) ($row['source'] ?? 'web'),
            'ip_address'           => $row['ip_address'] ?? null,
            'user_agent'           => $row['user_agent'] ?? null,
            'last_message_id'      => isset($row['last_message_id']) && $row['last_message_id'] !== null ? (int) $row['last_message_id'] : null,
            'last_msg_seq'         => (int) ($row['last_msg_seq'] ?? 0),
            'last_msg_type'        => $row['last_msg_type'] ?? null,
            'last_msg_preview'     => $row['last_msg_preview'] ?? null,
            'last_message_at'      => $row['last_message_at'] ?? null,
            'last_message_at_utc'  => $row['last_message_at'] ?? null,
            'unread_customer'      => (int) ($row['unread_customer'] ?? 0),
            'unread_agent'         => (int) ($row['unread_agent'] ?? 0),
            'meta'                 => $this->decodeMeta($row['meta'] ?? null),
            'created_at'           => $row['created_at'] ?? null,
            'created_at_utc'       => $row['created_at'] ?? null,
            'updated_at'           => $row['updated_at'] ?? null,
            'updated_at_utc'       => $row['updated_at'] ?? null,
            'closed_at'            => $row['closed_at'] ?? null,
            'closed_at_utc'        => $row['closed_at'] ?? null,
        ];
    }

    private function sendMessage(int $conversationId, array $identity, string $content, string $messageType, bool $trusted): array|WP_Error
    {
        $bodyText = $this->sanitizeMessageContent($content, $trusted);
        if ($bodyText === '') {
            return new WP_Error('empty_message', __('Message content cannot be empty.', 'G3'), ['status' => 400]);
        }

        $body = [
            'version' => 1,
            'text'    => $bodyText,
            'format'  => $trusted ? 'html' : 'plain',
        ];

        return $this->im->sendMessage($conversationId, $identity, $messageType, $body, [
            'preview'      => mb_substr(wp_strip_all_tags($bodyText), 0, 255),
            'after_commit' => fn(array $message, ?array $conversation): ?array => $this->afterMessageCommitted($conversationId, $identity, $message, $conversation),
        ]);
    }

    private function requestMessageType(array $data): string|WP_Error
    {
        $msgType = sanitize_key((string) ($data['msg_type'] ?? IM::MESSAGE_TEXT));
        if ($msgType === '') {
            $msgType = IM::MESSAGE_TEXT;
        }

        if ($msgType !== IM::MESSAGE_TEXT) {
            return new WP_Error('invalid_msg_type', 'Unsupported message type.', ['status' => 400]);
        }

        return $msgType;
    }

    private function createOfflineMessage(int $conversationId): void
    {
        if (!$this->z() || $this->withinWorkingHours()) {
            return;
        }

        $offline = trim((string) ($this->option()['offlineMessage'] ?? ''));
        if ($offline === '') {
            return;
        }

        $this->sendMessage($conversationId, $this->systemIdentity(), $offline, IM::MESSAGE_OFFLINE, true);
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

    private function sanitizeMessageContent(string $content, bool $trusted): string
    {
        $content = trim($content);
        if ($trusted) {
            return trim(function_exists('wp_kses_post') ? wp_kses_post($content) : strip_tags($content, '<p><br><strong><em><b><i><u><a><ul><ol><li><blockquote><code><pre>'));
        }

        return trim(sanitize_textarea_field($content));
    }

    private function decodeMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (!is_string($meta) || $meta === '') {
            return [];
        }

        $decoded = json_decode($meta, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function openStatuses(): array
    {
        return [
            CustomerConversation::STATUS_PENDING,
            CustomerConversation::STATUS_ACTIVE,
            CustomerConversation::STATUS_WRAP_UP,
        ];
    }

    private function conversationStatuses(): array
    {
        return [
            CustomerConversation::STATUS_PENDING,
            CustomerConversation::STATUS_ACTIVE,
            CustomerConversation::STATUS_WRAP_UP,
            CustomerConversation::STATUS_CLOSED,
        ];
    }

    private function finalStatus(string $status): bool
    {
        return $status === CustomerConversation::STATUS_CLOSED;
    }

    private function normalizeTime(string $value, string $fallback): string
    {
        $value = trim($value);
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
            return $fallback;
        }

        return $value;
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
