<?php
namespace JEALER\G3\Controllers;
use JEALER\G3\Core\Attributes\Middleware;
use JEALER\G3\Core\Attributes\RestRouter;
use JEALER\G3\Core\Router\Controller;
use JEALER\G3\Middleware\RateLimitMiddleware;
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
        $data = $request->get_json_params() ?: [];
        $this->service->stream((string) ($data['token'] ?? ''));
    }
}
