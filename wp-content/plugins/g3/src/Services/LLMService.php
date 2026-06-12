<?php
namespace JEALER\G3\Services;
use JEALER\G3\Utilities\Context;
use JEALER\G3\Utilities\Date;

class LLMService {
    private string $fileName     = 'llms.txt';
    private int    $postsPerType;
    public function __construct()
    {
        $this->postsPerType = Context::get(SystemService::LLM_OPTION_KEY)['postsPerType'] ?? 2000;
        error_log('postsPerType type: ' . gettype($this->postsPerType));
        error_log("LLMService initialized with postsPerType: " . $this->postsPerType);
    }

    public function handleRequest()
    {
        if (get_query_var('g3_var_llm') === 'endpoint') {
            header('Content-Type: text/plain; charset=utf-8');
            $llms_txt = $this->generateLLMsTxt();
            $test     = $this->saveLLMsTxt($llms_txt);
            echo $llms_txt;
            exit;
        }
        wp_redirect(home_url('404'));
    }

    private function saveLLMsTxt($content)
    {
        $file_path = ABSPATH . $this->fileName;
        $bom       = "\xEF\xBB\xBF";
        return file_put_contents($file_path, $bom . $content);
    }

    /**
     * Generate llms.txt content, supporting all public post types and including metadata for each item.
     */
    private function generateLLMsTxt()
    {
        $site_name = get_bloginfo('name');
        $site_desc = get_bloginfo('description');
        $site_url  = home_url();

        // 1. Generate file metadata in the header
        $output  = "# $site_name\n";
        $output .= "> $site_desc\n\n";
        $output .= "Base URL: $site_url\n";
        $output .= "LLMs Txt URL: $site_url/" . $this->fileName . "\n";
        $output .= "Generated: " . Date::dateTime(time()) . "\n";

        // 2. Get all public post types, excluding attachments
        $post_types = get_post_types([
            'public'   => true,
            '_builtin' => false // 先获取自定义的
        ], 'names');

        // include post and page
        $post_types[] = 'post';
        $post_types[] = 'page';

        // 3. Iterate through all post types and fetch the latest 100 items for each type (to prevent the file from being too large, adjust as needed)
        foreach ($post_types as $type) {
            $type_obj = get_post_type_object($type);
            $posts    = get_posts([
                'post_type'      => $type,
                'posts_per_page' => $this->postsPerType,
                'post_status'    => 'publish',
                'orderby'        => 'modified',
                'order'          => 'DESC',
            ]);

            if (!empty($posts)) {
                $output .= "\n## {$type_obj->labels->name} ({$type})\n\n";
                foreach ($posts as $post) {
                    setup_postdata($post);
                    $title = $post->post_title;
                    $url   = get_permalink($post);
                    // $date  = get_the_modified_date('Y-m-d', $post);

                    // excerpt > content( 150 chars )
                    // $excerpt = has_excerpt($post) ? wp_strip_all_tags($post->post_excerpt) : wp_trim_words(wp_strip_all_tags($post->post_content), 30);

                    // $output .= "- **[$title]**($url)\n";
                    $output .= "- [$title]($url)\n";
                    // $output .= "  - Updated: $date\n";
                    // $output .= "  - $excerpt\n\n";
                }
                wp_reset_postdata();
            }
        }

        return $output;
    }
}
