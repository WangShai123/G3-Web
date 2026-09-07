<?php
namespace JEALER\G3\Services;
use JEALER\G3\Core\Customer\CustomerConversation;
use JEALER\G3\Core\IM\IM;
use JEALER\G3\Core\Service\Service;
use JEALER\G3\Utilities\Date;
use JEALER\G3\Utilities\Type;
use Redis;
use Throwable;
use WP_Error;

class CustomerService extends Service {
    public const OPTION_KEY                       = 'g3_option_customer_service';
    public const COOKIE_GUEST_ID                  = 'g3_cs_guest_id';
    public const CACHE_GROUP                      = 'g3_customer_service';
    public const CUSTOMER_CONVERSATIONS_CACHE_KEY = 'g3_im:customer:conversations';
    private const CUSTOMER_CONVERSATION_INDEX_KEY  = 'g3_im:customer:conversations:index';
    private const CUSTOMER_CONVERSATION_SYNC_KEY   = 'g3_im:sync:customer:conversations';

    private IMService $im;
    private string    $customerConversationsTable;
    private ?Redis    $redisClient                = null;

    protected function onInit(): void
    {
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
            'needAuth'         => '0',
            'retentionDays'    => 180,
            'heartbeatSeconds' => 45,
            'timeoutMinutes'   => 30,
            'fallbackMessage'  => 'The service is temporarily unavailable. Please try again later.',
            'throttle'         => 5,
            'cacheStorage'     => '0',
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

    /**
     * Build the frontend customer service runtime configuration.
     */
    public function publicConfig(): array
    {
        $option  = $this->option();
        $z       = $this->z();
        $working = $this->withinWorkingHours();
        return [
            'enabled'          => $this->enabled(),
            'title'            => (string) $option['title'],
            'welcomeTip'       => $z ? (string) $option['welcomeTip'] : '',
            'welcomeMessage'   => $z ? (string) $option['welcomeMessage'] : '',
            'announcement'     => (string) $option['announcement'],
            'announcementLink' => (string) $option['announcementLink'],
            'offlineMessage'   => $z ? (string) $option['offlineMessage'] : '',
            'fallbackMessage'  => $z ? (string) $option['fallbackMessage'] : '',
            'working'          => $working,
            'offline'          => !$working,
            'guestId'          => $this->guestId(false),
            'z'                => $z,
            'heartbeatSeconds' => (int) $option['heartbeatSeconds'],
            'timeoutMinutes'   => (int) $option['timeoutMinutes'],
        ];
    }

    /**
     * Start or reuse an open customer conversation for the current visitor.
     * 
     * 开启或重用当前访客的客服会话
     * 
     * @param array $data The conversation data.
     * @return array|WP_Error The conversation data or WP_Error.
     */
    public function startConversation(array $data): array|WP_Error
    {
        if (!$this->enabled()) {
            return new WP_Error('customer_service_disabled', 'Customer service is disabled.', ['status' => 403]);
        }

        $identity              = $this->customerIdentity();
        $forceNew              = ($data['force_new'] ?? false) === true;
        $excludeConversationId = max(0, (int) ($data['exclude_conversation_id'] ?? 0));
        $conversation          = $forceNew ? null : $this->findOpenCustomerConversation($identity, $excludeConversationId);
        $created               = false;

        if ($excludeConversationId > 0) {
            $this->closeExpiredCustomerConversation($excludeConversationId, $identity);
        }

        if ($conversation && $this->customerConversationExpired($conversation)) {
            $this->closeExpiredCustomerConversation((int) $conversation['id'], $identity);
            $conversation = null;
        }

        if (!$conversation) {
            $conversation = $this->createCurrentCustomerConversation($identity, $data);
            if (is_wp_error($conversation)) {
                return $conversation;
            }
            $created = true;
        }

        if (!$conversation) {
            return new WP_Error('conversation_not_found', 'Conversation not found.', ['status' => 404]);
        }

        $content = trim((string) ($data['content'] ?? ''));
        if ($content !== '') {
            $message = $this->sendMessage((int) $conversation['id'], $identity, $content, IM::MESSAGE_TEXT, false);
            if (is_wp_error($message)) {
                return $message;
            }
        }

        return [
            'conversation' => $conversation,
            'messages'     => $created && $forceNew ? [] : $this->im->messages((int) $conversation['id'], 0, 50),
            'created'      => $created,
        ];
    }

    /**
     * Send a visitor message to a customer service conversation.
     * 
     * 发送访客消息到客服会话
     * 
     * @param int $conversationId The conversation ID.
     * @param array $data The message data.
     * @return array|WP_Error The message data or WP_Error.
     */
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
            if (!$this->createCustomerConversation($conversationId, $identity)) {
                return new WP_Error('customer_conversation_create_failed', 'Failed to create customer conversation.', ['status' => 500]);
            }
        }

        $msgType = $this->requestMessageType($data);
        if (is_wp_error($msgType)) {
            return $msgType;
        }

