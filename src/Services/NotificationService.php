<?php
namespace JEALER\G3\Services;
use JEALER\G3\Core\Service\Service;
use JEALER\G3\Utilities\Type;
use Redis;
use WP_Error;

class NotificationService extends Service {
    private const SSE_NAMESPACE = 'g3.notification';
    private const CACHE_GROUP = 'g3_notification';
    private const EVENT_COUNTER_KEY = 'g3_notification:counters:event_id';
    private const EVENT_INDEX_KEY = 'g3_notification:events:index';
    private const EVENT_TTL = DAY_IN_SECONDS;
    private const EVENT_INDEX_TTL_BUFFER = HOUR_IN_SECONDS;
    private const EVENT_GC_LIMIT = 200;
    private const SESSION_TTL_MIN = 120;
    private const SESSION_TTL_MAX = 300;
    private const SESSION_INDEX_TTL_BUFFER = 60;
    private const CLOSE_SIGNAL_TTL = 30;

    public function publish(string $channel, string $type, array $payload = [], array $meta = []): int|WP_Error
    {
        $channel = $this->sanitizeChannel($channel);
        $type    = $this->sanitizeEventType($type);

        if ($channel === '' || $type === '') {
            return new WP_Error('invalid_notification', 'Invalid notification channel or type.', ['status' => 400]);
        }

        $redis = $this->redis();
        if (!$redis) {
            return new WP_Error('redis_unavailable', 'Notification publish requires Redis.', ['status' => 503]);
        }

        $id  = $this->nextEventId($redis);
        $row = [
            'id'          => $id,
            'channel'     => $channel,
            'event_type'  => $type,
            'target_type' => isset($meta['target_type']) ? sanitize_key((string) $meta['target_type']) : null,
            'target_id'   => isset($meta['target_id']) ? (string) $meta['target_id'] : null,
            'actor_type'  => isset($meta['actor_type']) ? sanitize_key((string) $meta['actor_type']) : null,
            'actor_id'    => isset($meta['actor_id']) ? (string) $meta['actor_id'] : null,
            'payload'     => Type::arrayToJson($payload),
            'created_at'  => gmdate('Y-m-d H:i:s'),
        ];

        $encoded = Type::arrayToJson($row);
        if (!is_string($encoded) || $encoded === '') {
            return new WP_Error('notification_encode_failed', 'Failed to encode notification.', ['status' => 500]);
        }

        if (!$redis->setex($this->eventKey($id), self::EVENT_TTL, $encoded)) {
            return new WP_Error('notification_cache_failed', 'Failed to cache notification.', ['status' => 500]);
        }

        $redis->zAdd(self::EVENT_INDEX_KEY, $id, (string) $id);
        $redis->zAdd($this->channelEventsKey($channel), $id, (string) $id);
        $redis->expire(self::EVENT_INDEX_KEY, self::EVENT_TTL + self::EVENT_INDEX_TTL_BUFFER);
        $redis->expire($this->channelEventsKey($channel), self::EVENT_TTL + self::EVENT_INDEX_TTL_BUFFER);

        $this->cleanupExpiredEvents($redis, [$channel]);
        $this->fanout($redis, $channel, $id);

        return $id;
    }

    public function createSession(array|string $channels, int $afterId = 0, int $heartbeatSeconds = 45, string $owner = ''): array|WP_Error
    {
        $channels = is_array($channels) ? $channels : [$channels];
        $channels = array_values(array_filter(array_unique(array_map(fn($channel): string => $this->sanitizeChannel((string) $channel), $channels))));

        if (!$channels) {
            return new WP_Error('notification_channel_required', 'Notification channel is required.', ['status' => 400]);
        }

        $token            = bin2hex(random_bytes(24));
        $heartbeatSeconds = min(60, max(30, $heartbeatSeconds));
        $session          = [
            'token'             => $token,
            'channels'          => $channels,
            'after_id'          => max(0, $afterId),
            'heartbeat_seconds' => $heartbeatSeconds,
            'created_at'        => time(),
        ];
        $owner            = $this->sanitizeOwner($owner);
        if ($owner !== '') {
            $session['owner'] = $owner;
        }

        $redis = $this->redis();
        if (!$redis) {
            return new WP_Error('redis_unavailable', 'Notification stream requires Redis.', ['status' => 503]);
        }

        $redis->del($this->queueKey($token));
        $this->replaceOwnedSession($redis, $session);
        if (!$this->storeSession($redis, $session)) {
            return new WP_Error('notification_session_replaced', 'Notification stream session was replaced.', ['status' => 409]);
        }

        return [
            'token'             => $token,
            'after_id'          => $session['after_id'],
            'heartbeat_seconds' => $heartbeatSeconds,
        ];
    }

