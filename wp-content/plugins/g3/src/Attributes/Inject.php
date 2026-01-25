<?php
namespace JEALER\G3\Attributes;

use Attribute;

/**
 * 用于构造函数参数，指定要注入的服务
 * - 无参：按类型自动注入（如 #[Inject] LoggerService $logger）
 * - 有参：按服务 ID 注入（如 #[Inject('main_logger')] LoggerService $logger）
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Inject {
    public function __construct(
        public ?string $value = null
    ) {
    }
}