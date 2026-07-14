<?php
use JEALER\G3\Core\Container\Container;
use JEALER\G3\Services\SystemService;

class MonitorWidget extends WP_Widget {
    private Container $container;
    private SystemService $systemService;
    private array $fields = [
        'queries' => 'Database Queries',
        'time'    => 'Elapsed Time',
        'memory'  => 'Memory Usage',
        'online'  => '在线人数',
    ];
    public function __construct()
    {
        $widget_ops = [
            'classname'                   => 'monitor-widget',
            'description'                 => __('Retrieve the data for the web application monitor.', 'G3'),
            'customize_selective_refresh' => true,
            'show_instance_in_rest'       => true,
        ];
        parent::__construct('monitor_widget', __('Application Monitor', 'G3'), $widget_ops);
        $this->container = Container::run();
        $this->systemService = $this->container->get(SystemService::class);
    }
    public function widget($args, $instance)
    {
        $title = apply_filters('widget_title', $instance['title'] ?? __('Application Monitor', 'G3'));
        $items = $this->getMonitorItems($instance);

        echo $args['before_widget'];

        if (!empty($title)) {
            // echo $args['before_title'] . esc_html($title) . $args['after_title'];
            echo '<div class="widget-header"><h3>' . esc_html($title) . '</h3></div>';
        }

        if ($items) {
            echo '<div class="widget-body"><ul>';
            foreach ($items as $label => $value) {
                echo '<li><span class="monitor-label">' . esc_html($label) . '</span> <span class="monitor-value">' . esc_html($value) . '</span></li>';
            }
            echo '</ul></div>';
        }

        echo $args['after_widget'];
    }
    public function form($instance)
    {
        $title = !empty($instance['title']) ? $instance['title'] : __('Application Monitor', 'G3');
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e('Title'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
        </p>
        <p>
            <strong><?php _e('Display Items', 'G3'); ?></strong>
        </p>
        <?php foreach ($this->fields as $key => $label) : ?>
            <p>
                <input class="checkbox" type="checkbox" value="1"
                    id="<?php echo esc_attr($this->get_field_id($key)); ?>"
                    name="<?php echo esc_attr($this->get_field_name($key)); ?>"
                    <?php checked($this->isEnabled($instance, $key)); ?> />
                <label for="<?php echo esc_attr($this->get_field_id($key)); ?>">
                    <?php echo esc_html__($label, 'G3'); ?>
                </label>
            </p>
        <?php endforeach; ?>
        <?php
    }
    public function update($new_instance, $old_instance)
    {
        $instance          = $old_instance;
        $instance['title'] = sanitize_text_field($new_instance['title'] ?? '');

        foreach ($this->fields as $key => $label) {
            $instance[$key] = !empty($new_instance[$key]) ? '1' : '0';
        }

        return $instance;
    }
    private function getMonitorItems(array $instance): array
    {
        $items = [];

        if ($this->isEnabled($instance, 'queries')) {
            $items[__('Database Queries', 'G3')] = get_num_queries() . ' SQL';
        }

        if ($this->isEnabled($instance, 'time')) {
            $items[__('Elapsed Time', 'G3')] = sprintf('%.3f s', (float) timer_stop(0, 3));
        }

        if ($this->isEnabled($instance, 'memory')) {
            $items[__('Memory Usage', 'G3')] = sprintf('%.2f MB', memory_get_usage() / 1024 / 1024);
        }

        if ($this->isEnabled($instance, 'online')) {
            $v = $this->systemService->getOnlineCount();
            $items[__('Online', 'G3'). ' '. __('Users')] = $v === false ? __('Feature Disabled','G3') : $v;
        }

        return $items;
    }
    private function isEnabled(array $instance, string $key): bool
    {
        return !array_key_exists($key, $instance) || $instance[$key] === '1';
    }
}
