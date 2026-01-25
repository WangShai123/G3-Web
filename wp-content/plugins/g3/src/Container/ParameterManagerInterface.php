<?php
namespace JEALER\G3\Container;

/**
 * Parameter Manager Interface
 * 参数管理器接口
 * 
 * 负责管理容器参数，支持参数解析和环境变量
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
interface ParameterManagerInterface {
    /**
     * 设置参数
     * 
     * @param string $name 参数名
     * @param mixed $value 参数值
     * @return void
     */
    public function set(string $name, mixed $value): void;

    /**
     * 获取参数
     * 
     * @param string $name 参数名
     * @param mixed $default 默认值
     * @return mixed 参数值
     */
    public function get(string $name, mixed $default = null): mixed;

    /**
     * 检查参数是否存在
     * 
     * @param string $name 参数名
     * @return bool
     */
    public function has(string $name): bool;

    /**
     * 解析参数表达式
     * 
     * 支持：
     * - %parameter% - 普通参数
     * - %env(VAR_NAME)% - 环境变量
     * - %const(CONST_NAME)% - 常量
     * 
     * @param string $expression 参数表达式
     * @return mixed 解析后的值
     */
    public function resolve(string $expression): mixed;

    /**
     * 批量设置参数
     * 
     * @param array $parameters 参数数组
     * @return void
     */
    public function setParameters(array $parameters): void;

    /**
     * 获取所有参数
     * 
     * @return array
     */
    public function getParameters(): array;

    /**
     * 清除解析缓存
     * 
     * @return void
     */
    public function clearCache(): void;
}