<?php

use JEALER\G3\Utilities\Frontend;

class TocWidget extends WP_Widget {
    private const DEFAULT_TITLE    = 'Table of Contents';
    private const DEFAULT_TARGET   = '.j-editor';
    private const DEFAULT_HEADINGS = 'h2,h3';
    private const DEFAULT_OFFSET   = 80;

    public function __construct()
    {
        parent::__construct(
            'toc_widget',
            __('Table of Contents'),
            [
                'classname'                   => 'widget-list toc-widget',
                'description'                 => __('Display the table of contents for the current post.', 'G3'),
                'customize_selective_refresh' => true,
                'show_instance_in_rest'       => true,
            ]
        );
    }

    public function widget($args, $instance)
    {
        if (!is_singular() || !$this->checkHeadings()) {
            return;
        }

        $config = $this->normalizeInstance($instance);
        $title  = apply_filters('widget_title', $config['title']);

        Frontend::esm('g3.toc');
        Frontend::css('g3.widget');

        $style = 'style="backdrop-filter:blur(4px)"';
        echo $args['before_widget'];
        if (!empty($title)) {
            echo '<div class="widget-header" ' . $style . '><h3>' . wp_kses_post($title) . '</h3></div>';
        }
        echo '<div class="widget-body" ' . $style . '>';
        echo '<div class="g3-toc-root" data-g3-toc data-target="' . esc_attr($config['target']) . '" data-headings="' . esc_attr($config['headings']) . '" data-offset="' . esc_attr((string) $config['offset']) . '"></div>';
        echo '</div>';
        echo $args['after_widget'];
    }

    public function form($instance)
    {
        $config   = $this->normalizeInstance($instance);
        $target   = sprintf(__('%s %s %s', 'G3'), __('Target', 'G3'), __('Container', 'G3'), __('Selector', 'G3'));
        $headings = sprintf(__('%s %s', 'G3'), __('Headings', 'G3'), __('Scope', 'G3'));
        $offset   = sprintf(__('%s %s', 'G3'), __('Offset', 'G3'), __('Distance', 'G3'));
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>"
                name="<?php echo $this->get_field_name('title'); ?>" type="text"
                value="<?php echo esc_attr($config['title']); ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('target'); ?>"><?php echo $target; ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('target'); ?>"
                name="<?php echo $this->get_field_name('target'); ?>" type="text"
                value="<?php echo esc_attr($config['target']); ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('headings'); ?>"><?php echo $headings; ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('headings'); ?>"
                name="<?php echo $this->get_field_name('headings'); ?>" type="text"
                value="<?php echo esc_attr($config['headings']); ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('offset'); ?>"><?php echo $offset . '(px)'; ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('offset'); ?>"
                name="<?php echo $this->get_field_name('offset'); ?>" type="number" min="0" step="1"
                value="<?php echo esc_attr((string) $config['offset']); ?>" />
        </p>
        <?php

    }

    public function update($new_instance, $old_instance)
    {
        $instance             = $old_instance;
        $instance['title']    = sanitize_text_field(wp_unslash($new_instance['title'] ?? self::DEFAULT_TITLE));
        $instance['target']   = $this->sanitizeSelector($new_instance['target'] ?? self::DEFAULT_TARGET, self::DEFAULT_TARGET);
        $instance['headings'] = $this->sanitizeSelector($new_instance['headings'] ?? self::DEFAULT_HEADINGS, self::DEFAULT_HEADINGS);
        $instance['offset']   = max(0, absint($new_instance['offset'] ?? self::DEFAULT_OFFSET));

        return $instance;
    }

    private function normalizeInstance($instance): array
    {
        $instance = is_array($instance) ? $instance : [];

        return [
            'title'    => (string) ($instance['title'] ?? __(self::DEFAULT_TITLE)),
            'target'   => $this->sanitizeSelector($instance['target'] ?? self::DEFAULT_TARGET, self::DEFAULT_TARGET),
            'headings' => $this->sanitizeSelector($instance['headings'] ?? self::DEFAULT_HEADINGS, self::DEFAULT_HEADINGS),
            'offset'   => max(0, absint($instance['offset'] ?? self::DEFAULT_OFFSET)),
        ];
    }

    private function sanitizeSelector(mixed $value, string $fallback): string
    {
        $selector = sanitize_text_field(wp_unslash((string) $value));
        return $selector !== '' ? $selector : $fallback;
    }

    private function checkHeadings(): bool
    {
        $id = get_queried_object_id();
        if (!$id) return false;

        $post = get_post($id);
        if (!$post) return false;

        $content  = $post->post_content;
        $headings = preg_match_all('/<h2|<h3>/i', $content, $matches);
        return $headings > 0;
    }
}
