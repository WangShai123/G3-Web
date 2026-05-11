<?php

echo '<div class="wrap">';
echo '<h1 class="wp-heading-inline">' . __('Junk Cleaner', 'G3') . '</h1>';

settings_errors('junk-cleaner');
settings_fields('junk-cleaner');
do_settings_sections('junk-cleaner');

echo '</div>';

$confirm = __('Are you sure you want to delete it?', 'G3');
?>

<script>
    jQuery(document).ready(function ($) {
        const { toast } = jui;

        $('.junk-action-button').each(function () {
            if ($(this).data('count') === 0) {
                $(this).attr('disabled', true);
            }
        });

        const deletePost = (dataAction, action) => {
            $('body').on('click', `[data-action="${dataAction}"]`, function (e) {
                e.preventDefault();
                if (!confirm('<?php echo $confirm; ?>')) return;
                $.post(ajaxurl, {
                    action: action,
                }, (response) => {
                    if (response.success) {
                        toast.success(response.data.message);
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        toast.error(response.data.message);
                    }
                })
            })
        }

        deletePost('clear-draft', 'g3_clear_draft');
        deletePost('clear-auto-draft', 'g3_clear_auto_draft');
        deletePost('clear-trash', 'g3_clear_trash');
        deletePost('clear-revision', 'g3_clear_revision');
    })
</script>