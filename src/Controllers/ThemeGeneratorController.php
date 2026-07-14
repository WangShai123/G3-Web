<?php

namespace JEALER\G3\Controllers;

use JEALER\G3\Core\Container\Container;
use JEALER\G3\Core\Router\Controller;
use JEALER\G3\Service;
use JEALER\G3\Core\Attributes\RestRouter;
use JEALER\G3\Core\Attributes\Middleware;
use JEALER\G3\Core\Attributes\Schema;
use JEALER\G3\Middleware\RoleMiddleware;
use JEALER\G3\Middleware\RateLimitMiddleware;
use JEALER\G3\Services\ThemeGeneratorService;
use JEALER\G3\Utilities\Message;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class ThemeGeneratorController extends Controller {
    public function __construct(private ThemeGeneratorService $service) {}

    /**
     * Generate a new theme.
     * 
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     * @since 1.0.0
     * @author Wang Shai
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: 'theme/generate',
        methods: 'POST'
    )]
    #[Schema([
        'type'       => 'object',
        'required'   => ['name', 'folder', 'url', 'description', 'author', 'authorUrl', 'version'],
        'properties' => [
            'name'        => [
                'type'      => 'string',
                'minLength' => 2,
                'maxLength' => 32
            ],
            'folder'      => [
                'type'      => 'string',
                'minLength' => 2,
                'maxLength' => 16
            ],
            'url'         => [
                'type'      => 'string',
                'minLength' => 10,
                'maxLength' => 256
            ],
            'description' => [
                'type'      => 'string',
                'minLength' => 6,
                'maxLength' => 256
            ],
            'author'      => [
                'type'      => 'string',
                'minLength' => 2,
                'maxLength' => 16
            ],
            'authorUrl'   => [
                'type'      => 'string',
                'minLength' => 10,
                'maxLength' => 256
            ],
            'version'     => [
                'type'      => 'string',
                'minLength' => 3,
                'maxLength' => 16
            ]
        ]
    ])]
    #[Middleware(RoleMiddleware::class, ['administrator'])]
    #[Middleware(RateLimitMiddleware::class, [10, 60])]
    public function generate(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $params = $request->get_json_params();

        // Sanitize folder name
        $params['folder'] = sanitize_title($params['folder']);

        $themeBasePath = WP_CONTENT_DIR . '/themes';
        $themePath     = $themeBasePath . '/' . $params['folder'];

        if (file_exists($themePath)) {
            return new WP_Error(
                '400',
                sprintf(__('Folder exists: %s', 'G3'), $themePath),
                [
                    'status' => 400
                ]
            );
        }

        $this->service->create($params);

        return rest_ensure_response([
            'code'    => 200,
            'message' => Message::generated(),
            'data'    => [
                'name' => $request->get_param('name'),
                'path' => WP_CONTENT_DIR . '/themes/' . $request->get_param('folder')
            ]
        ]);
    }
}
