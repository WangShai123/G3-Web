<?php

namespace JEALER\G3\Attributes;

use Attribute;

/**
 * Middleware Attribute
 * @Annotation
 * @Target({TARGET_METHOD})
 * @Repeatable(Middleware::class)
 * @since 1.0.0
 * @author Wang Shai
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Middleware {

    /**
     * @param string $middleware 中间件类名
     * @param array $params 中间件参数
     */
    public function __construct(
        public string $middleware,
        public array $params = []
    ) {
    }
}
