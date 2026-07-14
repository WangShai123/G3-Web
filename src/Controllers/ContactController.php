<?php
namespace JEALER\G3\Controllers;
use JEALER\G3\Core\Attributes\Middleware;
use JEALER\G3\Core\Attributes\RestRouter;
use JEALER\G3\Core\Attributes\Schema;
use JEALER\G3\Core\Router\Controller;
use JEALER\G3\Jobs\EmailJob;
use JEALER\G3\Middleware\RateLimitMiddleware;
use JEALER\G3\Services\FormService;
use JEALER\G3\Services\MailerService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class ContactController extends Controller {
    public function __construct(private FormService $service) {}
    #[RestRouter(
        namespace: 'api/contact',
        route: 'v1/form',
        methods: 'POST'
    )]
    #[Middleware(RateLimitMiddleware::class, [6, 60])]
    #[Schema([
        'type'       => 'object',
        'required'   => ['title', 'content', 'email'],
        'properties' => [
            'title'   => [
                'type'      => 'string',
                'minLength' => 1,
                'maxLength' => 100
            ],
            'email'   => [
                'type'      => 'string',
                'format'    => 'email',
                'minLength' => 5,
                'maxLength' => 64
            ],
            'content' => [
                'type'      => 'string',
                'minLength' => 1,
                'maxLength' => 500
            ]
        ]
    ])]
    public function form(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data = $request->get_json_params();

        /** @var WP_Error|int $result */
        $result = $this->service->create($data);
        if (is_wp_error($result)) {
            return $result;
        }

        $formOption = get_option(FormService::FORM_OPTION_KEY, []);
        $notify     = is_array($formOption) ? ($formOption['email'] ?? '1') : '1';
        if ($notify === '1') {
            // @todo: 待改造完所有后台设置
            // EmailJob::send()
            // MailerService::send()
        }


        return rest_ensure_response([
            'success' => true,
            'code'    => 200,
            'message' => __('Your message has been sent successfully. We will get back to you shortly.', 'G3')
        ]);
    }
}
