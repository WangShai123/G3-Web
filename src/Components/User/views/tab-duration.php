<?php
use JEALER\G3\Components\User\Includes\DurationListTable;
use JEALER\G3\Utilities\Message;

$table = new DurationListTable();
$table->display();
?>

<script>
    jQuery(document).ready(function ($) {
        const { Toast, createModal, createForm } = jui;
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
                },
                {
                    type: 'number',
                    payload: {
                        label: '<?php _e('Membership Duration', 'G3'); ?>',
                        name: 'duration',
                        required: true
                    }
                },
                {
                    type: 'select',
                    payload: {
                        label: '<?php _e('Time Unit', 'G3'); ?>',
                        name: 'unit',
                        required: true,
                        value: 'minute',
                        options: [
                            {
                                text: '<?php _e('Second', 'G3'); ?>',
                                value: 'second'
                            },
                            {
                                text: '<?php _e('Minute'); ?>',
                                value: 'minute'
                            },
                            {
                                text: '<?php _e('Hour'); ?>',
                                value: 'hour'
                            },
                            {
                                text: '<?php _e('Day'); ?>',
                                value: 'day'
                            },
                            {
                                text: '<?php _e('Week', 'G3'); ?>',
                                value: 'week'
                            },
                            {
                                text: '<?php _e('Month'); ?>',
                                value: 'month'
                            },
                        ],
                    }
                }
            ],
            buttons: "reverse",
            buttonsPosition: "end",
            onSubmit: (data) => {
                $.post(ajaxurl, {
                    action: 'g3_edit_membership_duration',
                    data
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
            },
        }).build()
        const editor = createModal({
            text: {
                title: '<?php _e('Edit'); ?>',
            },
            content: f.element,
            footer: false,
            onHidden: () => {
                f.reset()
            }
        }).build();
        $(document).on('click', '.add-duration', (e) => {
            e.preventDefault();
            editor.show();
        })
        $(document).on('click', '.edit-duration', (e) => {
            e.preventDefault();
            const t = $(e.currentTarget);
            f.setFields([
                {
                    type: 'text',
                    payload: {
                        label: '<?php _e('Name'); ?>',
                        name: 'name',
                        value: t.data('name'),
                        required: true
                    }
                },
                {
                    type: 'text',
                    payload: {
                        label: '<?php _e('Slug'); ?>',
                        name: 'slug',
                        value: t.data('slug'),
                        required: true
                    }
                },
                {
                    type: 'number',
                    payload: {
                        label: '<?php _e('Membership Duration', 'G3'); ?>',
                        name: 'duration',
                        value: t.data('duration'),
                        required: true
                    }
                },
                {
                    type: 'select',
                    payload: {
                        label: '<?php _e('Time Unit', 'G3'); ?>',
                        name: 'unit',
                        required: true,
                        value: 'second',
                        options: [
                            {
                                text: '<?php _e('Second', 'G3'); ?>',
                                value: 'second'
                            },
                            {
                                text: '<?php _e('Minute'); ?>',
                                value: 'minute'
                            },
                            {
                                text: '<?php _e('Hour'); ?>',
                                value: 'hour'
                            },
                            {
                                text: '<?php _e('Day'); ?>',
                                value: 'day'
                            },
                            {
                                text: '<?php _e('Week', 'G3'); ?>',
                                value: 'week'
                            },
                            {
                                text: '<?php _e('Month'); ?>',
                                value: 'month'
                            },
                        ],
                    }
                }
            ])
            editor.show()
        })
        $(document).on('click', '.delete-duration', (e) => {
            const t = $(e.currentTarget);
            confirm('<?php Message::deleteConfirm(); ?>', {
                text: {
                    cancel: '<?php _e('Cancel'); ?>',
                    action: '<?php _e('Delete'); ?>',
                },
                onConfirm: () => {
                    $.post(ajaxurl, {
                        action: 'g3_delete_membership_duration',
                        data: {
                            slug: t.data('slug')
                        }
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
            });
        })
    })
</script>