    public function stream(string $token, int $lastEventId = 0): void
    {
        $unlimited = $this->prepareStreamRuntime();
        $this->sendHeaders();

        $session = $this->session($token);
        if (!$session) {
            $this->sse('error', ['code' => 'invalid_stream_session', 'message' => 'Invalid stream session.']);
            exit;
        }

        $channels  = $session['channels'];
        $afterId   = max((int) $session['after_id'], max(0, $lastEventId));
        $heartbeat = min(60, max(30, (int) $session['heartbeat_seconds']));
        $redis     = $this->redis();
        if (!$redis) {
            $this->sse('error', ['code' => 'redis_unavailable', 'message' => 'Notification stream requires Redis.'], $afterId);
            exit;
        }

        $queueKey  = $this->queueKey($token);
        $lastBeat  = time();
        $deadline  = $unlimited ? 0 : $this->streamDeadline();

        while (!connection_aborted() && ($deadline <= 0 || time() < $deadline)) {
            $this->prepareStreamRuntime();

            if (!$this->storeSession($redis, [
                'token'             => $token,
                'channels'          => $channels,
                'after_id'          => $afterId,
                'heartbeat_seconds' => $heartbeat,
                'created_at'        => $session['created_at'] ?? time(),
                'owner'             => $session['owner'] ?? '',
            ])) {
                break;
            }

            $events = $this->events($channels, $afterId, 100);
            foreach ($events as $event) {
                $afterId = max($afterId, (int) $event['id']);
                $this->sse($event['event_type'], $event, $afterId);
            }
            if ($events) {
                $lastBeat = time();
            }
            $hasMoreEvents = count($events) >= 100;

            if ($events && !$this->storeSession($redis, [
                'token'             => $token,
                'channels'          => $channels,
                'after_id'          => $afterId,
                'heartbeat_seconds' => $heartbeat,
                'created_at'        => $session['created_at'] ?? time(),
                'owner'             => $session['owner'] ?? '',
            ])) {
                break;
            }
            if ($events) {
                $this->cleanupAcknowledgedEvents($redis, $channels, $afterId);
            }

            if ($hasMoreEvents) {
                continue;
            }

            $signal = $redis->blPop([$queueKey], $this->waitSeconds($heartbeat, $deadline));
            if (!$signal && (time() - $lastBeat) >= $heartbeat) {
                $this->sse('heartbeat', ['time' => time()], $afterId);
                $lastBeat = time();
            }
        }

        exit;
    }

    public function events(array $channels, int $afterId = 0, int $limit = 100): array
    {
        $channels = array_values(array_filter(array_unique(array_map(fn($channel): string => $this->sanitizeChannel((string) $channel), $channels))));
        if (!$channels) {
            return [];
        }

        $redis = $this->redis();
        if (!$redis) {
            return [];
        }

        $this->cleanupExpiredEvents($redis, $channels);

        $limit = min(200, max(1, $limit));
        $ids   = [];
        foreach ($channels as $channel) {
            $channelIds = $redis->zRangeByScore(
                $this->channelEventsKey($channel),
                (string) (max(0, $afterId) + 1),
                '+inf',
                ['limit' => [0, $limit]]
            ) ?: [];
            $ids = array_merge($ids, $channelIds);
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);
        $ids = array_slice($ids, 0, $limit);

        $rows = [];
        foreach ($ids as $eventId) {
            $row = $this->eventRow($redis, $eventId);
            if (!$row) {
                $this->deleteEvent($redis, $eventId);
                continue;
            }
            if (!in_array((string) ($row['channel'] ?? ''), $channels, true)) {
                continue;
            }
            $rows[] = $row;
        }

        return array_map(fn(array $row): array => $this->format($row), $rows);
    }

