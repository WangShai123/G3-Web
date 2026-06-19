<?php

use JEALER\G3\Components\User\Includes\DurationListTable;

$table = new DurationListTable();
$table->display();
?>

<script>
    jQuery(document).ready(function ($) {
        const { Toast, Modal } = jui;
        const { success, error } = Toast;
        const editor = new Modal({
            title: '<?php _e('Edit'); ?>',
            cancelText: '<?php _e('Cancel'); ?>',
            confirmText: '<?php _e('Submit'); ?>',
            fields: [
                {
                    label: '<?php _e('Name'); ?>',
                    name: 'name',
                    type: 'text',
                    required: true
                },
                {
                    label: '<?php _e('Slug'); ?>',
                    name: 'slug',
                    type: 'text',
                    required: true
                },
                {
                    label: '<?php _e('Membership Duration', 'G3'); ?>',
                    name: 'duration',
                    type: 'number',
                    required: true
                },
                {
                    label: '<?php _e('Time Unit', 'G3'); ?>',
                    name: 'unit',
                    type: 'select',
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
            ],
            onSubmit: (data) => {
                editor.state.loading = true;
                $.post(ajaxurl, {
                    action: 'g3_edit_membership_duration',
                    data
                }, (res) => {
                    if (res.success) {
                        success(res.data.message);
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        error(res.data.message);
                    }
                }).done(() => {
                    editor.state.loading = false
                });
            },
            onHidden: () => {
                editor.reset()
            }
        });
        $(document).on('click', '.add-duration', (e) => {
            e.preventDefault();
            editor.show();
        })
        $(document).on('click', '.edit-duration', (e) => {
            e.preventDefault();
            const t = $(e.currentTarget);
            editor.setFields([
                {
                    label: '<?php _e('Name'); ?>',
                    name: 'name',
                    type: 'text',
                    value: t.data('name'),
                    required: true
                },
                {
                    label: '<?php _e('Slug'); ?>',
                    name: 'slug',
                    type: 'text',
                    value: t.data('slug'),
                    required: true
                },
                {
                    label: '<?php _e('Membership Duration', 'G3'); ?>',
                    name: 'duration',
                    type: 'number',
                    value: t.data('duration'),
                    required: true
                },
                {
                    label: '<?php _e('Time Unit', 'G3'); ?>',
                    name: 'unit',
                    type: 'select',
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
            ])
            editor.show()
        })
        $(document).on('click', '.delete-duration', (e) => {
            if (confirm('<?php _e('Are you sure you want to delete it?', 'G3'); ?>')) {
                $.post(ajaxurl, {
                    action: 'g3_delete_membership_duration',
                    data: {
                        slug: $(e.currentTarget).data('slug')
                    }
                }, (res) => {
                    if (res.success) {
                        success(res.data.message);
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        error(res.data.message);
                    }
                })
            }
        })
    })
</script>