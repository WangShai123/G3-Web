<?php
use JEALER\G3\Utilities\Element;

echo Element::tip(
    __('AI can access the endpoint to get a list of site content for AI model training. Please ensure the security of the endpoint and regularly check access logs after enabling it.', 'G3'),
    '',
    'default',
    'mt-4'
);
echo '<form action="" method="POST">';
settings_fields('llm');
do_settings_sections('g3-settings&tab=llm');
submit_button();
?>
</form>
<script>
    jQuery(document).ready(function ($) {
        const { Toast } = jui
        const { success, error } = Toast
        $('#generateLLM').on('click', function (e) {
            e.preventDefault();
            $.post(ajaxurl, {
                action: 'g3_generate_llm',
                nonce: '<?= wp_create_nonce('g3_generate_llm') ?>'
            }).done(function (res) {
                if (res.success) {
                    success(res.data.message)
                } else {
                    error(res.data.message)
                }
            }).fail(function (xhr, status, err) {
                error(xhr.responseJSON.data.message)
            })
        });
    });
</script>