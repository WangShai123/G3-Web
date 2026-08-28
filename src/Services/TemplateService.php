<?php
namespace JEALER\G3\Services;
use JEALER\G3\Core\Service\Service;

class TemplateService extends Service {

    public function singleTemplate(string $template, string $type, array $templates)
    {
        if (
            is_singular(['page', 'site-page'])
            || !is_single()
        ) return $template;

        $themeDir = get_stylesheet_directory();

        // special id template
        $postId = get_the_ID();
        if ($postId) {
            $specialIdTemplate = $themeDir . "/templates/post/post-{$postId}.php";
            if (file_exists($specialIdTemplate)) {
                return $specialIdTemplate;
            }
        }

        // fallback: post type template
        $postType     = get_post_type();
        $typeTemplate = $themeDir . "/templates/post/{$postType}.php";
        if (file_exists($typeTemplate)) return $typeTemplate;

        // fallback: default template
        $defaultTemplate = $themeDir . '/templates/post/index.php';
        if (file_exists($defaultTemplate)) return $defaultTemplate;

        return $template;
    }

    public function categoryTemplate(string $template, string $type, array $templates)
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

    public function notFoundTemplate(string $template, string $type, array $templates)
    {
        if (!is_404()) {
            return $template;
        }
        $themeDir = get_stylesheet_directory();

        $defaultTemplate = $themeDir . '/templates/404/index.php';
        if (file_exists($defaultTemplate)) {
            return $defaultTemplate;
        }
        return $template;
    }
}