    public function latestId(?string $channel = null): int
    {
        $redis = $this->redis();
        if (!$redis) {
            return 0;
        }

        return max(0, (int) $redis->get(self::EVENT_COUNTER_KEY));
    }

    private function fanout(Redis $redis, string $channel, int $eventId): void
    {
        $sessionsKey = $this->channelSessionsKey($channel);
        $now         = time();
        $this->cleanupChannelSessions($redis, $sessionsKey, $now);

        $tokens = $redis->zRangeByScore($sessionsKey, (string) $now, '+inf') ?: [];
        foreach ($tokens as $token) {
            $token = (string) $token;
            $session = $this->session($token, $redis);
            if (!$session) {
                $redis->zRem($sessionsKey, $token);
                continue;
            }

            $queueKey = $this->queueKey($token);
            $redis->rPush($queueKey, (string) $eventId);
            $redis->expire($queueKey, $this->sessionTtl((int) ($session['heartbeat_seconds'] ?? 45)));
        }

        if ((int) $redis->zCard($sessionsKey) <= 0) {
            $redis->del($sessionsKey);
        } else {
            $redis->expire($sessionsKey, $this->channelSessionsTtl());
        }
    }

    private function nextEventId(Redis $redis): int
    {
        return (int) $redis->incr(self::EVENT_COUNTER_KEY);
    }

    private function eventRow(Redis $redis, int $eventId): ?array
    {
        if ($eventId <= 0) {
            return null;
        }

        $raw = $redis->get($this->eventKey($eventId));
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $row = json_decode($raw, true);
        return is_array($row) ? $row : null;
    }

    private function cleanupAcknowledgedEvents(Redis $redis, array $channels, int $afterId): void
    {
        if ($afterId <= 0) {
            return;
        }

        foreach ($channels as $channel) {
            $channel = $this->sanitizeChannel((string) $channel);
            if ($channel === '') {
                continue;
            }

            $ids = $redis->zRangeByScore(
                $this->channelEventsKey($channel),
                '-inf',
                (string) $afterId,
                ['limit' => [0, self::EVENT_GC_LIMIT]]
            ) ?: [];

            foreach ($ids as $id) {
                $eventId = (int) $id;
                if ($eventId <= 0 || $this->channelHasPendingSession($redis, $channel, $eventId)) {
                    continue;
                }

                $this->deleteEvent($redis, $eventId, $channel);
            }
        }
    }

    private function channelHasPendingSession(Redis $redis, string $channel, int $eventId): bool
    {
        $sessionsKey = $this->channelSessionsKey($channel);
        $now         = time();
        $this->cleanupChannelSessions($redis, $sessionsKey, $now);

        $tokens = $redis->zRangeByScore($sessionsKey, (string) $now, '+inf') ?: [];
        foreach ($tokens as $token) {
            $token   = (string) $token;
            $session = $this->session($token, $redis);
            if (!$session) {
                $redis->zRem($sessionsKey, $token);
                continue;
            }

            $sessionChannels = is_array($session['channels'] ?? null) ? $session['channels'] : [];
            if (in_array($channel, $sessionChannels, true) && (int) ($session['after_id'] ?? 0) < $eventId) {
                return true;
            }
        }

        return false;
    }

    private function cleanupExpiredEvents(Redis $redis, array $channels = []): void
    {
        $ids = $redis->zRange(self::EVENT_INDEX_KEY, 0, self::EVENT_GC_LIMIT - 1) ?: [];
        foreach ($ids as $id) {
            $eventId = (int) $id;
            if ($eventId > 0 && !$this->eventRow($redis, $eventId)) {
                $this->deleteEvent($redis, $eventId);
            }
        }

        foreach ($channels as $channel) {
            $channel = $this->sanitizeChannel((string) $channel);
            if ($channel === '') {
                continue;
            }

            $ids = $redis->zRange($this->channelEventsKey($channel), 0, self::EVENT_GC_LIMIT - 1) ?: [];
            foreach ($ids as $id) {
                $eventId = (int) $id;
                if ($eventId > 0 && !$this->eventRow($redis, $eventId)) {
                    $this->deleteEvent($redis, $eventId, $channel);
                }
            }
        }
    }

