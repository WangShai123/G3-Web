<?php
namespace JEALER\G3\Services;

class TemplateService {
    public function singleTemplate($template)
    {
        if (!is_single()) {
            return $template;
        }
        $postType = get_post_type();
        $themeDir = get_stylesheet_directory();

        $typeTemplate = $themeDir . "/templates/single/{$postType}.php";
        if (file_exists($typeTemplate)) {
            return $typeTemplate;
        }

        $defaultTemplate = $themeDir . '/templates/single/index.php';
        if (file_exists($defaultTemplate)) {
            return $defaultTemplate;
        }
        return $template;
    }
    public function categoryTemplate($template)
    {
        if (!is_category()) {
            return $template;
        }
        $category = get_queried_object();
        $themeDir = get_stylesheet_directory();

        $categoryTemplate = $themeDir . "/templates/category/{$category->slug}.php";
        if (file_exists($categoryTemplate)) {
            return $categoryTemplate;
        }

        $defaultTemplate = $themeDir . '/templates/category/index.php';
        if (file_exists($defaultTemplate)) {
            return $defaultTemplate;
        }
        return $template;
    }
}
