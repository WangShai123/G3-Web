<?php
namespace JEALER\G3\Controllers;
use JEALER\G3\Core\Attributes\Middleware;
use JEALER\G3\Core\Attributes\RestRouter;
use JEALER\G3\Core\Router\Controller;
use JEALER\G3\Middleware\RateLimitMiddleware;
use JEALER\G3\Services\SystemService;
use WP_REST_Request;
use WP_REST_Response;

class SystemController extends Controller {

    public function __construct()
    {
        parent::__construct();
    }

    #[RestRouter(namespace: 'api/config', route: 'v1/site', methods: 'GET')]
    #[Middleware(RateLimitMiddleware::class, [20, 60])]
    public function site(WP_REST_Request $request): WP_REST_Response
    {
        sleep(1);

        $result = [
            'name'         => get_bloginfo('name'),
            'desc'         => get_bloginfo('description'),
            'url'          => get_bloginfo('url'),
            'title'        => __('Safe Reminder', 'G3'),
            'redirectDesc' => __('You are about to leave this site, please note your account and security.', 'G3'),
            'warnTitle'    => __('Warning', 'G3') . ' - ' . get_bloginfo('name'),
            'warnDesc'     => __('The URL you visited is illegal.', 'G3'),
            'back'         => sprintf(__('%s %s', 'G3'), __('Back'), __('Homepage')),
            'continue'     => sprintf(__('%s %s', 'G3'), __('Continue'), __('Visiting', 'G3')),
            'whiteList'    => ['miit.gov.cn'],
        ];

        $icp = SystemService::icp();

        if (trim($icp) !== '') {
            $result['icp']    = $icp;
            $result['icpUrl'] = 'https://beian.miit.gov.cn/';
        }

        return $this->success($result);
    }
}
