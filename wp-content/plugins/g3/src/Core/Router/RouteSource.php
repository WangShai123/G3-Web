<?php
namespace JEALER\G3\Core\Router;

class RouteSource {
    public function __construct(
        public readonly string $name,
        public readonly string $baseDir,
        public readonly string $baseNamespace
    ) {
    }

    public function exists(): bool
    {
        return is_dir($this->baseDir);
    }
}
