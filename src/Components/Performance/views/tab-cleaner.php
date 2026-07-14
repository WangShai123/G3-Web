<?php
use JEALER\G3\Utilities\Message;

$renderer->form($panel, $panelTab);
?>
<style>
    #submit {
        display: none
    }
</style>
<script>
    jQuery(document).ready(function ($) {
        const { Toast, all } = jui;
        const { success, error } = Toast
        const btns = all('.cleaner-action-button');
        const text = btns[0].innerText;
        $.post(ajaxurl, { action: 'g3_scan_trash' }, (res) => {
            if (res.success) {
                for (const btn of btns) {
                    const count = res.data[btn.dataset.action];
                    btn.dataset.count = count;
                    btn.classList.remove('is-hidden');
                    if (count === 0) {
                        btn.disabled = true;
                    } else {
                        btn.innerText = `${btn.innerText} (${count})`;
                    }
                }
            } else {
                error(res.data.message);
            }
        })
        const deletePosts = (dataAction, action) => {
            $(document).on('click', `[data-action="${dataAction}"]`, function (e) {
                const t = $(e.currentTarget);
                const loader = `<span class="g3-loader-wrap"><span class="icon-loader"></span></span>`;
                Toast.action('<?php Message::deleteConfirm(); ?>', {
                    text: {
                        cancel: '<?php _e('Cancel'); ?>',
                        action: '<?php _e('Clear'); ?>'
                    },
                    onAction: () => {
                        t.prop('disabled', true).html(loader);
                        $.post(ajaxurl, { action }, (res) => {
                            if (res.success) {
                                setTimeout(() => {
                                    success(res.data.message);
                                    t.text(text);
                                }, 1000);
                            } else {
                                setTimeout(() => {
                                    location.reload();
                                }, 1000);
                                error(res.data.message);
                            }
                        })
                    }
                })
            })
        }
        const actions = {
            'draft': 'g3_clear_draft',
            'auto-draft': 'g3_clear_auto_draft',
            'trash': 'g3_clear_trash',
            'revision': 'g3_clear_revision'
        }
        for (const [k, v] of Object.entries(actions)) {
            deletePosts(k, v);
        }
    })
</script>
