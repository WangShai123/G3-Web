<?php
use JEALER\G3\Components;
use JEALER\G3\Services\UserService;
use JEALER\G3\Utilities\Date;

/**
 * Comment Widget: Display a list of comments.
 * 
 * 评论列表小工具
 * 
 * @since 1.0.0
 * @author Wang Shai
 */
class CommentWidget extends WP_Widget {
    private $from;
    private $type;
    private $count;
    private $length;

    public function __construct()
    {
        parent::__construct(
            'comment_widget',
            __('Comment List', 'G3'),
            [
                'classname'                   => 'widget_comment',
                'description'                 => __('Retrieve the data for the comment list.', 'G3'),
                'customize_selective_refresh' => true,
                'show_instance_in_rest'       => true,
            ]
        );
        $this->from   = 0;
        $this->type   = 0;
        $this->count  = 5;
        $this->length = 30;
    }

    public function widget($args, $instance)
    {
        $title  = apply_filters('widget_title', $instance['title']);
        $from   = $instance['from'] ?? $this->from;
        $type   = $instance['type'] ?? $this->type;
        $count  = $instance['count'] ?? $this->count;
        $length = $instance['length'] ?? $this->length;

        echo $args['before_widget'];
        if (!empty($title)) {
            echo $args['before_title'] . $title . $args['after_title'];
        }
        echo '<div class="widget-content">';

        $comments = $this->getComments($from, $type, $count, $length);
        foreach ($comments as $comment) {
            echo '<a href="' . $comment['post_link'] . '#comment-' . $comment['comment_id'] . '">';
            if ($comment['avatar']) {
                echo '<span class="j-img-wrap"><img src="' . $comment['avatar'] . '" alt="' . $comment['nickname'] . '" draggable="false" class="img-item select-none j-lazy"></span>';
            }
            echo '<span class="widget-item-wrap is-line-3">' . $comment['content'] . '...<i>-' . $comment['time'] . '</i></span></a>';
        }

        echo '</div>';
        echo $args['after_widget'];
    }

    public function form($instance)
    {
        $from = $instance['from'] ?? $this->from;

        $announcement = Components::hasComponent('Announcement');
        $news         = Components::hasComponent('News');
        $product      = Components::hasComponent('Product');

        $title = !empty($instance['title']) ? $instance['title'] : __('Comment List', 'G3');
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>"
                name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('from'); ?>"><?php _e('Data Source'); ?></label>
            <select class="widefat" id="<?php echo $this->get_field_id('from'); ?>"
                name="<?php echo $this->get_field_name('from'); ?>">
                <option value="0" <?php selected($from, 0); ?>><?php _e('All Comments', 'G3'); ?></option>
                <option value="1" <?php selected($from, 1); ?>><?php _e('Post Comments', 'G3'); ?></option>
                <option value="2" <?php selected($from, 2); ?>><?php _e('Page Comments', 'G3'); ?></option>
                <?php if ($announcement) : ?>
                    <option value="3" <?php selected($from, 3); ?>>
                        <?php _e('Announcement Comments', 'G3'); ?>
                    </option>
                <?php endif; ?>
                <?php if ($news) : ?>
                    <option value="4" <?php selected($from, 4); ?>>
                        <?php _e('News Comments', 'G3'); ?>
                    </option>
                <?php endif; ?>
                <?php if ($product) : ?>
                    <option value="5" <?php selected($from, 5); ?>>
                        <?php _e('Product Comments', 'G3'); ?>
                    </option>
                <?php endif; ?>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('type'); ?>"><?php _e('Data Type'); ?></label>
            <select class="widefat" id="<?php echo $this->get_field_id('type'); ?>"
                name="<?php echo $this->get_field_name('type'); ?>">
                <option value="0" <?php //selected($instance['type'], 0); ?>><?php _e('Latest Comments', 'G3'); ?></option>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('count'); ?>"><?php _e('The Count'); ?></label>
            <input type="number" class="widefat" id="<?php echo $this->get_field_id('count'); ?>"
                name="<?php echo $this->get_field_name('count'); ?>"
                value="<?php echo !empty($instance['count']) ? $instance['count'] : $this->count; ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('length'); ?>"><?php _e('Maximum Character Count'); ?></label>
            <input type="number" class="widefat" id="<?php echo $this->get_field_id('length'); ?>"
                name="<?php echo $this->get_field_name('length'); ?>"
                value="<?php echo !empty($instance['length']) ? $instance['length'] : $this->length; ?>">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance)
    {
        $instance           = $old_instance;
        $instance['title']  = strip_tags($new_instance['title']);
        $instance['from']   = $new_instance['from'];
        $instance['type']   = $new_instance['type'];
        $instance['count']  = $new_instance['count'];
        $instance['length'] = $new_instance['length'];

        return $instance;
    }

    private function getComments($from, $type, $count, $length)
    {
        $comments = [];

        $args = [
            'status'      => 'approve',
            'number'      => $count,
            'type'        => 'comment',
            'post_status' => 'publish',
            'order'       => 'DESC',
        ];

        match ($from) {
            1 => $args['post_type'] = 'post',
            2 => $args['post_type'] = 'page',
            3 => $args['post_type'] = 'announcement',
            4 => $args['post_type'] = 'news',
            5 => $args['post_type'] = 'product',
            default => $args['post_type'] = 'any',
        };

        match ($type) {
            default => $args['orderby'] = 'comment_date',
        };

        $data     = new WP_Comment_Query;
        $comments = $data->query($args);
        $length   = is_numeric($length) && (int) $length > 0 ? (int) $length : $this->length;
        $comments = array_map(
            fn($comment) => [
                'comment_id' => $comment->comment_ID,
                'user_id'    => $comment->user_id,
                'post_link'  => get_permalink($comment->comment_post_ID),
                'avatar'     => UserService::getMeta($comment->user_id, UserService::META_KEY, 'avatar', []),
                'nickname'   => $comment->comment_author,
                'content'    => mb_substr(strip_tags($comment->comment_content), 0, $length, 'utf-8'),
                'time'       => Date::humanTime(strtotime($comment->comment_date))
            ],
            $comments
        );

        return $comments;
    }
}