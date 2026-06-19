<?php

use JEALER\G3\Utilities\Element;

echo Element::tip(
    __('You can provide the RSS feed address to partners or users for subscribing to receive content from your platform.', 'G3'),
    '',
    'default',
    'mt-4'
);
echo '<form action="" method="POST">';
settings_fields('rss');
do_settings_sections('g3-settings&tab=rss');
submit_button();
?>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const { copy, on, Toast } = jui
        on(document, 'click', (e) => {
            if (['rss1', 'rss2', 'atom'].includes(e.target.id)) {
                copy(e.target.value)
                Toast.lite('<?php _e('Copied'); ?>')
            }
        })
    })
</script>