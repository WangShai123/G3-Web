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
        const { Toast, all } = jui;
        const { success, error } = Toast

        const btns = all('.junk-action-button');
        let toDelete = 0;
        for (const btn of btns) {
            if (btn.dataset.count === '0') {
                btn.disabled = true;
            } else {
                toDelete++;
            }
        }

        if (toDelete === 0) return;

        const deletePost = (dataAction, action) => {
            $('body').on('click', `[data-action="${dataAction}"]`, function (e) {
                e.preventDefault();
                if (!confirm('<?php echo $confirm; ?>')) return;
                $.post(ajaxurl, { action }, (res) => {
                    if (res.success) {
                        success(res.data.message);
                        setTimeout(() => {
                            location.reload();
                        }, 800);
                    } else {
                        error(res.data.message);
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