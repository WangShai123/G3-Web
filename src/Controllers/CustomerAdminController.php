<?php
namespace JEALER\G3\Controllers;
use JEALER\G3\Core\Attributes\Middleware;
use JEALER\G3\Core\Attributes\RestRouter;
use JEALER\G3\Core\Attributes\Schema;
use JEALER\G3\Core\Router\Controller;
use JEALER\G3\Middleware\RoleMiddleware;
use JEALER\G3\Services\CustomerService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class CustomerAdminController extends Controller {
    public function __construct(private CustomerService $service) {}

    #[RestRouter(namespace: 'api/v1/admin', route: 'customer/stream/session', methods: 'POST')]
    #[Middleware(RoleMiddleware::class, ['administrator'])]
    public function adminStreamSession(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $result = $this->service->createAdminStreamSession($request->get_json_params() ?: []);
        return is_wp_error($result) ? $result : $this->ok($result);
    }

    #[RestRouter(namespace: 'api/v1/admin', route: 'customer/conversations/list', methods: 'POST')]
    #[Middleware(RoleMiddleware::class, ['administrator'])]
    public function adminConversations(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $result = $this->service->listConversations($request->get_json_params() ?: []);
        return is_wp_error($result) ? $result : $this->ok($result);
    }

    #[RestRouter(namespace: 'api/v1/admin', route: 'customer/conversations/detail', methods: 'POST')]
    #[Middleware(RoleMiddleware::class, ['administrator'])]
    public function adminConversation(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data   = $request->get_json_params() ?: [];
        $result = $this->service->getConversationForViewer((int) ($data['conversation_id'] ?? 0));
        return is_wp_error($result) ? $result : $this->ok($result);
    }

    #[RestRouter(namespace: 'api/v1/admin', route: 'customer/conversations/messages/list', methods: 'POST')]
    #[Middleware(RoleMiddleware::class, ['administrator'])]
    public function adminMessages(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data   = $request->get_json_params() ?: [];
        $result = $this->service->messagesForViewer(
            (int) ($data['conversation_id'] ?? 0),
            max(0, (int) ($data['after_id'] ?? 0)),
            min(100, max(1, (int) ($data['limit'] ?? 50)))
        );

        return is_wp_error($result) ? $result : $this->ok($result);
    }

    #[RestRouter(namespace: 'api/v1/admin', route: 'customer/conversations/read', methods: 'POST')]
    #[Middleware(RoleMiddleware::class, ['administrator'])]
    public function adminRead(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data   = $request->get_json_params() ?: [];
        $result = $this->service->markRead((int) ($data['conversation_id'] ?? 0), (int) ($data['message_id'] ?? 0));
        return is_wp_error($result) ? $result : $this->ok($result);
    }

    #[RestRouter(namespace: 'api/v1/admin', route: 'customer/conversations/update', methods: 'POST')]
    #[Middleware(RoleMiddleware::class, ['administrator'])]
    #[Schema([
        'type'       => 'object',
        'required'   => ['conversation_id'],
        'properties' => [
            'conversation_id' => ['type' => 'integer'],
            'subject'         => ['type' => 'string', 'maxLength' => 255],
            'status'          => ['type' => 'string', 'enum' => ['pending', 'handling', 'closed']],
            'close_reason'    => ['type' => 'string', 'enum' => ['agent', 'timeout', 'system']],
        ],
    ])]
    public function adminUpdate(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data = $request->get_json_params() ?: [];
        $id   = (int) ($data['conversation_id'] ?? 0);
        unset($data['conversation_id']);
        $result = $this->service->updateConversation($id, $data);
        return is_wp_error($result) ? $result : $this->ok($result);
    }

    #[RestRouter(namespace: 'api/v1/admin', route: 'customer/conversations/messages/send', methods: 'POST')]
    #[Middleware(RoleMiddleware::class, ['administrator'])]
    #[Schema([
        'type'       => 'object',
        'required'   => ['conversation_id', 'content'],
        'properties' => [
            'conversation_id' => ['type' => 'integer'],
            'content'         => ['type' => 'string', 'minLength' => 1, 'maxLength' => 5000],
            'msg_type'        => ['type' => 'string', 'enum' => ['text']],
        ]
    ])]
    public function adminSend(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data   = $request->get_json_params() ?: [];
        $result = $this->service->sendAgentMessage((int) ($data['conversation_id'] ?? 0), $data);
        return is_wp_error($result) ? $result : $this->ok($result);
    }

    #[RestRouter(namespace: 'api/v1/admin', route: 'customer/conversations/profile', methods: 'POST')]
    #[Middleware(RoleMiddleware::class, ['administrator'])]
    public function adminProfile(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data   = $request->get_json_params() ?: [];
        $result = $this->service->customerProfile((int) ($data['conversation_id'] ?? 0));
        return is_wp_error($result) ? $result : $this->ok($result);
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
