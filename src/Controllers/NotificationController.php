<?php
namespace JEALER\G3\Controllers;
use JEALER\G3\Core\Attributes\Middleware;
use JEALER\G3\Core\Attributes\RestRouter;
use JEALER\G3\Core\Router\Controller;
use JEALER\G3\Middleware\RateLimitMiddleware;
use JEALER\G3\Middleware\RoleMiddleware;
use JEALER\G3\Services\NotificationService;
use WP_REST_Request;
use WP_REST_Response;

class NotificationController extends Controller {
    public function __construct(private NotificationService $service) {}

    #[RestRouter(namespace: 'api/notify', route: 'v1/stream', methods: 'POST')]
    #[Middleware(RateLimitMiddleware::class, [120, 60])]
    public function stream(WP_REST_Request $request): void
    {
        $data        = $request->get_json_params() ?: [];
        $lastEventId = max(0, (int) ($request->get_header('last-event-id') ?: ($data['last_event_id'] ?? 0)));
        $this->service->stream((string) ($data['token'] ?? ''), $lastEventId);
    }

    #[RestRouter(namespace: 'api/admin/notify', route: 'v1/stream', methods: 'POST')]
    #[Middleware(RoleMiddleware::class, ['administrator'])]
    public function adminStream(WP_REST_Request $request): void
    {
        $data        = $request->get_json_params() ?: [];
        $lastEventId = max(0, (int) ($request->get_header('last-event-id') ?: ($data['last_event_id'] ?? 0)));
        $this->service->stream((string) ($data['token'] ?? ''), $lastEventId);
    }

    #[RestRouter(namespace: 'api/admin/notify', route: 'v1/events/consume', methods: 'POST')]
    #[Middleware(RoleMiddleware::class, ['administrator'])]
    public function consumeEvents(WP_REST_Request $request): WP_REST_Response
    {
        $data = $request->get_json_params() ?: [];
        $ids  = $data['event_ids'] ?? ($data['event_id'] ?? []);

        return $this->ok([
            'deleted' => $this->service->consumeEvents($ids),
        ]);
    }

    private function ok(mixed $data): WP_REST_Response
    {
        return rest_ensure_response([
            'success' => true,
            'code'    => 200,
            'data'    => $data,
        ]);
    }
}
