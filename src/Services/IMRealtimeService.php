<?php
namespace JEALER\G3\Services;
use JEALER\G3\Core\IM\IM;
use JEALER\G3\Core\Service\Service;
use JEALER\G3\Services\NotificationService;
use WP_Error;

class IMRealtimeService extends Service {
    public function publish(string $conversationType, ?int $conversationId, string $type, array $payload, array $meta = []): void
    {
        $this->notification()->publish($this->adminChannel($conversationType), $type, $payload, $meta);
        if ($conversationId) {
            $this->notification()->publish($this->viewerChannel($conversationId), $type, $payload, $meta);
        }
    }

    public function createSession(string $conversationType, string $scope, ?int $conversationId, int $afterId, int $heartbeat, string $owner = ''): array|WP_Error
    {
        $channel = $scope === 'admin'
            ? $this->adminChannel($conversationType)
            : ($conversationId ? $this->viewerChannel($conversationId) : '');

        if ($channel === '') {
            return new WP_Error('conversation_required', 'Conversation is required.', ['status' => 400]);
        }

        return $this->notification()->createSession($channel, $afterId, $heartbeat, $owner);
    }

    public function latestEventId(string $conversationType): int
    {
        return $this->notification()->latestId($this->adminChannel($conversationType));
    }

    public function cleanupReadMessageNotifications(string $conversationType, int $conversationId, int $messageId, string $readerRole): int
    {
        if ($conversationId <= 0 || $messageId <= 0) {
            return 0;
        }

        $channel = in_array($readerRole, [IM::ROLE_AGENT, IM::ROLE_SYSTEM], true)
            ? $this->adminChannel($conversationType)
            : $this->viewerChannel($conversationId);

        return $this->notification()->cleanupConsumedMessageEvents(
            $channel,
            'im_conversation',
            (string) $conversationId,
            $messageId
        );
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
