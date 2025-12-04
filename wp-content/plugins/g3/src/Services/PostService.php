<?php
namespace JEALER\G3\Services;
use JEALER\G3\Components;
use JEALER\G3\Services\PageService;
use WP_Post;
class PostService {
    public static string $seoTitleKey = 'g3_seo_title';
    public static string $seoKeywordsKey = 'g3_seo_keywords';
    public static string $seoDescriptionKey = 'g3_seo_description';

    /**
     * Get post meta data
     * 
     * 获取文章元数据
     * 
     * @param int|object $id postId / WP_Post
     * @param string $metaKey meta key
     * @param string $arrayKey array key if meta value is array
     * @param mixed $default default value if meta not exists
     * @return mixed meta value
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function getMeta(int|object $id, string $metaKey, string $arrayKey = '', mixed $default = null): mixed
    {
        if (\is_object($id)) {
            if ($id instanceof WP_Post) {
                $id = $id->ID;
            } else {
                return $default;
            }
        }

        $metaValue = get_post_meta($id, $metaKey, true);

        if (is_array($metaValue) && $arrayKey !== null && isset($metaValue[$arrayKey])) {
            return $metaValue[$arrayKey];
        }

        return ($metaValue !== '' && $metaValue !== null) ? $metaValue : $default;
    }

    /**
     * Get SEO Title
     * 
     * 获取 SEO 标题
     * 
     * Custom Filter: g3_filter_title
     * 
     * @return string
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function getTitle(): string
    {
        $title    = '';
        $siteName = get_bloginfo('name');
        $page     = get_query_var("paged");

        if (is_home() || is_front_page()) {
            $title = $siteName;
            if ($page > 1) {
                $title = sprintf(__('Page %s', 'G3'), $page) . ' - ' . $title;
            }
        } elseif (is_singular()) {
            $title = get_post_meta(get_queried_object_id(), self::$seoTitleKey, true);
            if (empty($title)) {
                $title = get_the_title();
            }
        } elseif (is_category() || is_tag() || is_tax()) {
            $title = single_term_title('', false);
        } elseif (is_archive()) {
            $title = get_the_archive_title();
        } elseif (is_search()) {
            $title = sprintf(__('Search results for %s', 'G3'), get_search_query());
        } elseif (is_author()) {
            $title = get_the_author_meta('nickname', get_queried_object_id());
        } elseif (is_date()) {
            $title = get_the_date();
        } elseif (is_404()) {
            $title = '404';
        } elseif (!is_404() && $page > 1) {
            $afterTitle  = ' ' . sprintf(__('Page %s', 'G3'), $page);
            $title      .= $afterTitle;
        }

        /**
         * Custom Filter: g3_filter_title
         * @param  string $title The post title.
         * @return string The filtered post title.
         */
        $title = apply_filters('g3_filter_title', $title);

        return (is_home() || is_front_page()) ? $title : $title . ' - ' . $siteName;
    }

    /**
     * Get Excerpt
     * 
     * 获取文章摘要
     * 
     * @param  int $maxLength The max length of excerpt.
     * @param  object $post The post object.
     * @return string
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function getExcerpt(int $maxLength = 150, $post = null): string
    {
        $currentPost = ($post instanceof WP_Post && $post->ID) ? $post : get_post();
        $excerpt     = $currentPost->post_excerpt;
        if (empty($excerpt)) {
            $excerpt = wp_strip_all_tags($currentPost->post_content);
            $excerpt = mb_strimwidth($excerpt, 0, $maxLength, "...");
        }
        return $excerpt;
    }

    /**
     * Get SEO Description
     * 
     * 获取 SEO 文章描述
     * 
     * Custom Filter: g3_filter_description
     * 
     * @return string
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function getDescription(): string
    {
        $description = get_bloginfo('description');

        if (is_singular()) {
            $description = get_post_meta(get_queried_object_id(), self::$seoDescriptionKey, true);
            if (empty($description)) {
                $description = self::getExcerpt();
            }
        } elseif (is_category() || is_tag() || is_tax()) {
            $description = strip_tags(term_description(get_queried_object_id()));
        } elseif (is_archive()) {
            $description = get_the_archive_description();
        } elseif (is_search()) {
            $description = sprintf(__('Search results for %s', 'G3'), get_search_query());
        } elseif (is_author() || PageService::isUser()) {
            $description = get_the_author_meta('description', get_queried_object_id());
        } elseif (is_404()) {
            $description = '404';
        }

        /**
         * Custom Filter: g3_filter_description
         * @param  string $description The post description.
         * @return string
         */
        $description = trim(apply_filters('g3_filter_description', $description));

        return $description;
    }

    /**
     * Get SEO Keywords
     * 
     * 获取 SEO 关键词
     * 
     * Custom Filter: g3_filter_keywords
     * 
     * @return string
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function getKeywords(): string
    {
        $keywords = '';
        $siteName = get_bloginfo('name');

        if (is_home() || is_front_page()) {
            $keywords = Components::getProperty('DigitalOperations', 'seo')['keywords'] ?? '';
            $keywords = !empty($keywords) ? $keywords : $siteName;
        } elseif (is_singular()) {
            $keywords = get_post_meta(get_the_ID(), self::$seoKeywordsKey, true);
            if (empty($keywords)) {
                // get post_tag if no data in self::$seoKeywordsKey
                $terms = get_the_terms(get_the_ID(), 'post_tag');
                if (!empty($terms)) {
                    $keywords = array_column($terms, 'name');
                    $keywords = implode(', ', $keywords);
                }
            }
        } elseif (is_category() || is_tag() || is_tax()) {
            $keywords = self::getTermKeywords(get_queried_object_id());
        } elseif (is_search()) {
            $keywords = sprintf(__('Search results for %s', 'G3'), get_search_query());
        } elseif (is_author()) {
            $keywords = get_the_author_meta('display_name');
        } elseif (is_archive()) {
            $keywords = get_the_archive_title();
        } elseif (is_404()) {
            $keywords = '404';
        } elseif (PageService::isUser()) {
            global $wp_query;
            $keywords = $wp_query->query_vars['g3_user']->data->display_name . ',' . $siteName;
        } elseif (PageService::isMy()) {
            $keywords = __('My', 'G3') . ',' . $siteName;
        } elseif (PageService::isLogin()) {
            $keywords = __('Login', 'G3') . ',' . $siteName;
        } elseif (PageService::isRegister()) {
            $keywords = __('Register', 'G3') . ',' . $siteName;
        } elseif (PageService::isLostPassword()) {
            $keywords = __('Post Password', 'G3') . ',' . $siteName;
        } elseif (PageService::isResetPassword()) {
            $keywords = __('Reset Password', 'G3') . ',' . $siteName;
        }

        /**
         * Custom Filter: g3_filter_keywords
         */
        $keywords = apply_filters('g3_filter_keywords', $keywords);

        return $keywords;
    }

    /**
     * Get Term Keywords
     * 
     * 获取分类关键词
     * 
     * @param  int $termId The term id.
     * @return string
     * @since 1.0.0
     * @author Wang Shai
     */
    public static function getTermKeywords(int $termId): string
    {
        return get_term_meta($termId, self::$seoKeywordsKey, true);
    }
}