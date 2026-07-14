<?php
use JEALER\G3\Utilities\Element;

$p1  = __('You can configure different theme templates for different types of devices to improve user experience.', 'G3');
$p2  = __('The theme you configure on the Appearance-Theme page is the main theme of the system. When the option corresponding to the multi-theme mode is empty, the main theme will be used first by default.', 'G3');
$msg = "<div>{$p1}</div><div>{$p2}</div>";
echo Element::tip(
    $msg,
    '',
    'default',
    'mt-4'
);

$renderer->form($panel, $panelPage);
?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const { copy, q, Toast } = jui;
        q('#copyScript').addEventListener('click', function () {
            const pre = q('#headScript');
            if (pre) {
                const result = copy(pre.textContent);
                if (result) {
                    Toast.success('<?php _e('Copied to clipboard!', 'G3'); ?>');
                } else {
                    Toast.error('<?php _e('Failed to copy!', 'G3'); ?>');
                }
            }
        });
    })
</script>
