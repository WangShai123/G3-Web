<?php
use JEALER\G3\Utilities\Element;

echo Element::tip(
    __('This tab is for Caddy security configuration advice.', 'G3'),
    '',
    'default',
    'mt-4'
);
?>

<div class="g3-content">

    <pre class="code-block">
# <?php _e('Prevent query-string from containing PHP ini configuration override parameters.', 'G3'); ?>

@badQuery {
    query auto_prepend_file*
    query allow_url_include*
    query *php://input*
}
respond @badQuery "Forbidden" 403

# <?php _e('Deny direct access to PHP unmapped files.', 'G3'); ?>

@blockedPHP {
    path_regexp blockedphp \.php$
    not path /index.php
}
respond @blockedPHP "Forbidden" 403

# <?php _e('Block User-Agent, such as: libredtail-http, python-httpx...', 'G3'); ?>

@blockUA {
    header User-Agent *libredtail-http*
    header User-Agent *python-httpx*
    header User-Agent *python-requests*
    header User-Agent *Go-http-client*
    header User-Agent *libwww-perl*
}
respond @blockUA 403

# <?php _e('Block access to sensitive files or directories.', 'G3'); ?>

@sensitive path_regexp sensitive ^/(\.user\.ini|\.htaccess|\.git|\.env|\.svn|\.project|LICENSE|README\.md|package\.json|package-lock\.json)$
respond @sensitive 404

# <?php _e('Block access to all .log files. such as: wp-content/debug.log', 'G3'); ?>

@logFiles path_regexp logfiles \.log$
respond @logFiles 404

# <?php _e('Block access to XML-RPC', 'G3'); ?>

@xmlrpc path /xmlrpc.php
respond @xmlrpc 403

# <?php _e('Block access to wp-config.php', 'G3'); ?>

@wpconfig path_regexp wpconfig ^/wp-config\.php$
respond @wpconfig 404

# <?php _e('Remove sensitive data from response headers, such as: X-Powered-By.', 'G3'); ?>

header -X-Powered-By
</pre>

</div>