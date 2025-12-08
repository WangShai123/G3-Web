<?PHP

if (!function_exists('html_class')) {
    /**
     * Generate HTML class attribute
     * 
     * 生成 HTML class 属性
     * 
     * Custom Filter: g3_filter_html_class
     * 
     * @param bool $print whether to print the class attribute
     * @return string|void
     * @since 1.0.0
     * @author Wang Shai
     */
    function html_class($print = true)
    {
        $classes = ['g3-web'];
        /**
         * @var array $classes
         * Custom Filter: g3_filter_html_class
         */
        $classes = apply_filters('g3_filter_html_class', $classes);

        $htmlClass = 'class="' . esc_attr(implode(' ', array_unique($classes))) . '"';

        if ($print) {
            echo $htmlClass;
        } else {
            return $htmlClass;
        }
    }
}

// add_action('wp_footer', function () {
//     $entries = apply_filters('jl_import_map_entries', []);
//     if (empty($entries)) return;

//     echo '<script type="importmap">' . json_encode(['imports' => $entries], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
// });