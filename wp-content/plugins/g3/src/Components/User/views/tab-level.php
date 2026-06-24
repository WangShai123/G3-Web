<?php
use JEALER\G3\Components\User\Includes\RoleListTable;
use JEALER\G3\Utilities\Message;

$table = new RoleListTable();
$table->display();
?>

<script>
    jQuery(document).ready(function ($) {
        const { Toast, Modal } = jui;
        $(document).on('click', '.reset-role', (e) => {
            let slug = $(e.currentTarget).data('slug');
            if (slug === 'abandon' || slug === 'beginner') {
                if (confirm('<?php _e('Are you sure you want to reset the current role name?', 'G3'); ?>')) {
                    $.post(ajaxurl, {
                        action: 'g3_reset_role',
                        slug
                    }, (res) => {
                        if (res.success) {
                            Toast.success(res.data.message);
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        } else {
                            Toast.error(res.data.message);
                        }
                    });
                }
            }
        })
        const editor = new Modal({
            text: {
                title: '<?php _e('Edit'); ?>',
                cancel: '<?php _e('Cancel'); ?>',
                confirm: '<?php _e('Submit'); ?>',
            },
            fields: [
                {
                    label: '<?php _e('Name'); ?>',
                    type: 'text',
                    name: 'name',
                    required: true
                },
                {
                    label: '<?php _e('Slug'); ?>',
                    type: 'text',
                    name: 'slug',
                    required: true
                },
                {
                    label: '<?php _e('Start Credits', 'G3'); ?>',
                    type: 'number',
                    name: 'start',
                    required: true
                },
                {
                    label: '<?php _e('End Credits', 'G3'); ?>',
                    type: 'number',
                    name: 'end',
                    required: true
                }
            ],
            onSubmit: (data) => {
                editor.state.loading = true;
                $.post(ajaxurl, {
                    action: 'g3_edit_role',
                    data: data
                }, (res) => {
                    setTimeout(() => {
                        if (res.success) {
                            Toast.success(res.data.message);
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        } else {
                            Toast.error(res.data.message);
                        }
                        editor.state.loading = false;
                    }, 300);
                })
            },
            onHidden: () => {
                editor.reset()
            }
        });
        $(document).on('click', '.add-role', (e) => {
            e.preventDefault();
            editor.show();
        });
        $(document).on('click', '.delete-role', (e) => {
            const slug = $(e.currentTarget).data('slug');
            if (confirm('<?php Message::deleteConfirm(); ?>')) {
                $.post(ajaxurl, {
                    action: 'g3_delete_role',
                    slug
                }, (res) => {
                    if (res.success) {
                        Toast.success(res.data.message)
                        setTimeout(() => {
                            location.reload();
                        }, 800)
                    } else {
                        Toast.error(res.data.message);
                    }
                })
            }
        });
        $(document).on('click', '.edit-role', (e) => {
            const target = $(e.currentTarget);
            const name = target.data('name');
            const slug = target.data('slug');
            const start = target.data('start');
            const end = target.data('end');
            if (slug === 'abandon') {
                editor.setFields([
                    {
                        label: '<?php _e('Name'); ?>',
                        type: 'text',
                        name: 'name',
                        required: true,
                        value: name
                    },
                    {
                        name: 'slug',
                        type: 'text',
                        value: slug,
                        type: 'hidden',
                        readonly: true
                    },
                    {
                        name: 'start',
                        type: 'number',
                        value: start,
                        type: 'hidden',
                        readonly: true
                    },
                    {
                        name: 'end',
                        type: 'number',
                        value: end,
                        type: 'hidden',
                        readonly: true
                    }
                ])
            } else if (slug === 'beginner') {
                editor.setFields([
                    {
                        label: '<?php _e('Name'); ?>',
                        type: 'text',
                        name: 'name',
                        required: true,
                        value: name
                    },
                    {
                        name: 'slug',
                        type: 'hidden',
                        value: slug,
                        readonly: true,
                    },
                    {
                        name: 'start',
                        type: 'hidden',
                        value: '0',
                        readonly: true
                    },
                    {
                        label: '<?php _e('End Credits', 'G3'); ?>',
                        name: 'end',
                        type: 'number',
                        value: end,
                    }
                ])
            } else {
                editor.setFields([
                    {
                        label: '<?php _e('Name'); ?>',
                        type: 'text',
                        name: 'name',
                        required: true,
                        value: name
                    },
                    {
                        label: '<?php _e('Slug'); ?>',
                        name: 'slug',
                        type: 'text',
                        value: slug,
                        required: true,
                    },
                    {
                        label: '<?php _e('Start Credits', 'G3'); ?>',
                        name: 'start',
                        type: 'number',
                        value: start,
                        required: true,
                    },
                    {
                        label: '<?php _e('End Credits', 'G3'); ?>',
                        name: 'end',
                        type: 'number',
                        value: end,
                    }
                ])
            }
            editor.show();
        });
    });
</script>