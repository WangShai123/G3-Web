<?php

namespace JEALER\G3\Utilities;

use JEALER\G3\Utilities\Context;
use JEALER\G3\Utilities\Message;

/**
 * Option utilities class
 *
 * 选项工具类
 *
 * @since 1.0.0
 * @author Wang Shai
 */
final class Option {

    /**
     * Get option value. If the option does not exist, initialize it with the default value.
     * 
     * 获取选项值
     * 
     * @param string $key Option key
     * @param mixed $default Default option value.
     * @param bool $autoload Whether to load the option when the site is initialized.
     * @return mixed Option value
     */
    public static function get(string $key, mixed $default = '', bool $autoload = true): mixed
    {
        $value = Context::get($key);
        if ($value !== null) {
            return $value;
        }

        $value = get_option($key);
        if ($value !== false && $value !== null && $value !== '') {
            Context::set($key, $value);
            return $value;
        }

        add_option($key, $default, '', $autoload);
        Context::set($key, $default);
        return $default;
    }

    /**
     * Update the option cache after submitting the form.
     * 
     * 更新后台表单选项提交后的缓存
     * 
     * @param string $optionName Option name
     * @param mixed $optionValue Option value
     * @return mixed Option Value
     */
    public static function cache(string $optionName, mixed $optionValue)
    {
        if (array_key_exists($optionName, $_POST) && $_POST[$optionName]) {
            $result = update_option($optionName, $_POST[$optionName]);
            if ($result) {
                Context::set($optionName, $_POST[$optionName]);
                $optionValue = $_POST[$optionName];
                add_settings_error('notice', 'updated', Message::updated(), 'updated');
                settings_errors('notice');
            } else {
                Context::set($optionName, $_POST[$optionName]);
                $result = add_option($optionName, $_POST[$optionName]);
                if ($result) {
                    $optionValue = $_POST[$optionName];
                    add_settings_error('notice', 'updated', Message::updated(), 'updated');
                    settings_errors('notice');
                } else {
                    add_settings_error('notice', 'error', __('No data changed', 'G3'), 'error');
                    settings_errors('notice');
                }
            }

        }
        return $optionValue;
    }

    public static function update(string $key, $value)
    {
        Context::set($key, $value);
        return update_option($key, $value);
    }

    public static function set(string $key, $value, $autoload = true): bool
    {
        Context::set($key, $value);
        return add_option($key, $value, '', $autoload);
    }
}
