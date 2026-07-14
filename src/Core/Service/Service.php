<?php
namespace JEALER\G3\Core\Service;
use JEALER\G3\Core\Container\Container;
use wpdb;

abstract class Service {
    protected Container $container;
    protected wpdb      $wpdb;
    protected array     $cache     = [];
    public function __construct()
    {
        $this->container = Container::run();
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    public function cache(): array
    {
        return $this->cache;
    }
}
