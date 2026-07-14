<?php
namespace JEALER\G3\Services;
use JEALER\G3\Core\Service\Service;
use Redis;
use Throwable;
use WP_Error;

class NotificationService extends Service {
    public const REDIS_DB = 3;
    private string $table;

    public function __construct()
    {
        parent::__construct();
        $this->table = $this->wpdb->prefix . 'g3_notifications';
    }

    public function publish(string $channel, string $type, array $payload = [], array $meta = []): int|WP_Error
    {
        $channel = $this->sanitizeChannel($channel);
        $type    = $this->sanitizeEventType($type);

        if ($channel === '' || $type === '') {
            return new WP_Error('invalid_notification', 'Invalid notification channel or type.', ['status' => 400]);
        }

        $result = $this->wpdb->insert($this->table, [
            'channel'     => $channel,
            'event_type'  => $type,
            'target_type' => isset($meta['target_type']) ? sanitize_key((string) $meta['target_type']) : null,
            'target_id'   => isset($meta['target_id']) ? (string) $meta['target_id'] : null,
            'actor_type'  => isset($meta['actor_type']) ? sanitize_key((string) $meta['actor_type']) : null,
            'actor_id'    => isset($meta['actor_id']) ? (string) $meta['actor_id'] : null,
            'payload'     => $this->encode($payload),
            'created_at'  => gmdate('Y-m-d H:i:s'),
        ]);

        if ($result === false) {
            return new WP_Error('notification_insert_failed', 'Failed to publish notification.', ['status' => 500]);
        }

        $id = (int) $this->wpdb->insert_id;
        $this->fanout($channel, $id);

        return $id;
    }

    public function createSession(array|string $channels, int $afterId = 0, int $heartbeatSeconds = 45): array|WP_Error
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

        $redis = $this->redis();
        if (!$redis) {
            return new WP_Error('redis_unavailable', 'Notification stream requires Redis.', ['status' => 503]);
        }

        $redis->setex($this->sessionKey($token), DAY_IN_SECONDS, wp_json_encode($session, JSON_UNESCAPED_UNICODE) ?: '{}');
        $redis->del($this->queueKey($token));
        foreach ($channels as $channel) {
            $redis->sAdd($this->channelSessionsKey($channel), $token);
            $redis->expire($this->channelSessionsKey($channel), DAY_IN_SECONDS);
        }

        return [
            'token'             => $token,
            'after_id'          => $session['after_id'],
            'heartbeat_seconds' => $heartbeatSeconds,
        ];
    }

    public function stream(string $token): void
    {
        $unlimited = $this->prepareStreamRuntime();
        $this->sendHeaders();

        $session = $this->session($token);
        if (!$session) {
            $this->sse('error', ['code' => 'invalid_stream_session', 'message' => 'Invalid stream session.']);
            exit;
        }

        $channels  = $session['channels'];
        $afterId   = (int) $session['after_id'];
        $heartbeat = min(60, max(30, (int) $session['heartbeat_seconds']));
        $redis     = $this->redis();
        $queueKey  = $this->queueKey($token);
        $lastBeat  = time();
        $deadline  = $unlimited ? 0 : $this->streamDeadline();

        while (!connection_aborted() && ($deadline <= 0 || time() < $deadline)) {
            $this->prepareStreamRuntime();
            $events = $this->events($channels, $afterId, 100);
            foreach ($events as $event) {
                $afterId = max($afterId, (int) $event['id']);
                $this->sse($event['event_type'], $event, $afterId);
            }
            if ($events) {
                $lastBeat = time();
            }

            if (!$redis) {
                sleep($this->waitSeconds($heartbeat, $deadline));
                if (!$events && (time() - $lastBeat) >= $heartbeat) {
                    $this->sse('heartbeat', ['time' => time()], $afterId);
                    $lastBeat = time();
                }
                continue;
            }

            $redis->setex($this->sessionKey($token), DAY_IN_SECONDS, wp_json_encode([
                'token'             => $token,
                'channels'          => $channels,
                'after_id'          => $afterId,
                'heartbeat_seconds' => $heartbeat,
                'created_at'        => $session['created_at'] ?? time(),
            ], JSON_UNESCAPED_UNICODE) ?: '{}');

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

        $limit        = min(200, max(1, $limit));
        $placeholders = implode(',', array_fill(0, count($channels), '%s'));
        $params       = array_merge($channels, [max(0, $afterId), $limit]);

        $rows = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE `channel` IN ({$placeholders}) AND `id` > %d
             ORDER BY `id` ASC
             LIMIT %d",
            $params
        ), ARRAY_A) ?: [];

        return array_map(fn(array $row): array => $this->format($row), $rows);
    }

    public function latestId(?string $channel = null): int
    {
        $channel = $channel !== null ? $this->sanitizeChannel($channel) : '';
        if ($channel !== '') {
            return (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT MAX(`id`) FROM {$this->table} WHERE `channel` = %s",
                $channel
            ));
        }

        return (int) $this->wpdb->get_var("SELECT MAX(`id`) FROM {$this->table}");
    }

    private function fanout(string $channel, int $eventId): void
    {
        $redis = $this->redis();
        if (!$redis) {
            return;
        }

        $sessionsKey = $this->channelSessionsKey($channel);
        $tokens      = $redis->sMembers($sessionsKey) ?: [];
        foreach ($tokens as $token) {
            $token = (string) $token;
            if (!$redis->exists($this->sessionKey($token))) {
                $redis->sRem($sessionsKey, $token);
                continue;
            }

            $queueKey = $this->queueKey($token);
            $redis->rPush($queueKey, (string) $eventId);
            $redis->expire($queueKey, DAY_IN_SECONDS);
        }
    }

    private function session(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            return null;
        }

        $redis = $this->redis();
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

    private function redis(): ?Redis
    {
        try {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379, 0.2);
            $redis->select(self::REDIS_DB);
            return $redis;
        }
        catch (Throwable) {
            return null;
        }
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
        return 'g3:notify:session:' . $token;
    }

    private function queueKey(string $token): string
    {
        return 'g3:notify:session:' . $token . ':queue';
    }

    private function channelSessionsKey(string $channel): string
    {
        return 'g3:notify:channel:' . $channel . ':sessions';
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
            'payload'     => $this->decode($row['payload'] ?? ''),
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
        echo 'event: ' . $event . "\n";
        echo 'data: ' . wp_json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
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
}
