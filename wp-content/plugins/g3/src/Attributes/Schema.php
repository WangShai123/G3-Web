<?php
namespace JEALER\G3\Attributes;
use Attribute;

/**
 * JSON Schema Attribute
 * @Annotation
 * @Target({TARGET_METHOD})
 * @Repeatable(Schema::class)
 * @since 1.0.0
 * @author Wang Shai
 * 
 * Example:
 * #[Schema(type:"object", properties:[ ... ], required:["title"])]
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Schema {
    /**
     * @param array $schema The JSON Schema properties
     */
    public function __construct(
        public array $schema = []
    ) {
    }
}