        return $this->sendMessage($conversationId, $identity, (string) ($data['content'] ?? ''), $msgType, false);
    }

    /**
     * Close a visitor-owned customer service conversation.
     * 
     * 关闭访客拥有的客服会话
     * 
     * @param int $conversationId The conversation ID.
     * @param string $closeReason The close reason.
     * @return array|WP_Error The conversation data or WP_Error.
     */
    public function closeCustomerConversation(int $conversationId, string $closeReason = CustomerConversation::CLOSE_BY_CUSTOMER): array|WP_Error
    {
        if (!$this->enabled()) {
            return new WP_Error('customer_service_disabled', 'Customer service is disabled.', ['status' => 403]);
        }

        $identity = $this->customerIdentity(false);
        if ($conversationId <= 0 || !$identity || !$this->canAccessCustomerConversation($conversationId, $identity)) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        $conversation = $this->getCustomerConversation($conversationId);
        if (!$conversation) {
            return new WP_Error('conversation_not_found', 'Conversation not found.', ['status' => 404]);
        }

        if ($this->finalStatus((string) $conversation['status'])) {
            return $conversation;
        }

        $allowedReasons = [
            CustomerConversation::CLOSE_BY_CUSTOMER,
            CustomerConversation::CLOSE_BY_TIMEOUT,
        ];
        $closeReason    = in_array($closeReason, $allowedReasons, true) ? $closeReason : CustomerConversation::CLOSE_BY_CUSTOMER;

        return $this->updateCustomerStatus($conversationId, CustomerConversation::STATUS_CLOSED, $identity, [
            'close_reason' => $closeReason,
        ]);
    }

    /**
     * Send an agent reply and assign the conversation when needed.
     * 
     * 发送客服回复并分配会话
     * 
     * @param int $conversationId The conversation ID.
     * @param array $data The message data.
     * @return array|WP_Error The message data or WP_Error.
     */
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
        if ($this->finalStatus((string) ($conversation['status'] ?? ''))) {
            return new WP_Error('conversation_closed', 'Conversation is closed.', ['status' => 409]);
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

    /**
     * List customer service conversations for the admin panel.
     * 
     * 列出客服会话
     * 
     * @param array $args The query arguments.
     * @return array|WP_Error The conversation data or WP_Error.
     */
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

    /**
     * Read a conversation after validating the current viewer can access it.
     * 
     * 获取客服会话
     * 
     * @param int $conversationId The conversation ID.
     * @return array|WP_Error The conversation data or WP_Error.
     */
    public function getConversationForViewer(int $conversationId): array|WP_Error
    {
        $identity = $this->canManage() ? null : $this->customerIdentity(false);
        if (!$this->canManage() && (!$identity || !$this->canAccessCustomerConversation($conversationId, $identity))) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        $conversation = $this->getCustomerConversation($conversationId);
        return $conversation ?: new WP_Error('conversation_not_found', 'Conversation not found.', ['status' => 404]);
    }

    /**
     * Read conversation messages after validating viewer access.
     * 
     * 获取客服会话消息
     * 
     * @param int $conversationId The conversation ID.
     * @param int $afterId The message ID to start from.
     * @param int $limit The number of messages to return.
     * @return array|WP_Error The message data or WP_Error.
     */
    public function messagesForViewer(int $conversationId, int $afterId = 0, int $limit = 50): array|WP_Error
    {
        $identity = $this->canManage() ? null : $this->customerIdentity(false);
        if (!$this->canManage() && (!$identity || !$this->canAccessCustomerConversation($conversationId, $identity))) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        return $this->im->messages($conversationId, $afterId, $limit);
    }

    /**
     * Update admin-managed conversation fields such as subject or status.
     * 
     * 更新客服会话字段
     * 
     * @param int $conversationId The conversation ID.
     * @param array $data The conversation data.
     * @return array|WP_Error The conversation data or WP_Error.
     */
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

    /**
     * Create an SSE stream session for the current customer viewer.
     * 
     * 创建客服会话流会话
     * 
     * @param array $data The conversation data.
     * @return array|WP_Error The conversation data or WP_Error.
     */
    public function createViewerStreamSession(array $data): array|WP_Error
    {
        $afterId        = max(0, (int) ($data['after_id'] ?? 0));
        $heartbeat      = min(60, max(30, (int) ($this->option()['heartbeatSeconds'] ?? 45)));
        $conversationId = max(0, (int) ($data['conversation_id'] ?? 0));
        $identity       = $this->customerIdentity(false);
        $clientId       = $this->streamClientId($data, 'customer-widget', ['customer-widget']);
        if ($conversationId <= 0 || !$identity || !$this->canAccessCustomerConversation($conversationId, $identity)) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        $owner = implode(':', [
            'im',
            IM::TYPE_CUSTOMER_SERVICE,
            'viewer',
            (string) ($identity['actor_type'] ?? IM::ACTOR_GUEST),
            (string) ($identity['actor_id'] ?? ''),
            'conversation',
            (string) $conversationId,
            'client',
            $clientId,
        ]);

        return $this->im->createStreamSession(IM::TYPE_CUSTOMER_SERVICE, 'viewer', $conversationId, $afterId, $heartbeat, $owner);
    }

    /**
     * Create an SSE stream session for the customer service admin panel.
     * 
     * 创建客服会话流会话
     * 
     * @param array $data The conversation data.
     * @return array|WP_Error The conversation data or WP_Error.
     */
    public function createAdminStreamSession(array $data): array|WP_Error
    {
        if (!$this->canManage()) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        $afterId   = max(0, (int) ($data['after_id'] ?? 0));
        $heartbeat = min(60, max(30, (int) ($this->option()['heartbeatSeconds'] ?? 45)));
        $clientId  = $this->streamClientId($data, 'customer-admin', ['customer-admin', 'customer-service']);

        $owner = implode(':', [
            'im',
            IM::TYPE_CUSTOMER_SERVICE,
            'admin',
            'user',
            (string) $this->currentUserId(),
            'client',
            $clientId,
        ]);

        return $this->im->createStreamSession(IM::TYPE_CUSTOMER_SERVICE, 'admin', null, $afterId, $heartbeat, $owner);
    }

    /**
     * Mark a conversation as read for the current customer or agent identity.
     * 
     * 标记客服会话为已读
     * 
     * @param int $conversationId The conversation ID.
     * @param int $messageId The message ID to mark as read.
     * @return array|WP_Error The conversation data or WP_Error.
     */
    public function markRead(int $conversationId, int $messageId = 0): array|WP_Error
    {
        $identity = $this->canManage() ? $this->agentIdentity() : $this->customerIdentity(false);
        if (!$identity || (!$this->canManage() && !$this->im->canAccessConversation($conversationId, $identity))) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        $result  = $this->im->markRead($conversationId, $messageId, $identity);
        $counter = $identity['role'] === IM::ROLE_AGENT ? 'unread_agent' : 'unread_customer';
        if ($this->cacheStorageEnabled()) {
            $this->updateCachedCustomerConversationColumns($conversationId, [
                $counter     => 0,
                'updated_at' => Date::utcDateTime(),
            ]);
            return $result;
        }

        $this->wpdb->update($this->customerConversationsTable, [
            $counter     => 0,
            'updated_at' => Date::utcDateTime(),
        ], ['conversation_id' => $conversationId]);

        return $result;
    }

    /**
     * Read IM events after validating viewer access.
     * 
     * 获取客服会话事件
     * 
     * @param int $afterId The event ID to start from.
     * @param int $conversationId The conversation ID to filter by.
     * @param int $limit The number of events to return.
     * @return array|WP_Error The event data or WP_Error.
     */
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

    /**
     * Get the latest customer service IM event id.
     * 
     * 获取客服客服会话事件事件ID
     * 
     * @return int The latest event ID or.
     */
    public function latestEventId(): int
    {
        try {
            return $this->im->latestEventId(IM::TYPE_CUSTOMER_SERVICE);
        }
        catch (Throwable) {
            return 0;
        }
    }

    /**
     * Refresh customer service presence for a scope and identifier.
     * 
     * 刷新客服会话存在状态
     * 
     * @param string $scope The scope of the presence.
     * @param int|string $id The identifier of the presence.
     */
    public function touchPresence(string $scope, int|string $id): void
    {
        $this->im->touchPresence($scope, $id);
    }

    private function streamClientId(array $data, string $fallback, array $allowed): string
    {
        $clientId = sanitize_key((string) ($data['client_id'] ?? $fallback));
        if ($clientId === '' || !in_array($clientId, $allowed, true)) {
            return $fallback;
        }

        return substr($clientId, 0, 64);
    }

    public function canManage(): bool
    {
        return $this->isLoggedIn() && $this->currentUserCan('manage_options');
    }

    /**
     * Return customer profile data for an admin-visible conversation.
     * 
     * 获取客服会话客户资料
     * 
     * @param int $conversationId The conversation ID.
     * @return array|WP_Error The customer profile data or WP_Error.
     */
    public function customerProfile(int $conversationId): array|WP_Error
    {
        if (!$this->canManage()) {
            return new WP_Error('forbidden', 'Forbidden', ['status' => 403]);
        }

        $conversation = $this->getCustomerConversation($conversationId);
        if (!$conversation) {
            return new WP_Error('conversation_not_found', 'Conversation not found.', ['status' => 404]);
        }

        return [
            'conversation' => $conversation,
            'user'         => !empty($conversation['customer_user_id']) ? $this->customerProfileUser($conversation) : null,
            'guest'        => empty($conversation['customer_user_id']) ? [
                'id' => $conversation['customer_guest_id'] ?? null,
                'ip' => $conversation['ip_address'] ?? null,
                'ua' => $conversation['user_agent'] ?? null,
            ] : null,
        ];
    }

    private function customerProfileUser(array $conversation): ?array
    {
        $userId = (int) ($conversation['customer_user_id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        /** @var UserService $userService */
        $userService = $this->container->get(UserService::class);
        $profile     = $userService->init($userId)?->cache();
        if (!is_array($profile)) {
            return null;
        }

        $session = $this->latestUserSession($userId);

        return [
            'user_id'       => (int) ($profile['user_id'] ?? $userId),
            'login'         => (string) ($profile['login'] ?? ''),
            'nickname'      => (string) ($profile['nickname'] ?? ''),
            'email'         => (string) ($profile['email'] ?? ''),
            'registered_at' => (string) ($profile['registered_at'] ?? ''),
            'ip'            => $session['ip'],
            'ua'            => $session['ua'],
        ];
    }

    private function latestUserSession(int $userId): array
    {
        $result = ['ip' => null, 'ua' => null];
        if ($userId <= 0) {
            return $result;
        }

        $sessions = get_user_meta($userId, 'session_tokens', true);
        if (!is_array($sessions) || !$sessions) {
            return $result;
        }

        $now    = time();
        $latest = null;
        foreach ($sessions as $session) {
            if (!is_array($session)) {
                continue;
            }

            $expiresAt = (int) ($session['expiration'] ?? 0);
            if ($expiresAt > 0 && $expiresAt <= $now) {
                continue;
            }

            $sort = (int) ($session['login'] ?? $expiresAt);
            if (!$latest || $sort > (int) ($latest['_sort'] ?? 0)) {
                $session['_sort'] = $sort;
                $latest           = $session;
            }
        }

        if (!$latest) {
            return $result;
        }

        return [
            'ip' => isset($latest['ip']) ? (string) $latest['ip'] : null,
            'ua' => isset($latest['ua']) ? (string) $latest['ua'] : null,
        ];
    }

    /**
     * Determine whether the current site-local time is inside configured work hours.
     */
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

    /**
     * Remove customer conversation records older than the retention window.
     */
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

    /**
     * Close pending or handling conversations that exceeded the inactive timeout.
     */
    public function markTimeoutConversations(int $minutes = 0, int $limit = 200): int
    {
        $minutes = $minutes > 0 ? $minutes : (int) ($this->option()['timeoutMinutes'] ?? 120);
        return $this->markCustomerTimeoutConversations($minutes, $limit);
    }

    private function createCurrentCustomerConversation(array $identity, array $data): array|WP_Error
    {
        $conversationId = $this->im->createConversation($identity, [
            'type'    => IM::TYPE_CUSTOMER_SERVICE,
            'subject' => $data['subject'] ?? '',
            'source'  => $data['source'] ?? 'web',
            'meta'    => $data['meta'] ?? [],
        ]);
        if (is_wp_error($conversationId)) {
            return $conversationId;
        }
        $customerConversation = $this->createCustomerConversation($conversationId, $identity);
        if (!$customerConversation) {
            return new WP_Error('customer_conversation_create_failed', 'Failed to create customer conversation.', ['status' => 500]);
        }

        $conversation = $this->im->getConversationRow($conversationId);
        if (!$conversation) {
            return new WP_Error('im_conversation_read_failed', 'Failed to read created IM conversation.', ['status' => 500]);
        }

        return $this->formatCustomerConversation(array_merge($conversation, $customerConversation));
    }

    private function closeExpiredCustomerConversation(int $conversationId, array $identity): bool
    {
        if ($conversationId <= 0 || !$this->canAccessCustomerConversation($conversationId, $identity)) {
            return false;
        }

        $conversation = $this->getCustomerConversation($conversationId);
        if (!$conversation || $this->finalStatus((string) ($conversation['status'] ?? ''))) {
            return true;
        }

        if (!$this->customerConversationExpired($conversation)) {
            return false;
        }

        $result = $this->updateCustomerStatus($conversationId, CustomerConversation::STATUS_CLOSED, $identity, [
            'close_reason' => CustomerConversation::CLOSE_BY_TIMEOUT,
        ]);

        return !is_wp_error($result);
    }

    private function customerConversationExpired(array $conversation): bool
    {
        $minutes  = max(1, min(14400, (int) ($this->option()['timeoutMinutes'] ?? CustomerService::defaultOption()['timeoutMinutes'])));
        $cutoff   = gmdate('Y-m-d H:i:s', time() - ($minutes * MINUTE_IN_SECONDS));
        $activeAt = (string) (
            $conversation['last_message_at']
            ?? $conversation['created_at']
            ?? ''
        );

        return $activeAt !== '' && $activeAt < $cutoff;
    }

    /**
     * Resolve or create the guest identifier cookie used for visitor conversations.
     */
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

    private function findOpenCustomerConversation(array $identity, int $excludeConversationId = 0): ?array
    {
        $statuses     = $this->openStatuses();
        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        $where        = $identity['actor_type'] === IM::ACTOR_USER && !empty($identity['user_id'])
            ? 'cc.`customer_user_id` = %d'
            : 'cc.`customer_guest_id` = %s';
        $actor        = $identity['actor_type'] === IM::ACTOR_USER && !empty($identity['user_id'])
            ? (int) $identity['user_id']
            : (string) $identity['actor_id'];
        $excludeSql   = $excludeConversationId > 0 ? ' AND c.`id` <> %d' : '';
        $params       = array_merge([IM::TYPE_CUSTOMER_SERVICE, IM::CONVERSATION_OPEN], $statuses, [$actor]);
        if ($excludeConversationId > 0) {
            $params[] = $excludeConversationId;
        }

        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT c.*, cc.*
             FROM {$this->wpdb->prefix}g3_im_conversations c
             INNER JOIN {$this->customerConversationsTable} cc ON cc.`conversation_id` = c.`id`
             WHERE c.`type` = %s
               AND c.`state` = %s
               AND cc.`status` IN ({$placeholders})
               AND {$where}
               {$excludeSql}
             ORDER BY c.`updated_at` DESC, c.`id` DESC
             LIMIT 1",
            $params
        ), ARRAY_A);

        $rows = is_array($row) ? [$row] : [];
        if ($this->cacheStorageEnabled()) {
            foreach ($this->cachedCustomerConversationRows() as $cached) {
                if (!in_array((string) ($cached['status'] ?? ''), $statuses, true)) {
                    continue;
                }
                if ($identity['actor_type'] === IM::ACTOR_USER && !empty($identity['user_id'])) {
                    if ((int) ($cached['customer_user_id'] ?? 0) !== (int) $identity['user_id']) {
                        continue;
                    }
                } elseif ((string) ($cached['customer_guest_id'] ?? '') !== (string) $identity['actor_id']) {
                    continue;
                }
                if ($excludeConversationId > 0 && (int) ($cached['conversation_id'] ?? 0) === $excludeConversationId) {
                    continue;
                }

                $conversation = $this->im->getConversationRow((int) $cached['conversation_id']);
                if (!$conversation || (string) ($conversation['type'] ?? '') !== IM::TYPE_CUSTOMER_SERVICE || (string) ($conversation['state'] ?? '') !== IM::CONVERSATION_OPEN) {
                    continue;
                }
                $rows[] = array_merge($conversation, $cached);
            }
        }

        if (!$rows) {
            return null;
        }

        usort($rows, fn(array $a, array $b): int => strcmp(
            (string) ($b['updated_at'] ?? $b['created_at'] ?? ''),
            (string) ($a['updated_at'] ?? $a['created_at'] ?? '')
        ) ?: ((int) ($b['conversation_id'] ?? $b['id'] ?? 0) <=> (int) ($a['conversation_id'] ?? $a['id'] ?? 0)));

        return $this->formatCustomerConversation($rows[0]);
    }

    private function createCustomerConversation(int $conversationId, array $identity): array|false
    {
        $now = Date::utcDateTime();
        $row = [
            'conversation_id'      => $conversationId,
            'customer_user_id'     => $identity['actor_type'] === IM::ACTOR_USER ? ($identity['user_id'] ?: null) : null,
            'customer_guest_id'    => $identity['actor_type'] === IM::ACTOR_GUEST ? $identity['actor_id'] : null,
            'assignee_user_id'     => null,
            'status'               => CustomerConversation::STATUS_PENDING,
            'close_reason'         => null,
            'first_response_at'    => null,
            'last_customer_msg_at' => null,
            'last_agent_msg_at'    => null,
            'unread_customer'      => 0,
            'unread_agent'         => 0,
            'meta'                 => null,
            'created_at'           => $now,
            'updated_at'           => $now,
            'closed_at'            => null,
        ];

        if ($this->cacheStorageEnabled()) {
            $redis = $this->redis();
            if ($redis) {
                return $this->storeCachedCustomerConversationRow($redis, $row) ? $row : false;
            }
            return false;
        }

        $stored = $this->wpdb->insert($this->customerConversationsTable, [
            'conversation_id'      => $row['conversation_id'],
            'customer_user_id'     => $row['customer_user_id'],
            'customer_guest_id'    => $row['customer_guest_id'],
            'status'               => $row['status'],
            'last_customer_msg_at' => $row['last_customer_msg_at'],
            'created_at'           => $row['created_at'],
            'updated_at'           => $row['updated_at'],
        ]) !== false;

        return $stored ? $row : false;
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
             ORDER BY c.`created_at` DESC, c.`id` DESC
             LIMIT %d",
            $params
        ), ARRAY_A) ?: [];

        if ($this->cacheStorageEnabled()) {
            $rows = $this->mergeConversationRows($rows, $this->filterCachedCustomerConversations([
                'status' => $status,
                'cursor' => $cursor,
                'search' => $search,
            ]));
            usort($rows, fn(array $a, array $b): int => strcmp(
                (string) ($b['created_at'] ?? ''),
                (string) ($a['created_at'] ?? '')
            ) ?: ((int) ($b['conversation_id'] ?? $b['id'] ?? 0) <=> (int) ($a['conversation_id'] ?? $a['id'] ?? 0)));
            $rows = array_slice($rows, 0, $limit + 1);
        }

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
        if ($this->cacheStorageEnabled()) {
            $customer     = $this->cachedCustomerConversationRow($conversationId);
            $conversation = $this->im->getConversationRow($conversationId);
            if ($customer && $conversation) {
                return $this->formatCustomerConversation(array_merge($conversation, $customer));
            }
        }

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
        if ($this->cacheStorageEnabled()) {
            $row = $this->cachedCustomerConversationRow($conversationId);
            if ($row) {
                if ($identity['actor_type'] === IM::ACTOR_USER) {
                    return (int) ($row['customer_user_id'] ?? 0) === (int) ($identity['user_id'] ?? 0);
                }

                return (string) ($row['customer_guest_id'] ?? '') === (string) ($identity['actor_id'] ?? '');
            }
        }

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
        if ($this->cacheStorageEnabled()) {
            $this->updateCachedCustomerConversationColumns($conversationId, [
                'assignee_user_id' => $userId > 0 ? $userId : null,
                'updated_at'       => Date::utcDateTime(),
            ]);
            return;
        }

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

        if ($this->cacheStorageEnabled()) {
            $conversation = $this->updateCachedCustomerConversationColumns($conversationId, $update);
            if ($conversation) {
                $this->im->publishConversationEvent(IM::EVENT_CONVERSATION_STATUS_CHANGED, $conversationId, $identity, $conversation);
                return $conversation;
            }

            return new WP_Error('conversation_not_found', 'Conversation not found.', ['status' => 404]);
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
             WHERE cc.`status` IN (%s, %s)
               AND COALESCE(c.`last_message_at`, c.`created_at`, cc.`created_at`) < %s
             ORDER BY COALESCE(c.`last_message_at`, c.`created_at`, cc.`created_at`) ASC
             LIMIT %d",
            CustomerConversation::STATUS_PENDING,
            CustomerConversation::STATUS_HANDLING,
            $cutoff,
            $limit
        )) ?: [];

        if ($this->cacheStorageEnabled()) {
            foreach ($this->cachedCustomerConversationRows() as $row) {
                if (
                    !in_array((string) ($row['status'] ?? ''), [
                        CustomerConversation::STATUS_PENDING,
                        CustomerConversation::STATUS_HANDLING,
                    ], true)
                ) {
                    continue;
                }

                $conversation = $this->im->getConversationRow((int) $row['conversation_id']);
                $last         = $conversation
                    ? (string) ($conversation['last_message_at'] ?? $conversation['created_at'] ?? $row['created_at'] ?? '')
                    : (string) ($row['created_at'] ?? '');
                if ($last !== '' && $last < $cutoff) {
                    $ids[] = (int) $row['conversation_id'];
                }
            }
            $ids = array_slice(array_values(array_unique(array_map('intval', $ids))), 0, $limit);
        }

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
                    $update['status'] = CustomerConversation::STATUS_HANDLING;
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
        if ($this->cacheStorageEnabled()) {
            $row = $this->cachedCustomerConversationRow($conversationId);
            if ($row) {
                return $row;
            }
        }

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

        if ($this->cacheStorageEnabled()) {
            $this->updateCachedCustomerConversationColumns($conversationId, $columns);
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

    /**
     * Persist cached customer conversation rows into the database.
     */
    public function syncCachedCustomerConversations(int $limit = 200): array
    {
        $redis = $this->redis();
        if (!$redis) {
            return ['synced' => 0, 'skipped' => 0, 'failed' => 0, 'error' => 'redis_unavailable'];
        }

        $limit  = min(1000, max(1, $limit));
        $ids    = $redis->zRangeByScore(self::CUSTOMER_CONVERSATION_SYNC_KEY, '-inf', '+inf', ['limit' => [0, $limit]]) ?: [];
        $rows   = $this->cachedRowsByIds($redis, self::CUSTOMER_CONVERSATIONS_CACHE_KEY, $ids);
        $byId   = [];
        $result = ['synced' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            $byId[(string) ((int) ($row['conversation_id'] ?? 0))] = $row;
        }

        foreach ($ids as $id) {
            $conversationId = (int) $id;
            $row            = $byId[(string) $conversationId] ?? null;
            if (!$row) {
                $redis->zRem(self::CUSTOMER_CONVERSATION_SYNC_KEY, (string) $conversationId);
                $result['skipped']++;
                continue;
            }

            if ($this->upsertCachedCustomerConversationRow($row)) {
                $redis->zRem(self::CUSTOMER_CONVERSATION_SYNC_KEY, (string) $conversationId);
                $result['synced']++;
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    private function storeCachedCustomerConversationRow(Redis $redis, array $row): bool
    {
        $conversationId = (int) ($row['conversation_id'] ?? 0);
        if ($conversationId <= 0) {
            return false;
        }

        $encoded = Type::arrayToJson($row);
        if (!is_string($encoded) || $encoded === '') {
            return false;
        }

        $stored = $redis->hSet(self::CUSTOMER_CONVERSATIONS_CACHE_KEY, (string) $conversationId, $encoded) !== false;
        $redis->zAdd(self::CUSTOMER_CONVERSATION_INDEX_KEY, $conversationId, (string) $conversationId);
        $redis->zAdd(self::CUSTOMER_CONVERSATION_SYNC_KEY, $conversationId, (string) $conversationId);
        $this->touchCacheKeys($redis, [
            self::CUSTOMER_CONVERSATIONS_CACHE_KEY,
            self::CUSTOMER_CONVERSATION_INDEX_KEY,
            self::CUSTOMER_CONVERSATION_SYNC_KEY,
        ]);

        return $stored;
    }

    private function updateCachedCustomerConversationColumns(int $conversationId, array $columns): ?array
    {
        $redis = $this->redis();
        if (!$redis || $conversationId <= 0) {
            return null;
        }

        $row = $this->cachedCustomerConversationRow($conversationId);
        if (!$row) {
            $row = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->customerConversationsTable} WHERE `conversation_id` = %d",
                $conversationId
            ), ARRAY_A);
        }
        if (!is_array($row)) {
            return null;
        }

        foreach ($columns as $column => $value) {
            $column = sanitize_key((string) $column);
            if ($column === '') {
                continue;
            }
            if (is_array($value) && isset($value['__increment'])) {
                $row[$column] = (int) ($row[$column] ?? 0) + 1;
                continue;
            }
            $row[$column] = $value;
        }

        if (!$this->storeCachedCustomerConversationRow($redis, $row)) {
            return null;
        }

        $conversation = $this->im->getConversationRow($conversationId);
        return $conversation ? $this->formatCustomerConversation(array_merge($conversation, $row)) : $this->formatCustomerConversation($row);
    }

    private function cachedCustomerConversationRow(int $conversationId): ?array
    {
        $redis = $this->redis();
        if (!$redis || $conversationId <= 0) {
            return null;
        }

        $raw = $redis->hGet(self::CUSTOMER_CONVERSATIONS_CACHE_KEY, (string) $conversationId);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $row = json_decode($raw, true);
        return is_array($row) ? $row : null;
    }

    private function cachedCustomerConversationRows(): array
    {
        $redis = $this->redis();
        if (!$redis) {
            return [];
        }

        $ids = $redis->zRevRange(self::CUSTOMER_CONVERSATION_INDEX_KEY, 0, 499) ?: [];
        return $this->cachedRowsByIds($redis, self::CUSTOMER_CONVERSATIONS_CACHE_KEY, $ids);
    }

    private function filterCachedCustomerConversations(array $args): array
    {
        $status = (string) ($args['status'] ?? '');
        $cursor = max(0, (int) ($args['cursor'] ?? 0));
        $search = mb_strtolower(trim((string) ($args['search'] ?? '')));
        $rows   = [];

        foreach ($this->cachedCustomerConversationRows() as $customer) {
            $conversationId = (int) ($customer['conversation_id'] ?? 0);
            if ($conversationId <= 0 || ($cursor > 0 && $conversationId >= $cursor)) {
                continue;
            }
            if ($status !== '' && (string) ($customer['status'] ?? '') !== $status) {
                continue;
            }

            $conversation = $this->im->getConversationRow($conversationId);
            if (!$conversation || (string) ($conversation['type'] ?? '') !== IM::TYPE_CUSTOMER_SERVICE) {
                continue;
            }

            $row = array_merge($conversation, $customer);
            if ($search !== '') {
                $haystack = mb_strtolower((string) ($row['subject'] ?? '') . ' ' . (string) ($row['last_msg_preview'] ?? '') . ' ' . (string) ($row['customer_guest_id'] ?? ''));
                if (!str_contains($haystack, $search)) {
                    continue;
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function mergeConversationRows(array $primary, array $secondary): array
    {
        $rows = [];
        foreach (array_merge($primary, $secondary) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $conversationId = (int) ($row['conversation_id'] ?? $row['id'] ?? 0);
            if ($conversationId <= 0) {
                continue;
            }
            $rows[$conversationId] = $row;
        }

        return array_values($rows);
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

    private function upsertCachedCustomerConversationRow(array $row): bool
    {
        $conversationId = (int) ($row['conversation_id'] ?? 0);
        if ($conversationId <= 0) {
            return false;
        }

        $data = [
            'conversation_id'      => $conversationId,
            'customer_user_id'     => isset($row['customer_user_id']) ? (int) $row['customer_user_id'] : null,
            'customer_guest_id'    => isset($row['customer_guest_id']) ? (string) $row['customer_guest_id'] : null,
            'assignee_user_id'     => isset($row['assignee_user_id']) ? (int) $row['assignee_user_id'] : null,
            'status'               => sanitize_key((string) ($row['status'] ?? CustomerConversation::STATUS_PENDING)),
            'close_reason'         => isset($row['close_reason']) ? sanitize_key((string) $row['close_reason']) : null,
            'first_response_at'    => $row['first_response_at'] ?? null,
            'last_customer_msg_at' => $row['last_customer_msg_at'] ?? null,
            'last_agent_msg_at'    => $row['last_agent_msg_at'] ?? null,
            'unread_customer'      => (int) ($row['unread_customer'] ?? 0),
            'unread_agent'         => (int) ($row['unread_agent'] ?? 0),
            'meta'                 => is_string($row['meta'] ?? null) ? $row['meta'] : null,
            'created_at'           => (string) ($row['created_at'] ?? Date::utcDateTime()),
            'updated_at'           => $row['updated_at'] ?? null,
            'closed_at'            => $row['closed_at'] ?? null,
        ];

        $exists = (bool) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT 1 FROM {$this->customerConversationsTable} WHERE `conversation_id` = %d LIMIT 1",
            $conversationId
        ));
        if ($exists) {
            $update = $data;
            unset($update['conversation_id']);
            $result = $this->wpdb->update($this->customerConversationsTable, $update, ['conversation_id' => $conversationId]);
            return $result !== false;
        }

        $result = $this->wpdb->insert($this->customerConversationsTable, $data);
        return $result !== false;
    }

    private function touchCacheKeys(Redis $redis, array $keys): void
    {
        foreach (array_unique(array_filter($keys)) as $key) {
            $redis->expire((string) $key, IMService::CACHE_TTL);
        }
    }

    private function cacheStorageEnabled(): bool
    {
        return $this->im->cacheStorageEnabled();
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

    private function formatCustomerConversation(array $row): array
    {
        $status = (string) ($row['status'] ?? CustomerConversation::STATUS_PENDING);
        return [
            'id'                         => (int) ($row['id'] ?? $row['conversation_id']),
            'conversation_id'            => (int) ($row['conversation_id'] ?? $row['id']),
            'type'                       => (string) ($row['type'] ?? IM::TYPE_CUSTOMER_SERVICE),
            'subject'                    => $row['subject'] ?? null,
            'state'                      => (string) ($row['state'] ?? IM::CONVERSATION_OPEN),
            'status'                     => $status,
            'customer_user_id'           => isset($row['customer_user_id']) && $row['customer_user_id'] !== null ? (int) $row['customer_user_id'] : null,
            'customer_guest_id'          => $row['customer_guest_id'] ?? null,
            'assignee_user_id'           => isset($row['assignee_user_id']) && $row['assignee_user_id'] !== null ? (int) $row['assignee_user_id'] : null,
            'close_reason'               => $row['close_reason'] ?? null,
            'first_response_at'          => $row['first_response_at'] ?? null,
            'first_response_at_local'    => $this->formatLocalDateTime($row['first_response_at'] ?? null),
            'last_customer_msg_at'       => $row['last_customer_msg_at'] ?? null,
            'last_customer_msg_at_local' => $this->formatLocalDateTime($row['last_customer_msg_at'] ?? null),
            'last_agent_msg_at'          => $row['last_agent_msg_at'] ?? null,
            'last_agent_msg_at_local'    => $this->formatLocalDateTime($row['last_agent_msg_at'] ?? null),
            'priority'                   => (int) ($row['priority'] ?? 0),
            'source'                     => (string) ($row['source'] ?? 'web'),
            'ip_address'                 => $row['ip_address'] ?? null,
            'user_agent'                 => $row['user_agent'] ?? null,
            'last_message_id'            => isset($row['last_message_id']) && $row['last_message_id'] !== null ? (int) $row['last_message_id'] : null,
            'last_msg_seq'               => (int) ($row['last_msg_seq'] ?? 0),
            'last_msg_type'              => $row['last_msg_type'] ?? null,
            'last_msg_preview'           => $row['last_msg_preview'] ?? null,
            'last_message_at'            => $row['last_message_at'] ?? null,
            'last_message_at_local'      => $this->formatLocalDateTime($row['last_message_at'] ?? null),
            'unread_customer'            => (int) ($row['unread_customer'] ?? 0),
            'unread_agent'               => (int) ($row['unread_agent'] ?? 0),
            'meta'                       => $this->decodeMeta($row['meta'] ?? null),
            'created_at'                 => $row['created_at'] ?? null,
            'created_at_local'           => $this->formatLocalDateTime($row['created_at'] ?? null),
            'updated_at'                 => $row['updated_at'] ?? null,
            'updated_at_local'           => $this->formatLocalDateTime($row['updated_at'] ?? null),
            'closed_at'                  => $row['closed_at'] ?? null,
            'closed_at_local'            => $this->formatLocalDateTime($row['closed_at'] ?? null),
        ];
    }

    private function formatLocalDateTime(?string $value): ?string
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

    private function customerIdentity(bool $createGuest = true): ?array
    {
        if ($this->isLoggedIn()) {
            $user    = $this->currentUser();
            $profile = $this->userIdentityProfile($user);
            return [
                'actor_type'   => IM::ACTOR_USER,
                'actor_id'     => (string) $user->ID,
                'user_id'      => (int) $user->ID,
                'role'         => IM::ROLE_CUSTOMER,
                'display_name' => $profile['display_name'],
                'avatar'       => $profile['avatar'],
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
        $user    = $this->currentUser();
        $profile = $this->userIdentityProfile($user);
        return [
            'actor_type'   => IM::ACTOR_AGENT,
            'actor_id'     => (string) $user->ID,
            'user_id'      => (int) $user->ID,
            'role'         => IM::ROLE_AGENT,
            'display_name' => $profile['display_name'],
            'avatar'       => $profile['avatar'],
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
            CustomerConversation::STATUS_HANDLING,
        ];
    }

    private function conversationStatuses(): array
    {
        return [
            CustomerConversation::STATUS_PENDING,
            CustomerConversation::STATUS_HANDLING,
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

    private function userIdentityProfile(object $user): array
    {
        $userId = (int) ($user->ID ?? 0);
        $name   = trim((string) ($user->display_name ?? '')) ?: (string) ($user->user_login ?? '');
        $avatar = '';

        if ($userId > 0) {
            /** @var UserService $userService */
            $userService = $this->container->get(UserService::class);
            $profile     = $userService->init($userId)?->cache();
            if (is_array($profile)) {
                $name   = trim((string) ($profile['nickname'] ?? '')) ?: $name;
                $avatar = trim((string) ($profile['avatar'] ?? ''));
            }
        }

        return [
            'display_name' => $name,
            'avatar'       => $avatar !== '' ? $avatar : UserService::getDefaultAvatar(),
        ];
    }
}
