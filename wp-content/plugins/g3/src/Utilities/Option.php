<?php
namespace JEALER\G3\Utilities;
final class Option {

    private static $notSetMarker;

    public function __construct()
    {
        self::notSet();
    }

    public static function notSet()
    {
        if (self::$notSetMarker === null) {
            self::$notSetMarker = new \stdClass();
        }
    }

    /**
     * Extract option value.
     * 
     * 提取选项值
     * 
     * @param mixed $data Option value
     * @return mixed Option value
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function extract($data)
    {
        return is_array($data) && count($data) === 1
            ? maybe_unserialize($data[0])
            : $data;
    }

    /**
     * Get option value. If the option does not exist, initialize it with the default value.
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
        $value = get_option($name);

        if (!$value) {
            add_option($name, $default, '', $autoload);
            return $default;
        }

        return $value;
    }

    /**
     * Update the option cache after submitting the form.
     * 
     * 更新后台表单选项缓存
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

    /**
     * Initialize the option value, use in component & setting.
     * 
     * 初始化选项值
     * 
     * @param string $name Option name
     * @param mixed $default Default option value.
     * @param bool $autoload
     * @return mixed Option Value
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function init(string $name, mixed $default = '', bool $autoload = false)
    {
        $value = self::get($name, $default, $autoload);
        return self::cache($name, $value);
    }
}