    private function deleteEvent(Redis $redis, int $eventId, string $channel = ''): void
    {
        if ($eventId <= 0) {
            return;
        }

        $row = $channel === '' ? $this->eventRow($redis, $eventId) : null;
        if ($channel === '' && is_array($row)) {
            $channel = $this->sanitizeChannel((string) ($row['channel'] ?? ''));
        }

        $redis->del($this->eventKey($eventId));
        $redis->zRem(self::EVENT_INDEX_KEY, (string) $eventId);
        if ($channel !== '') {
            $redis->zRem($this->channelEventsKey($channel), (string) $eventId);
        }
    }

    private function replaceOwnedSession(Redis $redis, array $session): void
    {
        $owner = $this->sanitizeOwner((string) ($session['owner'] ?? ''));
        $token = (string) ($session['token'] ?? '');
        if ($owner === '' || !$this->validSessionToken($token)) {
            return;
        }

        $ownerKey      = $this->ownerKey($owner);
        $previousToken = $redis->getSet($ownerKey, $token);
        $redis->expire($ownerKey, $this->ownerTtl((int) ($session['heartbeat_seconds'] ?? 45)));

        if (!is_string($previousToken) || $previousToken === '' || $previousToken === $token || !$this->validSessionToken($previousToken)) {
            return;
        }

        $previousSession = $this->session($previousToken, $redis);
        $channels        = is_array($previousSession['channels'] ?? null) ? $previousSession['channels'] : (array) ($session['channels'] ?? []);
        $this->destroySession($previousToken, $channels, $redis, $owner);
    }

    private function storeSession(Redis $redis, array $session): bool
    {
        $token = (string) ($session['token'] ?? '');
        if (!$this->validSessionToken($token)) {
            return false;
        }

        $channels = array_values(array_filter(array_unique(array_map(
            fn($channel): string => $this->sanitizeChannel((string) $channel),
            is_array($session['channels'] ?? null) ? $session['channels'] : []
        ))));

        if (!$channels) {
            return false;
        }

        $owner = $this->sanitizeOwner((string) ($session['owner'] ?? ''));
        if ($owner !== '' && !$this->ownsSession($redis, $owner, $token)) {
            return false;
        }

        $heartbeat            = min(60, max(30, (int) ($session['heartbeat_seconds'] ?? 45)));
        $ttl                  = $this->sessionTtl($heartbeat);
        $expiresAt            = time() + $ttl;
        $session['token']      = $token;
        $session['channels']   = $channels;
        $session['after_id']   = max(0, (int) ($session['after_id'] ?? 0));
        $session['created_at'] = max(0, (int) ($session['created_at'] ?? time()));
        $session['expires_at'] = $expiresAt;
        if ($owner !== '') {
            $session['owner'] = $owner;
        }

        $redis->setex($this->sessionKey($token), $ttl, wp_json_encode($session, JSON_UNESCAPED_UNICODE) ?: '{}');
        if ($owner !== '') {
            $redis->expire($this->ownerKey($owner), $this->ownerTtl($heartbeat));
        }

        foreach ($channels as $channel) {
            $this->touchChannelSession($redis, $channel, $token, $expiresAt);
        }

        return true;
    }

    private function destroySession(string $token, array $channels, ?Redis $redis = null, string $owner = ''): void
    {
        if (!$this->validSessionToken($token)) {
            return;
        }

        $redis ??= $this->redis();
        if (!$redis) {
            return;
        }

        $session = $this->session($token, $redis);
        $owner   = $this->sanitizeOwner($owner !== '' ? $owner : (string) ($session['owner'] ?? ''));
        if (is_array($session['channels'] ?? null)) {
            $channels = $session['channels'];
        }

        foreach ($channels as $channel) {
            $this->removeChannelSession($redis, (string) $channel, $token);
        }

        $queueKey = $this->queueKey($token);
        $redis->rPush($queueKey, 'close');
        $redis->expire($queueKey, self::CLOSE_SIGNAL_TTL);
        $redis->del($this->sessionKey($token));
        if ($owner !== '') {
            $this->releaseOwnedSession($redis, $owner, $token);
        }
    }

