<?php
use JEALER\G3\Utilities\Element;

echo Element::tip(
    __('Learn more about G3 Web and G3 System on the websites below.', 'G3'),
    '',
    'default',
    'mt-4'
);
?>
<p>
    <a href="https://www.jealer.com/g3-system/g3-web" target="_blank"
        title="<?php echo __('About', 'G3') . '  G3 Web'; ?>"><?php echo __('About', 'G3') . '  G3 Web'; ?></a>
</p>
<p>
    <a href="https://www.jealer.com/g3-system" target="_blank"
        title="<?php echo __('About', 'G3') . ' G3 ' . __('System', 'G3'); ?>"><?php echo __('About', 'G3') . ' G3 ' . __('System', 'G3'); ?></a>
</p>
<p>
    <a href="https://www.jealer.com/courses" target="_blank"
        title="<?php _e('Free Online Courses', 'G3'); ?>"><?php _e('Free Online Courses', 'G3'); ?></a>
</p>
<p>
    <a href="https://www.jealer.com/documents" target="_blank"
        title="<?php _e('Free Online Documents', 'G3'); ?>"><?php _e('Free Online Documents', 'G3'); ?></a>
</p>
<p>
    <a href="https://www.jealer.com/contact" target="_blank"
        title="<?php _e('Contact us', 'G3'); ?>"><?php _e('Contact us', 'G3'); ?></a>
</p>
