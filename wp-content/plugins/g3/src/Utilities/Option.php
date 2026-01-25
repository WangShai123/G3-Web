<?php
namespace JEALER\G3\Utilities;

use JEALER\G3\Utilities\Context;
use JEALER\G3\Utilities\Message;

final class Option {

    /**
     * Get option value. If the option does not exist, initialize it with the default value.
     * 
     * 获取选项值。
     * 注意：在G3-Web不允许数据库中存在空值
     * 
     * @param string $key Option key
     * @param mixed $default Default option value.
     * @param bool $autoload
     * @return mixed Option value
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function get(string $key, mixed $default = '', bool $autoload = false): mixed
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
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function cache(string $optionName, mixed $optionValue)
    {
        if (array_key_exists($optionName, $_POST) && $_POST[$optionName]) {
            error_log('submit data: ' . print_r($_POST[$optionName], true));
            $result = update_option($optionName, $_POST[$optionName]);
            error_log('update result: ' . print_r($result, true));
            if ($result) {
                error_log('update success');
                Context::set($optionName, $_POST[$optionName]);
                $optionValue = $_POST[$optionName];
                add_settings_error('notice', 'updated', Message::updated(), 'updated');
                settings_errors('notice');
            } else {
                add_settings_error('notice', 'error', __('No data changed', 'G3'), 'error');
                settings_errors('notice');
            }

        }
        return $optionValue;
    }

}