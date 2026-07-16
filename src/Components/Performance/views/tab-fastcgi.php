<?php
use JEALER\G3\Utilities\Element;

echo Element::tip(
    __('This tab contains recommendations for Nginx FastCGI caching configuration. If your website primarily contains static content and you want to further reduce server CPU load and memory consumption, please use it.', 'G3'),
    '',
    'default',
    'mt-4'
);
?>
<div class="g3-content">
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
    <h4>Nginx <?php _e('Configuration', 'G3'); ?></h4>
    <p>
        <?php _e('You need to define the cache file storage directory, such as: ', 'G3'); ?><code>/www/cache/nginx</code>
    </p>
    <pre># <?php _e('The cache files will be stored in the /www/cache/nginx directory, and the cache files will be distributed across two levels of subdirectories. The shared memory area is named WORDPRESS and has a size of 100MB. If a cache file is not accessed within 60 minutes, Nginx will automatically clean it up.', 'G3'); ?>

fastcgi_cache_path /www/cache/nginx levels=1:2 keys_zone=WORDPRESS:100m inactive=60m;

# <?php _e('Nginx will generate a unique MD5 hash value as the cache file name based on the request protocol (such as http/https), request method (GET/POST), host name and complete URI.', 'G3'); ?>

fastcgi_cache_key "$scheme$request_method$host$request_uri";

# <?php _e('Degradation and fault tolerance mechanism: When the backend PHP-FPM encounters an exception, Nginx allows returning an expired old cache to the user instead of directly reporting an error.', 'G3'); ?>

fastcgi_cache_use_stale error timeout invalid_header http_500;

# <?php _e('"Force Takeover" of cache control: Ignore the cache control header information returned by the backend PHP-FPM to ensure that Nginx\'s caching strategy takes precedence over the backend application\'s caching strategy.', 'G3'); ?>

fastcgi_ignore_headers Cache-Control Expires Set-Cookie;</pre>
    <h4>
        <?php _e('Website Configuration', 'G3'); ?>
    </h4>
    <p>
        <?php _e('Config as below in <code>Server {}</code> module:', 'G3'); ?>
    </p>
    <pre># <?php _e('Define cache switch', 'G3'); ?>

set $skip_cache 0;

# <?php _e('POST requests are not cached', 'G3'); ?>

if ($request_method = POST) {
    set $skip_cache 1;
}

# <?php _e('URLs with query parameters are not cached', 'G3'); ?>

if ($query_string != "") {
    set $skip_cache 1;
}

# <?php _e('Admin panel, special files, and REST API are not cached', 'G3'); ?>

if ($request_uri ~* "/wp-admin/|/xmlrpc.php|wp-.*\.php|/feed/|/sitemap|/wp-json/") {
    set $skip_cache 1;
}

# <?php _e('Logged-in users are not cached', 'G3'); ?>

if ($http_cookie ~* "comment_author|wordpress_[a-f0-9]+|wp-postpass|wptouch_switch_toggle") {
    set $skip_cache 1;
}

# <?php _e('PHP processing block', 'G3'); ?>

location ~ \.php$ {
    # <?php _e('From the PHP configuration file (such as enable-php-83.conf), confirm and modify this path:', 'G3'); ?>
    
    fastcgi_pass unix:/tmp/php-cgi-83.sock;

    fastcgi_index index.php;
    include fastcgi.conf;
    include pathinfo.conf;

    # FastCGI Cache
    fastcgi_cache_bypass $skip_cache;
    fastcgi_no_cache $skip_cache;
    fastcgi_cache WORDPRESS;
    fastcgi_cache_valid 200 301 302 1h;
    add_header X-Cache "$upstream_cache_status From $host";
}</pre>
</div>