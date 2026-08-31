<?php
namespace JEALER\G3\Controllers;

use JEALER\G3\Core\Attributes\Middleware;
use JEALER\G3\Core\Attributes\RestRouter;
use JEALER\G3\Core\Router\Controller;
use JEALER\G3\Middleware\RoleMiddleware;
use JEALER\G3\Services\AuthService;
use JEALER\G3\Utilities\Message;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class TableListController extends Controller {
    public function __construct(private AuthService $auth)
    {
        parent::__construct();
    }

    #[RestRouter(namespace: 'api/admin/table-list', route: 'v1/invitation-codes', methods: 'POST')]
    #[Middleware(RoleMiddleware::class, ['administrator'])]
    public function invitationCodes(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        if (!$this->auth->invitationCodeEnabled()) {
            return new WP_Error('g3_invitation_code_disabled', __('Invitation code feature is not available.', 'G3'), ['status' => 403]);
        }

        return $this->ok($this->auth->invitationCodeTableList($this->payload($request)));
    }

    #[RestRouter(namespace: 'api/admin/table-list', route: 'v1/invitation-codes/generate', methods: 'POST')]
    #[Middleware(RoleMiddleware::class, ['administrator'])]
    public function generateInvitationCodes(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data   = $this->payload($request);
        $amount = (int) ($data['amount'] ?? 1);

        if (!$this->auth->invitationCodeEnabled()) {
            return new WP_Error('g3_invitation_code_disabled', __('Invitation code feature is not available.', 'G3'), ['status' => 403]);
        }

        if ($amount < 1 || $amount > 20) {
            return new WP_Error(
                'g3_invalid_invitation_code_amount',
                __('Failed. It is recommended to generate 1-20 at a time.', 'G3'),
                ['status' => 400]
            );
        }

        $codes     = [];
        $failCount = 0;
        for ($i = 0; $i < $amount; $i++) {
            $code = $this->auth->generateInviteCode(true);
            if ($code !== false) {
                $codes[] = $code;
            } else {
                $failCount++;
            }
        }

        if (!$codes) {
            return new WP_Error('g3_invitation_code_generate_failed', __('Failed', 'G3'), ['status' => 500]);
        }

        return $this->ok([
            'message'      => Message::generated() . ': ' . count($codes) . ' ' . __('Invitation Code', 'G3'),
            'codes'        => $codes,
            'total'        => $amount,
            'successCount' => count($codes),
            'failCount'    => $failCount,
        ]);
    }

    #[RestRouter(namespace: 'api/admin/table-list', route: 'v1/invitation-codes/delete', methods: 'POST')]
    #[Middleware(RoleMiddleware::class, ['administrator'])]
    public function deleteInvitationCode(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data = $this->payload($request);
        $id   = (int) ($data['id'] ?? 0);

        if (!$this->auth->invitationCodeEnabled()) {
            return new WP_Error('g3_invitation_code_disabled', __('Invitation code feature is not available.', 'G3'), ['status' => 403]);
        }

        if ($id <= 0) {
            return new WP_Error('g3_invalid_invitation_code_id', __('Illegal request', 'G3'), ['status' => 400]);
        }

        $result = $this->auth->deleteInviteCode($id);
        if ($result === false) {
            return new WP_Error('g3_invitation_code_delete_failed', __('Failed', 'G3'), ['status' => 500]);
        }

        return $this->ok([
            'id'      => $id,
            'message' => __('Deleted', 'G3'),
        ]);
    }

    #[RestRouter(namespace: 'api/admin/table-list', route: 'v1/invitation-codes/bulk-delete', methods: 'POST')]
    #[Middleware(RoleMiddleware::class, ['administrator'])]
    public function bulkDeleteInvitationCodes(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data = $this->payload($request);
        $ids  = is_array($data['ids'] ?? null) ? $data['ids'] : [];

        if (!$this->auth->invitationCodeEnabled()) {
            return new WP_Error('g3_invitation_code_disabled', __('Invitation code feature is not available.', 'G3'), ['status' => 403]);
        }

        if (!$ids) {
            return new WP_Error('g3_empty_invitation_code_ids', __('Illegal request', 'G3'), ['status' => 400]);
        }

        return $this->ok($this->auth->deleteInviteCodes($ids));
    }

    private function payload(WP_REST_Request $request): array
    {
        $data = $request->get_json_params();
        return is_array($data) ? $data : [];
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
