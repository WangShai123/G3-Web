<?php
use JEALER\G3\Utilities\Element;

echo Element::tip(
    __('You can choose to use G3-Sitemap instead of the default WordPress sitemap in the security settings, as the default sitemap may expose sensitive user information.', 'G3'),
    '',
    'default',
    'mt-4'
);
echo '<form action="" method="POST">';
settings_fields('sitemap');
do_settings_sections('g3-settings&tab=sitemap');
?>
</form>
<script>
    jQuery(document).ready(function ($) {
        const { q, Toast, listen } = jui
        const { success, error } = Toast
        listen(q('#generateSitemap'), 'click', (e) => {
            e.preventDefault();
            $.post(ajaxurl, {
                action: 'g3_generate_sitemap',
                nonce: '<?= wp_create_nonce('g3_generate_sitemap') ?>'
            }).done(function (res) {
                if (res.success) {
                    success(res.data.message)
                } else {
                    error(res.data.message)
                }
            }).fail(function (xhr, status, err) {
                error(xhr.responseJSON.data.message)
            })
        })
    });

</script>