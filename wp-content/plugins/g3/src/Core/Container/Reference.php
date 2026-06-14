<?php
namespace JEALER\G3\Core\Container;
use Psr\Container\ContainerInterface;

class Reference implements DefinitionInterface {
    public function __construct(private string $id)
    {
    }
    public function getId(): string
    {
        return $this->id;
    }
    public function resolve(ContainerInterface $container): mixed
    {
        return $container->get($this->id);
    }
}
