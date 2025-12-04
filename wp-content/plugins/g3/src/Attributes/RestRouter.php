<?php
namespace JEALER\G3\Attributes;
use Attribute;

/**
 * Rest Router Attribute
 * @Annotation
 * @Target({TARGET_METHOD})
 * @Repeatable(RestRouter::class)
 * @since 1.0.0
 * @author Wang Shai
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class RestRouter {
    /**
     * @param string $namespace  The namespace for the route, e.g. 'myplugin/v1'
     * @param string $route  The route path, e.g. '/books/(?P<id>\d+)'
     * @param string|array $methods  Single or multiple HTTP methods, e.g. 'GET' or ['GET', 'POST']
     */
    public function __construct(
        public string $namespace,
        public string $route,
        public string|array $methods = 'GET',
        // public ?string $name = null
    ) {
    }
}