    private function touchChannelSession(Redis $redis, string $channel, string $token, int $expiresAt): void
    {
        $channel = $this->sanitizeChannel($channel);
        if ($channel === '') {
            return;
        }

        $sessionsKey = $this->channelSessionsKey($channel);
        $this->cleanupChannelSessions($redis, $sessionsKey);
        $redis->zAdd($sessionsKey, $expiresAt, $token);
        $redis->expire($sessionsKey, $this->channelSessionsTtl());
    }

    private function removeChannelSession(Redis $redis, string $channel, string $token): void
    {
        $channel = $this->sanitizeChannel($channel);
        if ($channel === '') {
            return;
        }

        $sessionsKey = $this->channelSessionsKey($channel);
        $type        = $redis->type($sessionsKey);

        if ($type === Redis::REDIS_ZSET) {
            $redis->zRem($sessionsKey, $token);
            if ((int) $redis->zCard($sessionsKey) <= 0) {
                $redis->del($sessionsKey);
            }
            return;
        }

        if ($type === Redis::REDIS_SET) {
            $redis->sRem($sessionsKey, $token);
            if ((int) $redis->sCard($sessionsKey) <= 0) {
                $redis->del($sessionsKey);
            }
        }
    }

    private function cleanupChannelSessions(Redis $redis, string $sessionsKey, ?int $now = null): void
    {
        $this->prepareChannelSessionsIndex($redis, $sessionsKey);
        $redis->zRemRangeByScore($sessionsKey, '-inf', (string) ($now ?? time()));
    }

    private function prepareChannelSessionsIndex(Redis $redis, string $sessionsKey): void
    {
        $type = $redis->type($sessionsKey);
        if ($type === Redis::REDIS_NOT_FOUND || $type === Redis::REDIS_ZSET) {
            return;
        }

        $redis->del($sessionsKey);
    }

    private function session(string $token, ?Redis $redis = null): ?array
    {
        if (!$this->validSessionToken($token)) {
            return null;
        }

        $redis ??= $this->redis();
        if (!$redis) {
            return null;
        }

        $raw = $redis->get($this->sessionKey($token));
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $session = json_decode($raw, true);
        return is_array($session) && !empty($session['channels']) ? $session : null;
    }

    private function ownsSession(Redis $redis, string $owner, string $token): bool
    {
        $currentToken = $redis->get($this->ownerKey($owner));
        if ($currentToken === false || $currentToken === null || $currentToken === '') {
            $redis->setex($this->ownerKey($owner), $this->ownerTtl(), $token);
            return true;
        }

        return is_string($currentToken) && $currentToken === $token;
    }

    private function releaseOwnedSession(Redis $redis, string $owner, string $token): void
    {
        $ownerKey     = $this->ownerKey($owner);
        $currentToken = $redis->get($ownerKey);
        if (is_string($currentToken) && $currentToken === $token) {
            $redis->del($ownerKey);
        }
    }

    private function validSessionToken(string $token): bool
    {
        return (bool) preg_match('/^[a-f0-9]{48}$/', $token);
    }

    private function sanitizeOwner(string $owner): string
    {
        $owner = trim($owner);
        return preg_match('/^[a-zA-Z0-9._:-]{1,200}$/', $owner) ? $owner : '';
    }

    private function sessionTtl(int $heartbeat): int
    {
        $heartbeat = min(60, max(30, $heartbeat));
        return min(self::SESSION_TTL_MAX, max(self::SESSION_TTL_MIN, $heartbeat * 4));
    }

    private function channelSessionsTtl(): int
    {
        return self::SESSION_TTL_MAX + self::SESSION_INDEX_TTL_BUFFER;
    }

