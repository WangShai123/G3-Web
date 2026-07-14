<?php
namespace JEALER\G3\Core;
use JEALER\G3\Core\Helper\Helper;
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Core\Queue\CronSchedules;

/**
 * Loader
 * 
 * 加载器
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class Loader {

    public Container $container;

    public function __construct()
    {
        if (!isset($this->container)) {
            $this->container = Container::run();
        }
        $this->init();
    }

    private function init(): void
    {
        if (!$this->container->has('loader')) {
            $this->container->setRawDefinition('loader', Helper::class);
        }
        $manager = $this->container->getTagManager();
        if (!$manager->hasTag('loader', 'loader')) {
            $manager->tag('loader', 'loader', 'helper', 'raven');
        }
        $this->container->get('loader')->loader();
        CronSchedules::initIfNeeded();
    }
}
