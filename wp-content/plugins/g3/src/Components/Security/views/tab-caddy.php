<?php
use JEALER\G3\Utilities\Frontend;
Frontend::loadStyle('jui');
?>
<div class="j-tip is-default mt-4">
    <div class="tip-title"><?php _e('Tip', 'G3'); ?></div>
    <div class="tip-content">
        <?php _e('This tab is for Caddy security configuration advice.', 'G3'); ?>
    </div>
</div>

<div class="j-tip is-default mt-2">
    <div class="tip-content">
        <div><?php _e('Prevent query-string from containing PHP ini configuration override parameters.', 'G3'); ?></div>
        <pre><code>@badQuery {
    query auto_prepend_file*
    query allow_url_include*
    query *php://input*
}
respond @badQuery "Forbidden" 403</code></pre>
    </div>
</div>

<div class="j-tip is-default mt-2">
    <div class="tip-content">
        <div><?php _e('Deny direct access to PHP unmapped files.', 'G3'); ?></div>
        <pre><code>@blockedPHP {
    path_regexp blockedphp \.php$
    not path /index.php
}
respond @blockedPHP "Forbidden" 403</code></pre>
    </div>
</div>

<div class="j-tip is-default mt-2">
    <div class="tip-content">
        <div><?php _e('Block User-Agent, such as: libredtail-http.', 'G3'); ?></div>
        <pre><code>@blockedUA header_regexp ua User-Agent "libredtail-http"
respond @blockedUA "Forbidden" 403</code></pre>
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