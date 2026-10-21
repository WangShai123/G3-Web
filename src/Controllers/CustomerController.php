<?php
namespace JEALER\G3\Controllers;
use JEALER\G3\Core\Attributes\Middleware;
use JEALER\G3\Core\Attributes\RestRouter;
use JEALER\G3\Core\Attributes\Schema;
use JEALER\G3\Core\Router\Controller;
use JEALER\G3\Middleware\RateLimitMiddleware;
use JEALER\G3\Services\CustomerService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class CustomerController extends Controller {
    public function __construct(private CustomerService $service) {}

    #[RestRouter(namespace: 'api/v1', route: 'customer/config', methods: 'GET')]
    #[Middleware(RateLimitMiddleware::class, [60, 60])]
    public function config(): WP_REST_Response
    {
        return $this->ok($this->service->publicConfig());
    }

    #[RestRouter(namespace: 'api/v1', route: 'customer/stream/session', methods: 'POST')]
    #[Middleware(RateLimitMiddleware::class, [60, 60])]
    public function streamSession(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $result = $this->service->createViewerStreamSession($request->get_json_params() ?: []);
        return is_wp_error($result) ? $result : $this->ok($result);
    }

    #[RestRouter(namespace: 'api/v1', route: 'customer/conversations/start', methods: 'POST')]
    #[Middleware(RateLimitMiddleware::class, [60, 60])]
    #[Schema([
        'type'       => 'object',
        'properties' => [
            'subject'                 => ['type' => 'string', 'maxLength' => 255],
            'content'                 => ['type' => 'string', 'maxLength' => 5000],
            'source'                  => ['type' => 'string', 'maxLength' => 32],
            'meta'                    => ['type' => 'object'],
            'force_new'               => ['type' => 'boolean'],
            'exclude_conversation_id' => ['type' => 'integer'],
        ]
    ])]
    public function start(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $result = $this->service->startConversation($request->get_json_params() ?: []);
        return is_wp_error($result) ? $result : $this->ok($result);
    }

    #[RestRouter(namespace: 'api/v1', route: 'customer/conversations/detail', methods: 'POST')]
    #[Middleware(RateLimitMiddleware::class, [60, 60])]
    public function conversation(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data   = $request->get_json_params() ?: [];
        $result = $this->service->getConversationForViewer((int) ($data['conversation_id'] ?? 0));
        return is_wp_error($result) ? $result : $this->ok($result);
    }

    #[RestRouter(namespace: 'api/v1', route: 'customer/conversations/messages/list', methods: 'POST')]
    #[Middleware(RateLimitMiddleware::class, [120, 60])]
    public function messages(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data   = $request->get_json_params() ?: [];
        $result = $this->service->messagesForViewer(
            (int) ($data['conversation_id'] ?? 0),
            max(0, (int) ($data['after_id'] ?? 0)),
            min(100, max(1, (int) ($data['limit'] ?? 50)))
        );

        return is_wp_error($result) ? $result : $this->ok($result);
    }

    #[RestRouter(namespace: 'api/v1', route: 'customer/conversations/messages/send', methods: 'POST')]
    #[Middleware(RateLimitMiddleware::class, [40, 60])]
    #[Schema([
        'type'       => 'object',
        'required'   => ['conversation_id', 'content'],
        'properties' => [
            'conversation_id' => ['type' => 'integer'],
            'content'         => ['type' => 'string', 'minLength' => 1, 'maxLength' => 5000],
            'msg_type'        => ['type' => 'string', 'enum' => ['text']],
        ]
    ])]
    public function send(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data   = $request->get_json_params() ?: [];
        $result = $this->service->sendCustomerMessage((int) ($data['conversation_id'] ?? 0), $data);
        return is_wp_error($result) ? $result : $this->ok($result);
    }

    #[RestRouter(namespace: 'api/v1', route: 'customer/conversations/close', methods: 'POST')]
    #[Middleware(RateLimitMiddleware::class, [20, 60])]
    #[Schema([
        'type'       => 'object',
        'required'   => ['conversation_id'],
        'properties' => [
            'conversation_id' => ['type' => 'integer'],
            'close_reason'    => ['type' => 'string', 'enum' => ['customer', 'timeout']],
        ]
    ])]
    public function close(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data   = $request->get_json_params() ?: [];
        $result = $this->service->closeCustomerConversation(
            (int) ($data['conversation_id'] ?? 0),
            (string) ($data['close_reason'] ?? 'customer')
        );
        return is_wp_error($result) ? $result : $this->ok($result);
    }

    #[RestRouter(namespace: 'api/v1', route: 'customer/conversations/read', methods: 'POST')]
    #[Middleware(RateLimitMiddleware::class, [60, 60])]
    public function read(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data   = $request->get_json_params() ?: [];
        $result = $this->service->markRead((int) ($data['conversation_id'] ?? 0), (int) ($data['message_id'] ?? 0));
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
