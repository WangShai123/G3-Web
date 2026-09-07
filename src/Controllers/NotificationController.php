<?php
namespace JEALER\G3\Controllers;
use JEALER\G3\Core\Attributes\Middleware;
use JEALER\G3\Core\Attributes\RestRouter;
use JEALER\G3\Core\Router\Controller;
use JEALER\G3\Middleware\RateLimitMiddleware;
use JEALER\G3\Middleware\RoleMiddleware;
use JEALER\G3\Services\NotificationService;
use WP_REST_Request;

class NotificationController extends Controller {
    public function __construct(private NotificationService $service)
    {
        parent::__construct();
    }

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
}
