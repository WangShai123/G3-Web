<?php
namespace JEALER\G3\Utilities;
final class Option {

    /**
     * Initialize option value in component & update form cache.
     * 
     * 初始化组件选项值并更新表单缓存。
     * 
     * @param mixed $optionValue Option value.
     * @param string $optionName Option name.
     * @param mixed $default Default option value.
     * @return mixed Option value.
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function init(mixed $optionValue, string $optionName, mixed $default = ''): mixed
    {
        if (empty($optionValue)) {
            $optionValue = self::get($optionName, $default);
        }
        return self::updateOptionCache($optionName, $optionValue);
    }

    /**
     * Update option cache.
     * 
     * After submitting the form, update the option cache with the new value.
     * 
     * 更新选项缓存
     * 
     * @param string $option_name 选项名称
     * @param mixed $option_value 选项值
     * @return mixed 选项值
     * @since 1.0.0
     * @author Wang Shai
     */
    private static function updateOptionCache(string $option_name, mixed $option_value): mixed
    {
        if (array_key_exists($option_name, $_POST) && $_POST[$option_name]) {
            $option_value = $_POST[$option_name];
        }
        return $option_value;
    }

    /**
     * Get option value.
     * 
     * If the option does not exist, initialize it with the default value.
     * 
     * 获取选项值
     * 
     * @param string $name Option name
     * @param mixed $default Default option value.
     * @param bool $autoload
     * @return mixed Option value
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function get(string $name, mixed $default = '', bool $autoload = false): mixed
    {
        $cache = get_option($name, null);

        if ($cache === null || $cache === '' || $cache === []) {

            // only add option if it doesn't exist
            if (!add_option($name, $default, '', $autoload)) {

                // if add_option failed, try update_option
                update_option($name, $default);
            }

            return $default;
        }

        return $cache;
    }

    /**
     * Update the option cache after submitting the form.
     * 
     * 更新选项缓存
     * 
     * @param string $optionName Option name
     * @param mixed $optionValue Option value
     * @return mixed Option Value
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function cache(string $optionName, mixed $optionValue)
    {
        if (array_key_exists($optionName, $_POST) && $_POST[$optionName]) {
            $optionValue = $_POST[$optionName];
        }
        return $optionValue;
    }

    public static function array(string $optionName, array $default = []): array
    {
        $value = get_option($optionName, $default);
        if (empty($value) || !is_array($value)) {
            $value = $default;
        }
        return $value;
    }

}