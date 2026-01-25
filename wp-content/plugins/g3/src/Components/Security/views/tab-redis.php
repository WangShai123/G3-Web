<?php
use JEALER\G3\Utilities\Element;

echo Element::tip(
    __('This tab is for Redis Cache configuration.', 'G3'),
    'default',
    'mt-4'
);
?>
<div class="j-content">
    <h2>
        <?php _e('For Object Cache', 'G3'); ?>
    </h2>
    <div class="j-tip is-default mt-2">
        <div class="tip-content">
            <div>
                <?php _e('Redis is the default data cache driver of G3-Web. We expect you to reduce unnecessary direct connections to the database and use memory caching to improve data query speed and response speed.', 'G3'); ?>
            </div>
            <div>
                <?php _e('In G3-Web, all business data must be cached and queried through Redis.', 'G3'); ?>
            </div>
        </div>
    </div>
</div>