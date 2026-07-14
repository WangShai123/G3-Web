<?php
use JEALER\G3\Utilities\Element;

echo Element::tip(
    __('You can provide the RSS feed address to partners or users for subscribing to receive content from your platform.', 'G3'),
    '',
    'default',
    'mt-4'
);
$renderer->form($panel, $panelTab);
?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const { copy, Toast } = jui
        document.addEventListener('click', (e) => {
            if (['rss1', 'rss2', 'atom'].includes(e.target.id)) {
                copy(e.target.value)
                Toast.lite('<?php _e('Copied'); ?>')
            }
        })
    })
</script>
