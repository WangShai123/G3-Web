<?php

use JEALER\G3\Components\Auth\Includes\InvitationCodeListTable;

$table = new InvitationCodeListTable();
$table->display();
?>

<script>
    jQuery(document).ready(function ($) {
        const { Modal, Toast, copy } = jui;
        const { success, error } = Toast
        $(document).on('click', '.generate-code', (e) => {
            e.preventDefault();
            const editor = new Modal({
                title: '<?php _e('Generate Invitation Code', 'G3'); ?>',
                confirmText: '<?php _e('Generate Invitation Code', 'G3'); ?>',
                cancelText: '<?php _e('Cancel'); ?>',
                fields: [{
                    label: '<?php _e('Amount', 'G3'); ?>',
                    name: 'amount',
                    type: 'number',
                    value: 1
                }],
                onSubmit: (data) => {
                    editor.state.loading = true;
                    $.post(ajaxurl, {
                        action: 'g3_generate_invite_code',
                        data: data
                    }, (res) => {
                        if (res.success) {
                            success(res.data.message);
                            setTimeout(() => {
                                location.reload();
                            }, 800);
                        } else {
                            error(res.data.message);
                        }
                    }).done(() => {
                        editor.state.loading = false;
                    })
                },
            });
            editor.show();
        });
        $(document).on('click', '.delete-code', (e) => {
            if (confirm('<?php _e('Are you sure you want to delete it?', 'G3'); ?>')) {
                $.post(ajaxurl, {
                    action: 'g3_delete_invite_code',
                    data: {
                        id: $(e.currentTarget).data('id')
                    }
                }, (res) => {
                    if (res.success) {
                        success(res.data.message);
                        setTimeout(() => {
                            location.reload();
                        }, 800);
                    } else {
                        error(res.data.message);
                    }
                })
            }
        });
        $(document).on('click', '.copy-code', (e) => {
            const code = $(e.currentTarget).data('code');
            const result = copy(code);
            if (result) {
                success('<?php _e('Copied'); ?>');
            } else {
                error('<?php _e('Failed'); ?>');
            }
        })
    });
</script>