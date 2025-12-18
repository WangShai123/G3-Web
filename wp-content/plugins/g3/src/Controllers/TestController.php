<?php
namespace JEALER\G3\Controllers;
use JEALER\G3\Attributes\RestRouter;
use JEALER\G3\Attributes\Middleware;
use JEALER\G3\Attributes\Schema;
use JEALER\G3\Middleware\RestAuthMiddleware;
use JEALER\G3\Middleware\RoleMiddleware;
use JEALER\G3\Middleware\RateLimitMiddleware;
use JEALER\G3\Utilities\Request;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class TestController {

    /**
     * 测试GET API - 获取基础信息
     * 访问：GET /wp-json/api/v1/test/info
     * 
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: 'test/info',
        methods: 'GET'
    )]
    public function getInfo(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        return rest_ensure_response([
            'code'         => 200,
            'message'      => 'G3 Test API is working! from G3-Web plugin.',
            'time'         => current_time('mysql'),
            'server_info'  => [
                'php_version'       => phpversion(),
                'wordpress_version' => get_bloginfo('version'),
                'plugin_version'    => G3_VERSION
            ],
            'request_info' => [
                'method'  => $request->get_method(),
                'params'  => $request->get_params(),
                'headers' => $request->get_headers()
            ]
        ]);
    }

    /**
     * 测试POST API - 创建数据
     * 访问：POST /wp-json/api/v1/test/create
     * 
     * @param WP_REST_Request $request
     * @return array
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: 'test/create',
        methods: 'POST'
    )]
    #[Schema([
        'type'       => 'object',
        'required'   => ['name', 'description'],
        'properties' => [
            'name'        => [
                'type'      => 'string',
                'minLength' => 1,
                'maxLength' => 100
            ],
            'description' => [
                'type'      => 'string',
                'maxLength' => 500
            ]
        ]
    ])]
    public function createData(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        // 获取POST数据
        $data = $request->get_json_params();

        // 数据净化
        $name        = sanitize_text_field($data['name']);
        $description = sanitize_textarea_field($data['description']);

        // 模拟创建数据（这里只是返回，实际应该保存到数据库）
        $created_data = [
            'id'          => wp_generate_uuid4(),
            'name'        => $name,
            'description' => $description,
            'created_at'  => current_time('mysql'),
            'created_by'  => get_current_user_id()
        ];

        return rest_ensure_response([
            'code'    => 200,
            'message' => 'Created',
            'data'    => $created_data
        ]);
    }

    /**
     * 测试PUT API - 更新数据
     * 访问：PUT /wp-json/api/v1/test/update
     * 
     * @param WP_REST_Request $request
     * @return array
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: 'test/update',
        methods: 'PUT'
    )]
    #[Schema([
        'type'                 => 'object',
        'required'             => ['id', 'name', 'description'],
        'properties'           => [
            'id'          => [
                'type'    => 'integer',
                'minimum' => 1,
                'maximum' => 99999
            ],
            'name'        => [
                'type'      => 'string',
                'minLength' => 1,
                'maxLength' => 100
            ],
            'description' => [
                'type'      => 'string',
                'minLength' => 1,
                'maxLength' => 500
            ]
        ],
        'additionalProperties' => false
    ])]
    public function updateData(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data = $request->get_json_params();
        $id   = $data['id'];

        // 模拟更新数据
        $updated_data = [
            'id'          => \intval($id),
            'name'        => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description']),
            'updated_at'  => current_time('mysql'),
            'updated_by'  => get_current_user_id()
        ];

        return rest_ensure_response([
            'code'    => 200,
            'message' => "ID {$id} updated",
            'data'    => $updated_data
        ]);
    }

    /**
     * 测试DELETE API - 删除数据
     * 访问：DELETE /wp-json/api/v1/test/delete
     * 
     * @param WP_REST_Request $request
     * @return array
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: '/test/delete',
        methods: 'DELETE'
    )]
    #[Schema([
        'type'                 => 'object',
        'required'             => ['id'],
        'properties'           => [
            'id' => [
                'type'    => 'integer',
                'minimum' => 1,
                'maximum' => 99999
            ]
        ],
        'additionalProperties' => false
    ])]
    public function deleteData(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data = $request->get_json_params();
        $id   = $data['id'] ?? null;

        // 模拟删除操作
        return rest_ensure_response([
            'status'     => 'success',
            'message'    => "ID {$id} deleted",
            'deleted_id' => \intval($id),
            'deleted_at' => current_time('mysql'),
            'deleted_by' => get_current_user_id()
        ]);
    }

    /**
     * 测试需要权限的API - 仅管理员可访问
     * 访问：GET /wp-json/api/v1/test/admin
     * 
     * @param WP_REST_Request $request
     * @return array
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: '/test/admin',
        methods: 'GET'
    )]
    #[Middleware(RoleMiddleware::class, ['administrator'])]
    public function admin(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $current_user = wp_get_current_user();

        return rest_ensure_response([
            'code'    => 200,
            'message' => 'Test Admin API',
            'data'    => [
                'id'           => $current_user->ID,
                'login'        => $current_user->user_login,
                'email'        => $current_user->user_email,
                'display_name' => $current_user->display_name,
                'roles'        => $current_user->roles,
                'access_time'  => current_time('mysql')
            ]
        ]);
    }

    /**
     * 测试需要登录的API - 使用中间件实现
     * 访问：GET /wp-json/api/v1/test/protected
     * 
     * @param WP_REST_Request $request
     * @return array
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: 'test/protected',
        methods: 'GET'
    )]
    #[Middleware(RestAuthMiddleware::class)]
    public function protectedEndpoint(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $current_user = wp_get_current_user();

        return rest_ensure_response([
            'code'    => 200,
            'message' => 'Auth Success',
            'data'    => [
                'id'           => $current_user->ID,
                'login'        => $current_user->user_login,
                'display_name' => $current_user->display_name
            ]
        ]);
    }

    /**
     * 测试限流API - 每分钟最多5次请求
     * 访问：GET /wp-json/api/v1/test/limited
     * 
     * @param WP_REST_Request $request
     * @return array
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: '/test/limited',
        methods: 'GET'
    )]
    #[Middleware(RateLimitMiddleware::class, [5, 60])]
    public function rateLimitedEndpoint(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        return rest_ensure_response([
            'code'    => 200,
            'message' => 'This is a rate-limited API, up to 5 requests per minute',
            'data'    => [
                'request_count' => Request::count($request),
                'timestamp'     => current_time('mysql')
            ],
        ]);
    }

    /**
     * 测试文件上传API
     * 访问：POST /wp-json/api/v1/test/upload
     * 
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: '/test/upload',
        methods: 'POST'
    )]
    public function uploadFile(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        // 获取上传的文件
        $files = $request->get_file_params();

        if (empty($files['file'])) {
            return new WP_Error(
                400,
                'No file uploaded.',
                [
                    'status' => 400,
                ]
            );
        }

        $file = $files['file'];

        // 验证文件类型（只允许图片）
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!\in_array($file['type'], $allowed_types)) {
            return new WP_Error(
                400,
                '只允许上传图片文件 (JPEG, PNG, GIF, WebP)',
                ['status' => 400]
            );
        }

        // 使用WordPress的文件上传处理
        require_once(ABSPATH . 'wp-admin/includes/file.php');

        $upload = wp_handle_upload($file, ['test_form' => false]);

        if (isset($upload['error'])) {
            return new WP_Error(
                500,
                $upload['error'],
                ['status' => 500]
            );
        }

        return rest_ensure_response([
            'status'    => 'success',
            'message'   => '文件上传成功',
            'file_info' => [
                'url'         => $upload['url'],
                'file'        => $upload['file'],
                'type'        => $upload['type'],
                'size'        => filesize($upload['file']),
                'uploaded_at' => current_time('mysql')
            ]
        ]);
    }

    /**
     * 测试批量操作API
     * 访问：POST /wp-json/api/v1/test/batch
     * 
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    #[RestRouter(
        namespace: 'api/v1',
        route: '/test/batch',
        methods: 'POST'
    )]
    public function batchOperation(WP_REST_Request $request): WP_Error|WP_REST_Response
    {
        $data = $request->get_json_params();

        if (!isset($data['operations']) || !is_array($data['operations'])) {
            return new WP_Error(
                400,
                'Please provide a valid operations array.',
                ['status' => 400]
            );
        }

        $results = [];

        foreach ($data['operations'] as $index => $operation) {
            if (!isset($operation['action']) || !isset($operation['data'])) {
                $results[] = [
                    'index'   => $index,
                    'code'    => 400,
                    'message' => 'Operation missing action or data field.'
                ];
                continue;
            }

            switch ($operation['action']) {
                case 'create':
                    $results[] = [
                        'index'  => $index,
                        'status' => 'success',
                        'action' => 'create',
                        'result' => [
                            'id'         => wp_generate_uuid4(),
                            'data'       => $operation['data'],
                            'created_at' => current_time('mysql')
                        ]
                    ];
                    break;

                case 'update':
                    $results[] = [
                        'index'  => $index,
                        'status' => 'success',
                        'action' => 'update',
                        'result' => [
                            'id'         => $operation['data']['id'] ?? 'unknown',
                            'data'       => $operation['data'],
                            'updated_at' => current_time('mysql')
                        ]
                    ];
                    break;

                case 'delete':
                    $results[] = [
                        'index'  => $index,
                        'status' => 'success',
                        'action' => 'delete',
                        'result' => [
                            'id'         => $operation['data']['id'] ?? 'unknown',
                            'deleted_at' => current_time('mysql')
                        ]
                    ];
                    break;

                default:
                    $results[] = [
                        'index'   => $index,
                        'status'  => 'error',
                        'message' => '不支持的操作: ' . $operation['action']
                    ];
            }
        }

        return rest_ensure_response([
            'code'      => 200,
            'message'   => 'Batch operation completed.',
            'processed' => \count($data['operations']),
            'results'   => $results
        ]);
    }
}