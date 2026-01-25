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
        <?php _e('For Hybrid Theme', 'G3'); ?>
    </h2>
    <div class="j-tip is-default mt-2">
        <div class="tip-content">
            <div>
                <?php _e('We recommend using the frontend and backend hybrid development way to build wordpress themes.', 'G3'); ?>
            </div>
            <ul>
                <li>
                    <?php _e('In PHP templates, build a complete header to meet SEO requirements. In the page, only output the absolute necessary content.', 'G3'); ?>
                </li>
                <li>
                    <?php _e('In template pages, most data is handled and interacted with by front-end JavaScript using rest-api and client-side caching (such as localStorage, indexDB). Reduce server pressure, reduce traffic transmission, improve page loading speed and user experience.', 'G3'); ?>
                </li>
            </ul>
        </div>
    </div>
</div>