<?php
use JEALER\G3\Components\User\Includes\PremiumListTable;
use JEALER\G3\Utilities\Message;

$table = new PremiumListTable();
$table->display();
?>

<script>
    jQuery(document).ready(function ($) {
        const { createModal, Toast, createForm } = jui;
        const { success, error, confirm } = Toast;
        const f = createForm({
            fields: [
                {
                    type: 'text',
                    payload: {
                        label: '<?php _e('Name'); ?>',
                        name: 'name',
                        required: true
                    }
                },
                {
                    type: 'text',
                    payload: {
                        label: '<?php _e('Slug'); ?>',
                        name: 'slug',
                        required: true
                    }
                }
            ],
            buttons: "reverse",
            buttonsPosition: "end",
            onSubmit: (data) => {
                $.post(ajaxurl, {
                    action: 'g3_edit_premium_config',
                    data
                }, (res) => {
                    if (res.success) {
                        success(res.data.message);
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    }
                }).fail((res) => {
                    error(res.responseJSON.data.message);
                })
            },
        }).build();
        const editor = createModal({
            text: {
                title: '<?php _e('Edit'); ?>',
                cancel: '<?php _e('Cancel'); ?>',
                confirm: '<?php _e('Submit'); ?>',
            },
            content: f.element,
            footer: false,
            onHidden: () => {
                f.reset()
            }
        }).build();
        $(document).on('click', '.add-config', (e) => {
            e.preventDefault();
            editor.show();
        });
        $(document).on('click', '.edit-config', (e) => {
            const name = $(e.currentTarget).data('name');
            const slug = $(e.currentTarget).data('slug');
            f.setFields([
                {
                    type: 'text',
                    payload: {
                        label: '<?php _e('Name'); ?>',
                        name: 'name',
                        required: true,
                        value: name
                    }
                },
                {
                    type: 'text',
                    payload: {
                        label: '<?php _e('Slug'); ?>',
                        name: 'slug',
                        required: true,
                        value: slug
                    }
                }
            ]);
            editor.show();
        });
        $(document).on('click', '.delete-config', (e) => {
            const slug = $(e.currentTarget).data('slug');
            confirm('<?php Message::deleteConfirm(); ?>', {
                onConfirm: () => {
                    $.post(ajaxurl, {
                        action: 'g3_delete_premium_config',
                        slug
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
    })
</script>