<?php
use JEALER\G3\Utilities\Element;

echo Element::tip(
    __('This tab is for Nginx FastCGI Cache configuration.', 'G3'),
    'default',
    'mt-4'
);
?>
<div class="j-content">
    <h2>
        <?php _e('For Traditional Wordpress Theme', 'G3'); ?>
    </h2>

    <div class="j-tip is-default mt-2">
        <div class="tip-content">
            <div>
                <?php _e('Traditional website application working process: ', 'G3'); ?>
            </div>
            <ul>
                <li><?php _e('User visits the website', 'G3'); ?></li>
                <li><?php _e('The nginx server receives the user request', 'G3'); ?></li>
                <li><?php _e('Nginx sends the user request to php-fpm', 'G3'); ?></li>
                <li><?php _e('Runs wordpress to process the request', 'G3'); ?></li>
                <li>
                    <?php _e('php-fpm processes the response, returns the response to nginx', 'G3'); ?>
                </li>
                <li>
                    <?php _e('Nginx returns the response to the user', 'G3'); ?>
                </li>
            </ul>
            <div>
                <?php _e('As a rule, most of the content on the web is static. Frequently interacting with php-fpm for unnecessary interactions will cause a great performance waste.', 'G3'); ?>
            </div>
            <div>
                <?php _e('By utilizing the nginx fastcgi cache function, website content can be cached as static HTML. Every time a normal user GET request is made, nginx will first read the cache file, reducing unnecessary interactions with php-fpm.', 'G3'); ?>
            </div>
        </div>
    </div>

    <h2>
        <?php _e('Configuration Guide', 'G3'); ?>
    </h2>
</div>
<style>
    .tip-content ul {
        list-style: disc;
        margin-left: 16px;
    }
</style>