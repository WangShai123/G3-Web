<?php
use JEALER\G3\Components\Auth\Includes\InvitationCodeListTable;
use JEALER\G3\Utilities\Message;

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
                text: {
                    title: '<?php _e('Generate Invitation Code', 'G3'); ?>',
                    confirm: '<?php _e('Generate Invitation Code', 'G3'); ?>',
                    cancel: '<?php _e('Cancel'); ?>',
                },
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
                        data
                    }, (res) => {
                        setTimeout(() => {
                            if (res.success) {
                                success(res.data.message);
                                setTimeout(() => {
                                    location.reload();
                                }, 800);
                            } else {
                                error(res.data.message);
                            }
                            editor.state.loading = false;
                        }, 300);
                    })
                },
            });
            editor.show();
        });
        $(document).on('click', '.delete-code', (e) => {
            if (confirm('<?php Message::deleteConfirm(); ?>')) {
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
