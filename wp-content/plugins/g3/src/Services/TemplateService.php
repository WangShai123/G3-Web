<?php
namespace JEALER\G3\Services;

class TemplateService {
    public function singleTemplate($template)
    {
        if (!is_single()) {
            return $template;
        }
        $postType = get_post_type();
        $themeDir = get_template_directory();
        // try post type template
        $type_template = $themeDir . "/single/{$postType}.php";
        if (file_exists($type_template)) {
            return $type_template;
        }
        // try default template
        $default_template = $themeDir . '/single/default.php';
        if (file_exists($default_template)) {
            return $default_template;
        }
        // fallback
        return $template;
    }
}