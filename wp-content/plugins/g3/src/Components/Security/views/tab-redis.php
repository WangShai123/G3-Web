<?php
use JEALER\G3\Utilities\Element;

echo Element::tip(
    __('This tab is for Redis Cache advice.', 'G3'),
    '',
    'default',
    'mt-4'
);
?>
<div class="g3-content">
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

    <h2>
        <?php _e('Use Redis', 'G3'); ?>
    </h2>
    <div class="j-tip is-default mt-2">
        <div class="tip-content">
            <div>
                <?php
                _e('1. Install Redis software on the server and enable it to run.', 'G3');
                ?>
            </div>
            <div>
                <?php
                _e('2. Install the Redis extension for PHP and enable it.', 'G3');
                ?>
            </div>
            <div>
                <?php
                _e('3. Create the <code>object-cache.php</code> object storage file: In the Developer Mode > Data Refresh page of the admin panel, click the "Generate object-cache.php file" button to add object caching functionality to WordPress.', 'G3');
                ?>
            </div>
        </div>
    </div>

    <h2>
        Redis <?php _e('Database'); ?>
    </h2>
    <?php
    echo Element::tip(
        __('G3-Web will create 2 databases in Redis to cache regular data and queue tasks. Regular data is stored in database 0, and queue tasks are stored in database 1.', 'G3'),
        false,
        '',
        'mt-2',
    );
    ?>

    <h2>
        <?php _e('Recommended configuration', 'G3'); ?>
    </h2>
    <p><?php _e('Use Redis\'s <b>LRU</b> eviction policy to avoid redundant cached data. Configure memory management in <code>redis.conf</code> to allow Redis to automatically evict infrequently used data:', 'G3'); ?>
    </p>
    <pre>
# <?php _e('Set the maximum memory as needed, such as 512MB.', 'G3'); ?>

maxmemory 512mb

# <?php _e('Use the LRU (Least Recently Used) strategy to automatically evict the least recently used key.', 'G3'); ?>

maxmemory-policy allkeys-lru
</pre>
</div>