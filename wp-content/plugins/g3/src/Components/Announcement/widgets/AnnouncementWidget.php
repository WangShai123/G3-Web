<?php
use JEALER\G3\Utilities\Date;

class AnnouncementWidget extends WP_Widget {
    private $count;
    private $category;
    private $type;

    public function __construct()
    {
        $widget_ops = [
            'classname'                   => 'widget_announcement',
            'description'                 => __('Retrieve the data for the announcement list.', 'G3'),
            'customize_selective_refresh' => true,
            'show_instance_in_rest'       => true,
        ];
        parent::__construct('announcement_widget', __('Announcement List', 'G3'), $widget_ops);

        $this->count    = 5;
        $this->category = 0;
        $this->type     = 0;
    }

    public function widget($args, $instance)
    {
        $title = apply_filters('widget_title', $instance['title']);

        $count    = $instance['count'] ?? $this->count;
        $category = $instance['category'] ?? $this->category;
        $type     = $instance['type'] ?? $this->type;

        $announcements = $this->getAnnouncements($count, $category, $type);

        echo $args['before_widget'];

        if (!empty($title)) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        echo '<div class="widget-content">';
        foreach ($announcements as $announcement) {
            echo '<a href="' . get_permalink($announcement['id']) . '">';
            if ($announcement['cover']) {
                echo '<span class="j-img-wrap"><img src="' . $announcement['cover'] . '" alt="' . $announcement['title'] . '" draggable="false" class="img-item select-none j-lazy"></span>';
            }
            echo '<span class="widget-item-wrap is-line-3">' . $announcement['title'] . '<i>-' . $announcement['time'] . '</i></span></a>';
        }
        echo '</div>';
        echo $args['after_widget'];
    }

    public function form($instance)
    {
        $title      = !empty($instance['title']) ? $instance['title'] : __('Announcement List', 'G3');
        $category   = $instance['category'] ?? '';
        $categories = get_terms([
            'taxonomy'   => 'announcement_category',
            'hide_empty' => false,
        ]);
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>"
                name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('type'); ?>"><?php _e('Data Type'); ?></label>
            <select class="widefat" id="<?php echo $this->get_field_id('type'); ?>"
                name="<?php echo $this->get_field_name('type'); ?>">
                <option value="0" <?php //selected($instance['type'], 0); ?>><?php _e('Latest Announcements', 'G3'); ?></option>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('count'); ?>"><?php _e('The Count'); ?></label>
            <input type="number" class="widefat" id="<?php echo $this->get_field_id('count'); ?>"
                name="<?php echo $this->get_field_name('count'); ?>"
                value="<?php echo !empty($instance['count']) ? $instance['count'] : $this->count; ?>">
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
        $instance['count']    = $new_instance['count'];
        $instance['category'] = $new_instance['category'];
        return $instance;
    }

    private function getAnnouncements($count, $category, $type)
    {
        $posts = [];

        $args = [
            'post_type'      => 'announcement',
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