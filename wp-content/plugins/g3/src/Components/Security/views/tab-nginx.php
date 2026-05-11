<?php
use JEALER\G3\Utilities\Element;

echo Element::tip(
    __('This tab is for Nginx security configuration advice.', 'G3'),
    '',
    'default',
    'mt-4'
);
?>

<div class="g3-content">

    <div class="j-tip is-default mt-2">
        <div class="tip-content">
            <div><?php _e('Prevent query-string from containing PHP ini configuration override parameters.', 'G3'); ?>
            </div>
            <pre>if ($query_string ~* "auto_prepend_file|allow_url_include|php://input") {
    return 403;
}</pre>
        </div>
    </div>

    <div class="j-tip is-default mt-2">
        <div class="tip-content">
            <div><?php _e('Deny direct access to PHP unmapped files.', 'G3'); ?>
            </div>
            <pre>location ~ \.php$ {
    try_files $uri =404;
}</pre>
        </div>
    </div>

    <div class="j-tip is-default mt-2">
        <div class="tip-content">
            <div>
                <?php _e('Block User-Agent, such as: libredtail-http, python-httpx...', 'G3'); ?>
            </div>
            <pre>map $http_user_agent $block_ua {
    default         0;
    "~*libredtail-http"   1;
    "~*python-httpx"      1;
    "~*python-requests"   1;
    "~*Go-http-client"    1;
    "~*libwww-perl"       1;
}
server {
    if ($block_ua) {
        return 403;
    }
}</pre>
        </div>
    </div>

    <div class="j-tip is-default mt-2">
        <div class="tip-content">
            <div>
                <?php _e('Block access to sensitive files or directories.', 'G3'); ?>
            </div>
            <pre>location ~ ^/(\.user.ini|\.htaccess|\.git|\.env|\.svn|\.project|LICENSE|README.md|package.json|package-lock.json)
{
    return 404;
}</pre>
        </div>
    </div>

    <div class="j-tip is-default mt-2">
        <div class="tip-content">
            <div>
                <?php _e('Block access to all .log files. such as: wp-content/debug.log', 'G3'); ?>
            </div>
            <pre>location ~* \.log$ {
    return 404;
}</pre>
        </div>
    </div>

    <div class="j-tip is-default mt-2">
        <div class="tip-content">
            <div>
                <?php _e('Block access to XML-RPC', 'G3'); ?>
            </div>
            <pre>location = /xmlrpc.php {
    deny all;
}</pre>
        </div>
    </div>

    <div class="j-tip is-default mt-2">
        <div class="tip-content">
            <div>
                <?php _e('Block access to wp-config.php', 'G3'); ?>
            </div>
            <pre>location = /wp-config.php {
    return 404;
}</pre>
        </div>

    </div>

    <div class="j-tip is-default mt-2">
        <div class="tip-content">
            <div>
                <?php _e('Remove sensitive data from response headers, such as: X-Powered-By.', 'G3'); ?>
            </div>
            <pre>fastcgi_hide_header X-Powered-By;</pre>
        </div>
    </div>

</div>