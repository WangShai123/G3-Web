<?php
namespace JEALER\G3;

use JEALER\G3\Helper\Helper;
use JEALER\G3\Container\Container;
use JEALER\G3\Queue\CronSchedules;
use JEALER\G3\Utilities\Context;

class Loader {
    public ?Container $container = null;
    public function __construct()
    {
        if ($this->container === null) {
            $this->container = Container::run();
        }
        $this->init();
    }
    private function init(): void
    {
        if (!$this->container->has('loader')) {
            $this->container->setRawDefinition('loader', Helper::class);
            $this->container->get('loader')->loader();
        } else {
            $this->container->get('loader')->loader();
        }
        CronSchedules::initIfNeeded();
    }
}
