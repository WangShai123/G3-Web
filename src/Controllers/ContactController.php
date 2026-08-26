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
use JEALER\G3\Utilities\Date;
use JEALER\G3\Utilities\System;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class ContactController extends Controller {
    private FormService   $formService;
    private MailerService $mailerService;
    public function __construct()
    {
        parent::__construct();
        $this->formService   = $this->service(FormService::class);
        $this->mailerService = $this->service(MailerService::class);
    }
    #[RestRouter(
        namespace: 'api/contact',
        route: 'v1/form',
        methods: 'POST'
    )]
    #[Middleware(RateLimitMiddleware::class, [10, 60])]
    #[Schema([
        'type'       => 'object',
        'required'   => ['name', 'content', 'email'],
        'properties' => [
            'name'    => [
                'type'      => 'string',
                'minLength' => 1,
                'maxLength' => 128
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

        $result = $this->formService->create($data);
        if (is_wp_error($result)) {
            return $result;
        }

        $formOption = get_option(FormService::FORM_OPTION_KEY, []);
        $notify     = $formOption['email'] ?? '0';
        if ($notify === '1') {
            $contactH3 = __('User Profile', 'G3');
            $name      = __('Name') . ': ' . $data['name'];
            $contentH3 = __('Details') . ': ';
            $content   = $data['content'];
            $ext       = $data['ext'] ?? [];
            $strExt    = __('Metadata') . ': ' . json_encode($ext);
            $dateTime  = __('Time') . ': ' . Date::dateTime(time());
            $ip        = 'IP: ' . System::ip();
            $siteUrl   = __('Site') . ': ' . get_bloginfo('url');

            $msg = <<<HTML
<h3>{$contentH3}</h3>
<p>{$content}</p>
<h3>{$contactH3}:</h3>
<ul>
    <li>{$name}</li>
    <li>Email: {$data['email']}</li>
    <li>{$strExt}</li>
    <li>{$ip}</li>
    <li>{$dateTime}</li>
</ul>
<p>{$siteUrl}</p>
HTML;

            $email   = get_option('admin_email');
            $subject = __('New Message', 'G3') . ' - ' . $data['name'];

            $this->mailerService->sendMail($email, $subject, $msg);
        }

        return rest_ensure_response([
            'success' => true,
            'code'    => 200,
            'message' => __('Your message has been sent successfully. We will get back to you shortly.', 'G3')
        ]);
    }
}
