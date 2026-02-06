<?php
use JEALER\G3\Utilities\Element;

echo Element::tip(
    __('This tab is for PHP OPCache configuration advice.', 'G3'),
    'default',
    'mt-4'
);
?>
<div class="j-content">
    <h2>
        <?php _e('For PHP OPCache', 'G3'); ?>
    </h2>

    <div class="j-tip is-default mt-2">
        <div class="tip-content">
            <div>
                <?php _e('The purpose of OPCache is to improve the efficiency of PHP code through precompiling PHP code. Therefore, our core goal is to reduce the number of file reads and compilation overhead, find a balance between memory usage, cache hit, and code update mechanism.', 'G3'); ?>
            </div>
        </div>
    </div>

    <h2>
        <?php _e('Configuration Guide', 'G3'); ?>
    </h2>
    <h4>
        <?php
        _e('How to estimate the actual number of PHP files in your WordPress project?', 'G3');
        ?>
    </h4>
    <div>
        <?php
        _e('Run the command to see the number of .php files in your WordPress project root directory. Please modify the path to your actual project path.', 'G3');
        ?> <code>find /www/wwwroot/your-site -name "*.php" | wc -l</code>
    </div>

    <h4>
        <?php
        _e('Verify OPcache Efficiency & Fill', 'G3');
        ?>
    </h4>
    <pre>
&lt;?php
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status();
    echo "&lt;pre&gt;";
    print_r($status);
    echo "&lt;/pre&gt;";
} else {
    echo "OPcache not active.";
}</pre>
    <div>
        <?php
        _e('Use the above method to view the OPCache status, pay attention to the values of the <code>num_cached_scripts</code> and <code>max_cached_keys</code> parameters, which represent the number of scripts currently cached and the maximum number of scripts supported.', 'G3');
        ?>
    </div>

    <h3><?php _e('Advice Configuration'); ?></h3>
    <div>
        <?php
        _e('The following configuration recommendations are based on servers with 8G memory:', 'G3');
        ?>
    </div>
    <pre>; PHP-FPM Pool
pm = dynamic
pm.max_children = 15
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 500

; PHP Settings
php_admin_value[memory_limit] = 256M

; OPcache
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 32767
opcache.interned_strings_buffer = 16
opcache.revalidate_freq = 180
opcache.fast_shutdown = 1
opcache.save_comments = 1</pre>
</div>
<style>
    .tip-content ul {
        list-style: disc;
        margin-left: 16px;
    }

    .j-content pre {
        font-size: 12px;
    }
</style>