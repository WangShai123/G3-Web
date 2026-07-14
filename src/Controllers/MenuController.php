<?php
namespace JEALER\G3\Controllers;
use JEALER\G3\Core\Attributes\RestRouter;
use JEALER\G3\Core\Attributes\Middleware;
use JEALER\G3\Core\Attributes\Schema;
use JEALER\G3\Core\Router\Controller;
use JEALER\G3\Middleware\RateLimitMiddleware;
use JEALER\G3\Services\MenuService;
use JEALER\G3\Utilities\Request;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class MenuController extends Controller {

    public function __construct(
        private MenuService $menuService,
    )
    {
    }

    /**
     * Get menu items by location.
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     * @since 1.0.0
     * @author Wang Shai
     */
    #[RestRouter(
        namespace: 'api/menu',
        route: 'v1/query',
        methods: 'POST'
    )]
    #[Schema([
        'type'       => 'object',
        'required'   => ['location'],
        'properties' => [
            'location' => [
                'type'      => 'string',
                'minLength' => 1,
            ]
        ]
    ])]
    #[Middleware(RateLimitMiddleware::class, [20, 60])]
    public function handler(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $params = $request->get_json_params();

        $location = $params['location'] ?? '';
        $cacheKey = $location;

        $cache = wp_cache_get($cacheKey, MenuService::MENU_JSON_CACHE_GROUP);
        if ($cache) {
            return rest_ensure_response([
                'success' => true,
                'code'    => 200,
                'data'    => $cache,
            ]);
        }

        $result = $this->menuService->getJson($location);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response([
            'success' => true,
            'code'    => 200,
            'data'    => $result,
        ]);
    }
}
