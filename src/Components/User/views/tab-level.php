<?php
use JEALER\G3\Components\User\Includes\RoleListTable;
use JEALER\G3\Utilities\Message;

$table = new RoleListTable();
$table->display();
?>

<script>
    jQuery(document).ready(function ($) {
        const { Toast, createModal, createForm } = jui;
        const { success, error, confirm } = Toast;
        $(document).on('click', '.reset-role', (e) => {
            let slug = $(e.currentTarget).data('slug');
            if (slug === 'abandon' || slug === 'beginner') {
                confirm('<?php _e('Are you sure you want to reset the current role name?', 'G3'); ?>', {
                    onConfirm: () => {
                        $.post(ajaxurl, {
                            action: 'g3_reset_role',
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
                        });
                    }
                })
            }
        })

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
                },
                {
                    type: 'number',
                    payload: {
                        label: '<?php _e('Start Credits', 'G3'); ?>',
                        name: 'start',
                        required: true
                    }
                },
                {
                    type: 'number',
                    payload: {
                        label: '<?php _e('End Credits', 'G3'); ?>',
                        name: 'end',
                        required: true
                    }
                }
            ],
            buttons: "reverse",
            buttonsPosition: "end",
            onSubmit: (data) => {
                $.post(ajaxurl, {
                    action: 'g3_edit_role',
                    data
                }, (res) => {
                    f.state.submitting = true;
                    setTimeout(() => {
                        Toast.success(res.data.message);
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                        f.state.submitting = false;
                        editor.hide();
                    }, 800);
                }).fail((res) => {
                    f.state.submitting = false;
                    Toast.error(res.responseJSON.data.message);
                });
            },
        }).build();
        const editor = createModal({
            text: {
                title: '<?php _e('Edit'); ?>',
                cancel: '<?php _e('Cancel'); ?>',
                confirm: '<?php _e('Submit'); ?>',
            },
            footer: false,
            content: f.element,
            onHidden: () => {
                console.log(f)
                f.resetFields();
            }
        }).build();

        $(document).on('click', '.add-role', (e) => {
            e.preventDefault();
            editor.show();
        });
        $(document).on('click', '.delete-role', (e) => {
            const slug = $(e.currentTarget).data('slug');
            confirm('<?php echo Message::deleteConfirm(); ?>', {
                onConfirm: () => {
                    $.post(ajaxurl, {
                        action: 'g3_delete_role',
                        slug
                    }, (res) => {
                        if (res.success) {
                            Toast.success(res.data.message)
                            setTimeout(() => {
                                location.reload();
                            }, 800)
                        }
                    }).fail((res) => {
                        Toast.error(res.responseJSON.data.message);
                    })
                }
            });
        });
        $(document).on('click', '.edit-role', (e) => {
            const target = $(e.currentTarget);
            const name = target.data('name');
            const slug = target.data('slug');
            const start = target.data('start');
            const end = target.data('end');
            if (slug === 'abandon') {
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
                            value: slug,
                            readonly: true
                        }
                    },
                    {
                        type: 'text',
                        payload: {
                            label: '<?php _e('Start Credits', 'G3'); ?>',
                            name: 'start',
                            value: start,
                            readonly: true
                        }
                    },
                    {
                        type: 'number',
                        payload: {
                            label: '<?php _e('End Credits', 'G3'); ?>',
                            name: 'end',
                            value: end,
                            readonly: true
                        }
                    }
                ])
            } else if (slug === 'beginner') {
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
                        type: 'hidden',
                        payload: {
                            name: 'slug',
                            value: slug,
                            readonly: true,
                        }
                    },
                    {
                        type: 'hidden',
                        payload: {
                            name: 'start',
                            value: '0',
                            readonly: true
                        }
                    },
                    {
                        type: 'number',
                        payload: {
                            label: '<?php _e('End Credits', 'G3'); ?>',
                            name: 'end',
                            value: end,
                        }
                    }
                ])
            } else {
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
                            value: slug,
                            required: true,
                        }
                    },
                    {
                        type: 'number',
                        payload: {
                            label: '<?php _e('Start Credits', 'G3'); ?>',
                            name: 'start',
                            value: start,
                            required: true,
                        }
                    },
                    {
                        type: 'number',
                        payload: {
                            label: '<?php _e('End Credits', 'G3'); ?>',
                            name: 'end',
                            value: end,
                        }
                    }
                ])
            }
            editor.show();
        });
    });
</script>