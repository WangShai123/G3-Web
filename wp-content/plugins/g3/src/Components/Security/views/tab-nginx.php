<?php
use JEALER\G3\Utilities\Frontend;
Frontend::loadStyle('jui');
?>
<div class="j-tip is-default mt-4">
    <div class="tip-title"><?php _e('Tip', 'G3'); ?></div>
    <div class="tip-content">
        <?php _e('This tab is for Nginx security configuration advice.', 'G3'); ?>
    </div>
</div>

<div class="j-tip is-default mt-2">
    <div class="tip-content">
        <!-- 阻止 query-string 中出现危险 PHP ini override 参数 -->
        <div><?php _e('Prevent query-string from containing PHP ini configuration override parameters.', 'G3'); ?></div>
        <pre><code>if ($query_string ~* "auto_prepend_file|allow_url_include|php://input") {
    return 403;
}</code></pre>
    </div>
</div>

<div class="j-tip is-default mt-2">
    <div class="tip-content">
        <!-- 禁止直接访问未映射的 .php 文件 -->
        <div><?php _e('Deny direct access to PHP unmapped files.', 'G3'); ?></div>
        <pre><code>location ~ \.php$ {
    try_files $uri =404;
}</code></pre>
    </div>
</div>

<div class="j-tip is-default mt-2">
    <div class="tip-content">
        <!-- 屏蔽特定 User-Agent（例如 libredtail-http, python-httpx等） -->
        <div><?php _e('Block User-Agent, such as: libredtail-http, python-httpx...', 'G3'); ?></div>
        <pre><code>map $http_user_agent $block_ua {
    default         0;
    "~*libredtail-http"   1;
    "~*python-httpx"      1;
    "~*python-requests"   1;
    "~*Go-http-client"    1;
}
server {
    if ($block_ua) {
        return 403;
    }
}</code></pre>
    </div>
</div>

<div class="j-tip is-default mt-2">
    <div class="tip-content">
        <!-- 禁止访问敏感文件或目录 -->
        <div><?php _e('Block access to sensitive files or directories.', 'G3'); ?></div>
        <pre><code>location ~ ^/(\.user.ini|\.htaccess|\.git|\.env|\.svn|\.project|LICENSE|README.md)
{
    return 404;
}</code></pre>
    </div>
</div>

<div class="j-tip is-default mt-2">
    <div class="tip-content">
        <!-- 禁止访问所有 .log 文件（特别是 wp-content/debug.log） -->
        <div><?php _e('Block access to all .log files. such as: wp-content/debug.log', 'G3'); ?></div>
        <pre><code>location ~* \.log$ {
    return 404;
}</code></pre>
    </div>
</div>

<div class="j-tip is-default mt-2">
    <div class="tip-content">
        <div><?php _e('Block access to XML-RPC', 'G3'); ?></div>
        <pre><code>location = /xmlrpc.php {
    deny all;
}</code></pre>
    </div>
</div>

<div class="j-tip is-default mt-2">
    <div class="tip-content">
        <div><?php _e('Block access to wp-config.php', 'G3'); ?></div>
        <pre><code>location = /wp-config.php {
    return 404;
}</code></pre>
    </div>
</div>

<style>
    pre:has(code) {
        line-height: 21.5px;
        background: rgba(0, 0, 0, .07);
        width: 100%;
        padding: 6px 4px;
    }

    pre:has(code) code {
        background: none;
    }
</style>