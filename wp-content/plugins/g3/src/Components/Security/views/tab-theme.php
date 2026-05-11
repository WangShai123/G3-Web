<?php
use JEALER\G3\Utilities\Element;

echo Element::tip(
    __('This tab is for hybrid theme development advice.', 'G3'),
    '',
    'default',
    'mt-4'
);
?>
<div class="g3-content">
    <h2>
        <?php _e('For Hybrid Theme', 'G3'); ?>
    </h2>
    <div class="j-tip is-default mt-2">
        <div class="tip-content">
            <div>
                <?php _e('We recommend using the frontend and backend hybrid development way to build wordpress themes.', 'G3'); ?>
            </div>
            <ol>
                <li>
                    <?php _e('In PHP templates, build a complete header to meet SEO requirements. In the page, only output the absolute necessary content.', 'G3'); ?>
                </li>
                <li>
                    <?php _e('In template pages, most data is handled and interacted with by front-end JavaScript using rest-api and client-side caching (such as localStorage, indexDB). Reduce server pressure, reduce traffic transmission, improve page loading speed and user experience.', 'G3'); ?>
                </li>
            </ol>
            <div>
                <?php
                _e('Of course, if you have complete JavaScript skills, we recommend you use the no-theme mode to build Web applications using pure front-end skills.', 'G3');
                ?>
            </div>
        </div>
    </div>

    <h2><?php _e('For No Theme Mode', 'G3') ?></h2>
    <div class="j-tip is-default mt-2">
        <div class="tip-content">
            <div>
                <?php
                _e('WordPress supports building Web applications using pure front-end skills.', 'G3');
                ?>
            </div>
            <ol>
                <li>
                    <?php
                    _e('Open the index.php file in the root directory, modify <code>WP_USE_THEME</code> to <code>false</code> to disable WordPress theme template related functions: <code>define(\'WP_USE_THEMES\', true);</code>', 'G3');
                    ?>
                </li>
                <li>
                    <?php
                    _e('Use JavaScript and any favorite development framework, such as <code>React</code>, <code>Vue</code> based on <code>Restful API</code>, customize page routing, build pure front-end Web applications.', 'G3');
                    ?>
                </li>
            </ol>
            <div>
                <?php
                _e('The No Theme Mode, will greatly improve your application performance and server load capacity.', 'G3');
                ?>
            </div>
        </div>
    </div>
</div>