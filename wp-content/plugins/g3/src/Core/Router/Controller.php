<?php
namespace JEALER\G3\Core\Router;
use JEALER\G3\Core\Container\Container;

abstract class Controller {
    protected Container $container;

    public function __construct(?Container $container = null)
    {
        $this->container = $container ?: Container::run();
    }

    protected function service(string $id): object
    {
        return $this->container->get($id);
    }
}
