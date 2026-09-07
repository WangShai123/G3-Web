<?php
namespace JEALER\G3\Core\Router;
use JEALER\G3\Core\Container\Container;
use WP_REST_Response;

abstract class Controller {
    protected Container $container;

    public function __construct()
    {
        $this->container = Container::run();
    }

    protected function service(string $id): object
    {
        return $this->container->get($id);
    }

    protected function success(mixed $data, string $message = '', ?array $extra = null): WP_REST_Response
    {
        $res = [
            'success' => true,
            'code'    => 200,
            'message' => $message,
            'data'    => $data,
        ];

        if ($extra !== null) {
            $extra = array_diff_key($extra, $res);
            $res   = array_merge($res, $extra);
        }

        return rest_ensure_response($res);
    }
}
