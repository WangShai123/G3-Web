<?php
namespace JEALER\G3\Services;
use JEALER\G3\Core\Service\Service;
use WP_Error;

class IMRealtimeService extends Service {
    public function publish(string $conversationType, ?int $conversationId, string $type, array $payload, array $meta = []): void
    {
        $this->notification()->publish($this->adminChannel($conversationType), $type, $payload, $meta);
        if ($conversationId) {
            $this->notification()->publish($this->viewerChannel($conversationId), $type, $payload, $meta);
        }
    }

    public function createSession(string $conversationType, string $scope, ?int $conversationId, int $afterId, int $heartbeat): array|WP_Error
    {
        $channel = $scope === 'admin'
            ? $this->adminChannel($conversationType)
            : ($conversationId ? $this->viewerChannel($conversationId) : '');

        if ($channel === '') {
            return new WP_Error('conversation_required', 'Conversation is required.', ['status' => 400]);
        }

        return $this->notification()->createSession($channel, $afterId, $heartbeat);
    }

    public function latestEventId(string $conversationType): int
    {
        return $this->notification()->latestId($this->adminChannel($conversationType));
    }

    private function notification(): NotificationService
    {
        return $this->container->get(NotificationService::class);
    }

    private function adminChannel(string $conversationType): string
    {
        return 'im.' . sanitize_key($conversationType) . '.admin';
    }

    private function viewerChannel(int $conversationId): string
    {
        return 'im.conversation.' . $conversationId;
    }
}