    private function ownerTtl(int $heartbeat = 45): int
    {
        return $this->sessionTtl($heartbeat) + self::SESSION_INDEX_TTL_BUFFER;
    }

    private function redis(): ?Redis
    {
        /** @var RedisService $redisService */
        $redisService = $this->container->get(RedisService::class);
        return $redisService->init(DBService::NOTIFICATION_REDIS_DB);
    }

    private function prepareStreamRuntime(): bool
    {
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(false);
        }

        if (function_exists('set_time_limit') && !$this->functionDisabled('set_time_limit')) {
            @set_time_limit(0);
        }

        return (int) ini_get('max_execution_time') <= 0;
    }

    private function waitSeconds(int $heartbeat, int $deadline = 0): int
    {
        $heartbeat = min(60, max(30, $heartbeat));
        $limit     = (int) ini_get('max_execution_time');
        $wait      = $limit <= 0 ? $heartbeat : max(5, min($heartbeat, $limit - 5));

        if ($deadline > 0) {
            $wait = min($wait, max(1, $deadline - time()));
        }

        return max(1, $wait);
    }

    private function streamDeadline(): int
    {
        $limit = (int) ini_get('max_execution_time');
        if ($limit <= 0) {
            return 0;
        }

        return time() + max(5, $limit - 5);
    }

    private function functionDisabled(string $function): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return in_array($function, $disabled, true);
    }

    private function sanitizeChannel(string $channel): string
    {
        $channel = trim($channel);
        return preg_match('/^[a-zA-Z0-9._:-]{1,120}$/', $channel) ? $channel : '';
    }

    private function sanitizeEventType(string $type): string
    {
        $type = trim($type);
        return preg_match('/^[a-zA-Z0-9._:-]{1,64}$/', $type) ? $type : '';
    }

    private function sessionKey(string $token): string
    {
        return self::CACHE_GROUP . ':session:' . $token;
    }

    private function queueKey(string $token): string
    {
        return self::CACHE_GROUP . ':session:' . $token . ':queue';
    }

    private function eventKey(int $eventId): string
    {
        return self::CACHE_GROUP . ':event:' . $eventId;
    }

    private function channelEventsKey(string $channel): string
    {
        return self::CACHE_GROUP . ':channel:' . $channel . ':events';
    }

    private function channelSessionsKey(string $channel): string
    {
        return self::CACHE_GROUP . ':channel:' . $channel . ':sessions';
    }

    private function ownerKey(string $owner): string
    {
        return self::CACHE_GROUP . ':owner:' . sha1($owner) . ':session';
    }

    private function format(array $row): array
    {
        return [
            'id'          => (int) $row['id'],
            'channel'     => $row['channel'],
            'event_type'  => $row['event_type'],
            'target_type' => $row['target_type'],
            'target_id'   => $row['target_id'],
            'actor_type'  => $row['actor_type'],
            'actor_id'    => $row['actor_id'],
            'payload'     => Type::jsonToArray($row['payload'] ?? ''),
            'created_at'  => $row['created_at'],
        ];
    }

    private function sendHeaders(): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/event-stream; charset=utf-8');
            header('Cache-Control: no-cache, no-transform');
            header('X-Accel-Buffering: no');
            header('Connection: keep-alive');
        }

        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
    }

    private function sse(string $event, array $data, int $id = 0): void
    {
        if ($id > 0) {
            echo "id: {$id}\n";
        }

        $message = [
            'namespace'   => self::SSE_NAMESPACE,
            'type'        => $event,
            'channel'     => (string) ($data['channel'] ?? ''),
            'target_type' => (string) ($data['target_type'] ?? ''),
            'target_id'   => (string) ($data['target_id'] ?? ''),
            'actor_type'  => (string) ($data['actor_type'] ?? ''),
            'actor_id'    => (string) ($data['actor_id'] ?? ''),
            'created_at'  => (string) ($data['created_at'] ?? ''),
            'payload'     => $data['payload'] ?? $data,
        ];
        echo 'event: ' . $event . "\n";
        echo 'data: ' . wp_json_encode($message, JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    }
}
