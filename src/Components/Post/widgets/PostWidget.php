<?php
use JEALER\G3\Utilities\Date;
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Utilities\Image;

class PostWidget extends WP_Widget {
    private int $count;
    private int $type;
    private int $category;

    public function __construct()
    {
        parent::__construct(
            'post_widget',
            __('Post List', 'G3'),
            [
                'classname'                   => 'widget-list post-widget',
                'description'                 => __('Retrieve the data for the post list.', 'G3'),
                'customize_selective_refresh' => true,
                'show_instance_in_rest'       => true,
            ]
        );
        $this->count    = 5;
        $this->category = 0;
        $this->type     = 0;
    }
    public function widget($args, $instance)
    {
        $title    = apply_filters('widget_title', $instance['title']);
        $count    = $instance['count'] ?? $this->count;
        $category = $instance['category'] ?? $this->category;
        $type     = $instance['type'] ?? $this->type;

        $posts = $this->getPosts($count, $category, $type);

        Frontend::css('widget');

        echo $args['before_widget'];

        if (!empty($title)) {
            // echo $args['before_title'] . $title . $args['after_title'];
            $link = site_url('/pages/post');
            echo '<div class="widget-header"><h3>' . $title . '</h3><a href="' . $link . '" class="more-link">' . __('More', 'G3') . Image::icon('arrow-right') . '</a></div>';
        }
        echo '<div class="widget-body">';
        foreach ($posts as $post) {
            echo '<div class="posts-list-item">';
            echo '<span class="item-index">' . (array_search($post, $posts) + 1) . '</span>';
            echo '<a href="' . get_permalink($post['id']) . '" class="item-title">' . $post['title'] . '</a></div>';
        }
        echo '</div>';
        echo $args['after_widget'];
    }
    public function form($instance)
    {
        $title      = !empty($instance['title']) ? $instance['title'] : __('Posts List', 'G3');
        $category   = $instance['category'] ?? $this->category;
        $count      = $instance['count'] ?? $this->count;
        $type       = $instance['type'] ?? $this->type;
        $categories = get_categories();
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>"
                name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('type'); ?>"><?php _e('Data Type', 'G3'); ?></label>
            <select class="widefat" id="<?php echo $this->get_field_id('type'); ?>"
                name="<?php echo $this->get_field_name('type'); ?>">
                <option value="0" <?php selected($type, 0); ?>><?php _e('Latest Posts', 'G3'); ?></option>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('count'); ?>"><?php _e('Count', 'G3'); ?></label>
            <input type="number" class="widefat" id="<?php echo $this->get_field_id('count'); ?>"
                name="<?php echo $this->get_field_name('count'); ?>"
                value="<?php echo !empty($count) ? $count : $this->count; ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('category'); ?>"><?php _e('Categories'); ?></label>
            <select class="widefat" id="<?php echo $this->get_field_id('category'); ?>"
                name="<?php echo $this->get_field_name('category'); ?>">
                <option value="0" <?php selected($category, 0); ?>><?php _e('All Categories'); ?></option>
                <?php foreach ($categories as $cat) : ?>
                    <option value="<?php echo $cat->slug; ?>" <?php selected($category, $cat->slug); ?>><?php echo $cat->name; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }
    public function update($new_instance, $old_instance)
    {
        $instance             = $old_instance;
        $instance['title']    = strip_tags($new_instance['title']);
        $instance['type']     = $new_instance['type'];
        $instance['count']    = $new_instance['count'];
        $instance['category'] = $new_instance['category'];
        return $instance;
    }
    private function getPosts($count, $category, $type)
    {
        $posts = [];

        $args = [
            'post_type'      => 'post',
            'posts_per_page' => $count,
            'order'          => 'DESC',
        ];
        switch ($type) {
            case 0:
                $args['orderby'] = 'date';
                break;
            default:
                break;
        }

        if (!empty($category)) {
            $args['category_name'] = $category;
        }

        $data = new WP_Query($args);

        if ($data->have_posts()) {
            while ($data->have_posts()) {
                $data->the_post();

                $posts[] = [
                    'id'    => get_the_ID(),
                    'title' => get_the_title(),
                    'link'  => get_permalink(),
                    'time'  => Date::humanTime(),
                    'cover' => get_the_post_thumbnail_url(get_the_ID(), 'thumbnail'),
                ];
            }
        }

        wp_reset_postdata();

        return $posts;
    }
}
