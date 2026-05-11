<?php

namespace JEALER\G3\Attributes;

use Attribute;

/**
 * Aspect-Oriented Programming Attribute
 * 
 * @Annotation
 * @Target({TARGET_METHOD, TARGET_CLASS, TARGET_PROPERTY})
 * @Repeatable(Aspects::class)
 * @since 1.0.0
 * @author Wang Shai
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Aspects {

    /**
     * 切面类型
     * @var string $type method | property | construct | exception
     */
    public string $type;

    /**
     * 通知类型
     * @var string $advice before | after | after_throw | before_get | after_get | before_set | after_set | before_create | after_create
     */
    public string $advice;

    /**
     * 匹配目标（方法名/属性名，支持通配符）
     * @var string $target
     */
    public string $target;

    /**
     * 回调函数/闭包
     * @var callable $callback
     */
    public $callback;

    public function __construct(string $type, string $advice, string $target = '*', ?callable $callback = null)
    {
        $this->type     = $type;
        $this->advice   = $advice;
        $this->target   = $target;
        $this->callback = $callback;
    }
}
