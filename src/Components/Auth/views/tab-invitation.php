<?php
use JEALER\G3\Components\Auth\Includes\InvitationCodeListTable;
use JEALER\G3\Utilities\Message;

$table = new InvitationCodeListTable();
$table->display();
?>

<script>
    jQuery(document).ready(function ($) {
        const { createModal, createForm, Toast, copy } = jui;
        const { success, error, confirm } = Toast
        $(document).on('click', '.generate-code', (e) => {
            e.preventDefault();
            const f = createForm({
                fields: [
                    {
                        type: 'number',
                        payload: {
                            label: '<?php _e('Amount', 'G3'); ?>',
                            name: 'amount',
                            value: 1
                        }
                    }
                ],
                buttons: "reverse",
                buttonsPosition: "end",
                onSubmit: (data) => {
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
                                editor.hide();
                            }
                        }, 300);
                    }).fail((res) => {
                        error(res.responseJSON.data.message);
                    })
                }
            }).build();
            const editor = createModal({
                text: {
                    title: '<?php _e('Generate Invitation Code', 'G3'); ?>',
                    confirm: '<?php _e('Generate Invitation Code', 'G3'); ?>',
                    cancel: '<?php _e('Cancel'); ?>',
                },
                footer: false,
                content: f.element,
                onHidden: () => {
                    f.destroy();
                    editor.destroy();
                }
            }).build();
            editor.show();
        });
        $(document).on('click', '.delete-code', (e) => {
            const id = $(e.currentTarget).data('id');
            confirm('<?php Message::deleteConfirm(); ?>', {
                onConfirm: () => {
                    $.post(ajaxurl, {
                        action: 'g3_delete_invite_code',
                        data: { id }
                    }, (res) => {
                        if (res.success) {
                            success(res.data.message);
                            setTimeout(() => {
                                location.reload();
                            }, 800);
                        }
                    }).fail((res) => {
                        error(res.responseJSON.data.message);
                    })
                }
            });
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