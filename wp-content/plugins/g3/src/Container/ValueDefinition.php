<?php

namespace JEALER\G3\Container;

use Psr\Container\ContainerInterface;

class ValueDefinition implements DefinitionInterface {

    public function __construct(private mixed $value)
    {
    }

    public function resolve(ContainerInterface $container): mixed
    {
        return $this->value;
    }
}
