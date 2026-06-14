<?php
namespace JEALER\G3\Core\Container;
use Psr\Container\ContainerInterface;

interface DefinitionInterface {
    public function resolve(ContainerInterface $container): mixed;
}
