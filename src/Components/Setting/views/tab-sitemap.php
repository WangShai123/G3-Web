<?php
use JEALER\G3\Utilities\Element;

echo Element::tip(
    sprintf('<div>' . __('In <a href="%s">the security settings</a>, you can choose to use G3-Sitemap instead of <a href="%s" target="_blank">the default WordPress sitemap</a>, as the default sitemap may expose sensitive user information.', 'G3') . '</div>', admin_url('admin.php?page=security'), home_url('wp-sitemap.xml')),
    '',
    'default',
    'mt-4'
);
$renderer->form($panel, $panelTab);
?>
<style>
    p.submit {
        display: none
    }
</style>
<script>
    jQuery(document).ready(function ($) {
        const { q, Toast } = jui
        const { success, error } = Toast
        q('#generateSitemap').addEventListener('click', function () {
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
<?php
