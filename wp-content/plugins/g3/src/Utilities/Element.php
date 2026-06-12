<?php
namespace JEALER\G3\Utilities;
use JEALER\G3\Utilities\Frontend;

/**
 * Element utilities
 * 
 * 元素工具类
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
final class Element {

    /**
     * Generate a tab navigation
     * 
     * 生成标签导航
     *
     * @param string $componentName Component name
     * @param string $defaultTabName Default tab name
     * @param array $args Tab list ['key'=>'name']
     * @return void
     */
    public static function tab(string $componentName, string $defaultTabName, array $args = []): void
    {
        $current = $_REQUEST['tab'] ?? $defaultTabName;
        $header  = '<h2 class="nav-tab-wrapper">';
        foreach ($args as $tab_key => $tab_name) {
            $header .= \sprintf(
                '<a href="%s" class="nav-tab %s">%s</a>',
                add_query_arg(
                    [
                        'tab'     => $tab_key,
                        'sub-tab' => false
                    ]
                ),
                $tab_key == $current ? 'nav-tab-active' : '',
                $tab_name
            );
        }
        $header .= '</h2>';
        echo $header;
        if (isset($_REQUEST['sub-tab'])) {
            $current = $_REQUEST['sub-tab'];
        }
        $template_path = G3_COMPONENT_DIR . "/{$componentName}/views/tab-{$current}.php";
        if (file_exists($template_path)) {
            require_once $template_path;
        } else {
            echo '<div class="wrap">' . \sprintf(
                __('Template file does not exist: %s', 'G3'),
                $template_path
            ) . '</div>';
        }
    }

    /**
     * Generate admin setting option field: select
     * 
     * 生成设置选项字段：下拉选择
     * 
     * @param string $optionName Option name
     * @param mixed $optionValue Option value
     * @param string $keyName Field name
     * @param string $legend Field legend
     * @param string $description Field description
     * @param string $class Field class
     * @param array $options Option list ['key'=>'name']
     * @param bool $isArray Whether it is an array
     * @return string HTML
     */
    public static function select(
        string $optionName,
        mixed $optionValue,
        string $keyName,
        string $legend,
        string $description = '',
        string $class = '',
        array $options = [],
        bool $isArray = true
        // bool $needNone = false
    ): string
    {
        $html = '<fieldset>';

        if ($legend) {
            $html .= "<legend class='screen-reader-text'><span>$legend</span></legend>";
        }

        $value = $isArray ? ($optionValue[$keyName] ?? '') : esc_attr($optionValue);
        $id    = $isArray ? $keyName : $optionName;
        $name  = $isArray ? "{$optionName}[$keyName]" : $optionName;
        $title = __('Please Select', 'G3');

        $html .= "<label for='$id'><select id='$id' name='$name' class='$class' title='$title'>";

        if (empty($options)) {
            $options = [
                '0' => __('Disable', 'G3'),
                '1' => __('Enable', 'G3'),
            ];
        }
        foreach ($options as $key => $val) {
            $selected = ($value == $key) ? 'selected="selected"' : '';
            // If $needNone is true, add a none option, with value __('None', 'G3')
            // if ($needNone && $key == '') {
            //     $html .= sprintf('<option value="" %s>%s</option>', $selected, __('None', 'G3'));
            //     continue;
            // }

            $html .= \sprintf('<option value="%s" %s>%s</option>', $key, $selected, __($val, 'G3'));
        }

        $html .= "</select>";

        if (!empty($description)) {
            $html .= "<p class='description'>$description</p>";
        }

        $html .= '</label></fieldset>';

        return $html;
    }

    /**
     * Generate admin setting option field: enable (select)
     * 
     * 生成设置选项字段: enable (select 下拉选择)
     *
     * @param string $optionName Option name
     * @param string|array $optionValue Option value
     * @param string $keyName Field name
     * @param string $legend Field legend
     * @param string $description Field description
     * @param string $class Field class
     * @param array $options Option list ['key'=>'name']
     * @param bool $isArray Whether it is an array
     * @return string HTML
     */
    public static function enable($optionName, $optionValue, $keyName, $legend, $description = '', $class = '', $options = [], $isArray = true): string
    {
        return self::select($optionName, $optionValue, $keyName, $legend, $description, $class, $options, $isArray);
    }

    /**
     * Generate admin setting option field: input
     * 
     * 生成设置选项字段：input 输入框
     * 
     * @param string $optionName Option name
     * @param mixed $optionValue Option value
     * @param string $keyName Field name
     * @param string $legend Field legend
     * @param string $description Field description
     * @param string $inputType Input type
     * @param string $class Input class
     * @param bool $isArray Whether it is an array
     * @return string HTML
     */
    public static function input(
        string $optionName,
        mixed $optionValue,
        string $keyName,
        string $legend,
        string $description = '',
        string $inputType = 'text',
        string $class = '',
        bool $isArray = true
    ): string
    {
        $html = '<fieldset>';

        if ($legend) {
            $html .= "<legend class='screen-reader-text'><span>$legend</span></legend>";
        }

        $value = $isArray ? ($optionValue[$keyName] ?? '') : $optionValue;
        $id    = $isArray ? $keyName : $optionName;
        $name  = $isArray ? "{$optionName}[$keyName]" : $optionName;
        $title = __('Please Input the Field', 'G3');

        $html .= "<label for='$id'><input type='$inputType' id='$id' name='$name' value='$value' class='$class' title='$title' autocomplete='on' />";

        if ($description) {
            $html .= "<p class='description'>$description</p>";
        }

        $html .= '</label></fieldset>';

        return $html;
    }

    /**
     * Generate admin setting option field: input upload
     * 
     * 生成设置选项字段：input file 上传
     * 
     * @param string $optionName Option name
     * @param mixed $optionValue Option value
     * @param string $keyName Field name
     * @param string $legend Field legend
     * @param string $description Field description
     * @param string $class Input class
     * @param bool $isArray Whether it is an array
     * @return string HTML
     */
    public static function uploadInput(
        string $optionName,
        mixed $optionValue,
        string $keyKame,
        string $legend,
        string $description = '',
        string $class = '',
        bool $isArray = true
    ): string
    {
        wp_enqueue_media();
        wp_enqueue_script('media-grid');
        wp_enqueue_script('media');
        Frontend::loadScript('g3.media.upload');

        $html = '<fieldset>';

        if ($legend) {
            $html .= "<legend class='screen-reader-text'><span>$legend</span></legend>";
        }

        $value = $isArray ? ($optionValue[$keyKame] ?? '') : $optionValue;
        $id    = $isArray ? $keyKame : $optionName;
        $name  = $isArray ? "{$optionName}[$keyKame]" : $optionName;
        $class = "field-upload-url $class";
        $title = __('Please Input the Field', 'G3');

        $html .= "<label for='$id'><input type='text' id='$id' name='$name' value='$value' class='$class' title='$title' />";
        $html .= "<input type='button' class='button button-secondary field-upload-button' value='" . __('Upload', 'G3') . "' />";

        if ($description) {
            $html .= "<p class='description'>$description</p>";
        }

        $html .= '</label></fieldset>';

        return $html;
    }

    /**
     * Generate admin setting option field: input + image upload and preview
     * 
     * 生成设置选项字段：input file 上传和 image 预览
     * 
     * @param string $optionName Option name
     * @param mixed $optionValue Option value
     * @param string $keyName Field name
     * @param string $legend Field legend
     * @param string $description Field description
     * @param int $size Image size
     * @param string $class Input class
     * @param bool $isArray Whether it is an array
     * @param string $default Custom default value when customizing the form
     * @return string HTML
     */
    public static function imageInput(
        string $optionName,
        mixed $optionValue,
        string $keyName,
        string $legend,
        string $description = '',
        int $size = 120,
        string $class = '',
        bool $isArray = true,
        string $default = ''
    ): string
    {
        wp_enqueue_media();
        wp_enqueue_script('media-grid');
        wp_enqueue_script('media');
        Frontend::loadScript('g3.media.image');

        $html = "<fieldset>";
        if ($legend) {
            $html .= "<legend class='screen-reader-text'><span>$legend</span></legend>";
        }

        $value = $isArray ? ($default ? $default : $optionValue[$keyName] ?? '') : ($default ? $default : $optionValue);
        $id    = $isArray ? $keyName : $optionName;
        $name  = $isArray ? "{$optionName}[$keyName]" : $optionName;
        $class = "field-upload-url $class";
        $title = __('Please Input the Field', 'G3');

        $html .= "<label for='$id'><input type='text' id='$id' name='$name' value='$value' class='$class' title='$title' />";
        $html .= '<input type="button" class="button button-secondary field-upload-image-button" value="' . __('Upload', 'G3') . '" />';

        if ($description) {
            $html .= '<p class="description">' . $description . '</p>';
        }

        if ($value) {
            $html .= "<p class='description preview-wrap' style='position:relative;width:auto;height:{$size}px;overflow:hidden;'>";
            $html .= "<img class='preview-image' src='$value' style='width:auto;height:{$size}px;object-fit:cover;' />";
            // $html .= "<span class='clear-image' style='position:absolute;top:0;left:0;width:16px;height:16px;line-height:16px;text-align:center;background-color:#f00;color:#fff;cursor:pointer;'>×</span>";
            $html .= "</p>";
        }
        $html .= "</label></fieldset>";
        return $html;
    }

    /**
     * Generate admin setting option field: input counter
     * 
     * 生成设置选项字段：input counter 计数器
     * 
     * @param string $optionName Option name
     * @param mixed $optionValue Option value
     * @param string $keyName Field name
     * @param string $legend Field legend
     * @param string $text Counter text
     * @param string $description Field description
     * @param string $class Input class
     * @param array $counter Counter config
     * @param bool $isArray Whether it is an array
     * @return string HTML
     */
    public static function counterInput(
        string $optionName,
        mixed $optionValue,
        string $keyName,
        string $legend,
        string $text,
        string $description = '',
        string $class = '',
        array $counter = [
            'min'  => 0,
            'max'  => 100,
            'step' => 1,
        ],
        bool $isArray = true
    ): string
    {
        $html = '<fieldset>';

        if ($legend) {
            $html .= "<legend class='screen-reader-text'><span>$legend</span></legend>";
        }

        $value = $isArray ? ($optionValue[$keyName] ?? '') : $optionValue;
        $id    = $isArray ? $keyName : $optionName;
        $name  = $isArray ? "{$optionName}[$keyName]" : $optionName;
        $class = "field-upload-url $class";
        $title = __('Please Input the Field', 'G3');

        $html .= "<label for='$id'><input type='number' id='$id' name='$name' value='$value' class='$class' title='$title' min='{$counter['min']}' max='{$counter['max']}' step='{$counter['step']}' />";

        if ($text) {
            $html .= "<span class='counter-text' style='margin-left:4px'>$text</span>";
        }

        if ($description) {
            $html .= "<p class='description'>$description</p>";
        }
        $html .= '</label></fieldset>';
        return $html;
    }

    /**
     * Generate admin setting option field: textarea
     * 
     * 生成设置选项字段：textarea 文本域
     * 
     * @param string $optionName Option name
     * @param mixed $optionValue Option value
     * @param string $keyName Field name
     * @param string $legend Field legend
     * @param string $description Field description
     * @param string $class Input class
     * @param int $rows Textarea rows
     * @param int $cols Textarea cols
     * @param bool $isArray Whether it is an array
     * @return string HTML
     */
    public static function textarea(
        string $optionName,
        mixed $optionValue,
        string $keyName,
        string $legend = '',
        string $description = '',
        string $class = 'large-text code',
        int $rows = 5,
        int $cols = 50,
        bool $isArray = true
    ): string
    {
        $html = "<fieldset>";

        if ($legend) {
            $html .= "<legend class='screen-reader-text'><span>$legend</span></legend>";
        }

        $value = $isArray ? ($optionValue[$keyName] ?? '') : $optionValue;
        $id    = $isArray ? $keyName : $optionName;
        $name  = $isArray ? "{$optionName}[$keyName]" : $optionName;
        $title = __('Please Input the Field', 'G3');

        $html .= "<label for='$id'><textarea id='$id' name='$name' class='$class' rows='$rows' cols='$cols' title='$title'>";
        $html .= stripslashes($value);
        $html .= "</textarea>";

        if ($description) {
            $html .= "<p class='description'>$description</p>";
        }
        $html .= "</label></fieldset>";
        return $html;
    }

    /**
     * Generate admin setting option field: radio
     * 
     * 生成设置选项字段：radio 单选按钮
     * 
     * @param string $optionName Option name
     * @param mixed $optionValue Option value
     * @param string $keyName Field name
     * @param string $legend Field legend
     * @param string $description Field description
     * @param string $class Input class
     * @param array $options Options
     * @param bool $horizontal Whether to display horizontally
     * @param bool $isArray Whether it is an array
     * @return string HTML
     */
    public static function radio(
        string $optionName,
        mixed $optionValue,
        string $keyName,
        string $legend,
        string $description = '',
        string $class = '',
        array $options = [],
        bool $horizontal = true,
        bool $isArray = true
    ): string
    {
        $id = $isArray ? $keyName : $optionName;

        $html = "<fieldset id='$id'>";

        if ($legend) {
            $html .= "<legend class='screen-reader-text'><span>$legend</span></legend>";
        }

        $value = $isArray ? ($optionValue[$keyName] ?? '') : $optionValue;
        $name  = $isArray ? "{$optionName}[$keyName]" : $optionName;

        foreach ($options as $option_key => $option_label) {
            $checked      = checked($value, $option_key, false);
            $option_label = __($option_label, 'G3');

            if ($horizontal) {
                $html .= "<label class='radio-label-horizontal'>";
                $html .= "<input type='radio' id='$keyName-$option_key' name='$name' value='$option_key' class='$class' title='$option_label' $checked />";
                $html .= $option_label;
                $html .= '</label>';
            } else {
                $html .= "<p><label class='radio-label-vertical'><input type='radio' id='$optionName-$option_key' name='$name' value='$option_key' class='$class' title='$option_label' $checked />";
                $html .= $option_label;
                $html .= '</label></p>';
            }
        }

        if ($description) {
            $html .= "<p class='description'>$description</p>";
        }

        $html .= "</fieldset>";

        if ($horizontal) {
            $html .= "<style>.radio-label-horizontal:not(:last-of-type){margin-right:12px !important}</style>";
        }

        return $html;
    }

    /**
     * Generate admin setting option field: checkbox
     * 
     * 生成设置选项字段：checkbox 复选框
     * 
     * @param string $optionName Option name
     * @param mixed $optionValue Option value
     * @param string $keyName Field name
     * @param string $legend Field legend
     * @param string $description Field description
     * @param string $class Input class
     * @param array $options Options
     * @param bool $horizontal Whether to display horizontally
     * @param bool $isArray Whether option_name's value is an array
     * @return string HTML
     */
    public static function checkbox(
        string $optionName,
        mixed $optionValue,
        string $keyName,
        string $legend,
        string $description = '',
        string $class = '',
        array $options = [],
        bool $horizontal = true,
        bool $isArray = true
    ): string
    {
        $id = $isArray ? $keyName : $optionName;

        $html = "<fieldset id='$id'>";

        if ($legend) {
            $html .= "<legend class='screen-reader-text'><span>$legend</span></legend>";
        }

        $value = $isArray ? ($optionValue[$keyName] ?? '') : $optionValue;
        $value = $isArray ? (\is_array($value) ? $value : []) : explode(',', $value);
        $name  = $isArray ? "{$optionName}[$keyName]" : $optionName;
        $name  = $isArray ? "{$name}[]" : $name;

        foreach ($options as $option_key => $option_label) {
            $checked      = checked(in_array($option_key, $value), true, false);
            $option_label = __($option_label, 'G3');

            if ($horizontal) {
                $html .= "<label class='checkbox-label-horizontal'>";
                $html .= "<input type='checkbox' id='$keyName-$option_key' name='$name' value='$option_key' class='$class' title='$option_label' $checked />";
                $html .= $option_label;
                $html .= '</label>';
            } else {
                $html .= "<p><label class='checkbox-label-vertical'><input type='checkbox' id='$optionName-$option_key' name='$name' value='$option_key' class='$class' title='$option_label' $checked />";
                $html .= $option_label;
                $html .= '</label></p>';
            }
        }

        if ($description) {
            $html .= "<p class='description'>$description</p>";
        }

        $html .= "</fieldset>";

        if ($horizontal) {
            $html .= "<style>.checkbox-label-horizontal:not(:last-of-type){margin-right:12px !important}</style>";
        }

        return $html;
    }

    /**
     * Generate admin setting option field: switch
     * 
     * 生成设置选项字段：switch 开关
     * 
     * @param string $optionName Option name
     * @param mixed $optionValue Option value
     * @param string $keyName Field name
     * @param string $legend Field legend
     * @param string $description Field description
     * @param string $class Input class
     * @param bool $isArray Whether it is an array
     * @param string $size Switch size: sm|md
     * @return string HTML
     */
    public static function switch(
        string $optionName,
        mixed $optionValue,
        string $keyName,
        string $legend,
        string $description = '',
        string $class = '',
        string $size = 'md',
        bool $isArray = true,
    ): string
    {
        $id = $isArray ? $keyName : $optionName;

        $html = "<fieldset id='$id'>";

        if ($legend) {
            $html .= "<legend class='screen-reader-text'><span>$legend</span></legend>";
        }

        $value = $isArray ? ($optionValue[$keyName] ?? '') : $optionValue;
        $name  = $isArray ? "{$optionName}[$keyName]" : $optionName;

        // 确定开关尺寸
        $sizeClass = in_array($size, ['sm', 'md']) ? $size : 'sm';

        // 检查是否选中
        $isChecked = $value == '1' || $value == true || $value == 'on';

        $html .= "<input type='hidden' name='$name' value='0' />";
        $html .= "<label class='j-switch is-default is-$sizeClass $class'>";
        $html .= "<input type='checkbox' id='switch-$id' name='$name' value='1' " . checked($isChecked, true, false) . " />";
        $html .= '<span class="switch-slider"></span>';
        $html .= '</label>';

        if ($description) {
            $html .= "<p class='description'>$description</p>";
        }

        $html .= "</fieldset>";

        return $html;
    }

    /**
     * Register multiple Settings API fields at once
     * 
     * 生成多个设置选项字段
     *
     * @param string $page          Page slug
     * @param string $section       section slug
     * @param array  $fieldArgs     Array of field configurations [[],[]]
     *
     * Each field parameter format:
     * [
     *     'id'       => 'field_id',     // (required) string   Field ID
     *     'title'    => 'Field Title',  // (required) string   Field title
     *     'callback' => 'callback_fn',  // (optional) callback Rendering function, default __return_false
     *     'args'     => [],             // (optional) array    Additional arguments
     * ]
     * @return void
     */
    public static function settingFields(string $page, string $section, array $fieldArgs): void
    {
        if (empty($fieldArgs) || !\is_array($fieldArgs)) {
            return;
        }

        foreach ($fieldArgs as $field) {
            // Check required parameters, skip invalid fields
            if (
                !\is_array($field) ||
                empty($field['id']) ||
                empty($field['title'])
            ) {
                continue;
            }

            $id       = $field['id'] ?? '';
            $title    = $field['title'] ?? '';
            $callback = $field['callback'] ?? '__return_false';
            $args     = $field['args'] ?? [];

            if (!is_callable($callback)) {
                $callback = '__return_false';
            }

            add_settings_field(
                $id,
                $title,
                $callback,
                $page,
                $section,
                $args
            );
        }
    }

    /**
     * Generate a tip
     * 
     * 生成一个提示
     * 
     * @param string $message Tip message
     * @param string $type Tip type
     * @return string HTML
     */
    public static function tip(string $message, string|bool $title = '', string $type = 'default', string $className = ''): string
    {
        $type = match ($type) {
            'success' => 'success',
            'warning' => 'warning',
            'danger'  => 'danger',
            default   => 'default',
        };

        $titleString = '';

        if ($title !== false) {
            $finalTitle = '';
            if (is_string($title) && $title !== '') {
                $finalTitle = $title;
            } else {
                $finalTitle = __('Tip', 'G3');
            }

            if (!empty($finalTitle)) {
                $titleString = "<div class=\"tip-title\">{$finalTitle}</div>";
            }
        }

        $result = <<<HTML
<div class="j-tip is-{$type} {$className}">
    {$titleString}
    <div class="tip-content">
        {$message}
    </div>
</div>
HTML;
        return $result;
    }
}
