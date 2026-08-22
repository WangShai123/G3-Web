<?php
namespace JEALER\G3\Services;

use JEALER\G3\Core\Service\Service;

class TemplateService extends Service {
    public function singleTemplate($template)
    {
        if (!is_single()) {
            return $template;
        }
        $postType = get_post_type();
        $themeDir = get_stylesheet_directory();

        $typeTemplate = $themeDir . "/templates/post/{$postType}.php";
        if (file_exists($typeTemplate)) {
            return $typeTemplate;
        }

        $defaultTemplate = $themeDir . '/templates/post/index.php';
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
