<?php

use JEALER\G3\Components\User\Includes\ManagerListTable;

$table = new ManagerListTable();
$table->display();
?>

<script>
    jQuery(document).ready(function ($) {
        const { toast, modal } = jui;
        const editor = new modal({
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
                }
            ],
            onSubmit: (data) => {
                editor.showLoading();
                $.post(ajaxurl, {
                    action: 'g3_edit_manager_role',
                    data: data
                }, (res) => {
                    if (res.success) {
                        toast.success(res.data.message);
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        toast.error(res.data.message);
                    }
                    editor.hideLoading();
                });
            },
            onHidden: () => {
                editor.reset()
            }
        });
        $(document).on('click', '.add-role', (e) => {
            e.preventDefault();
            editor.show();
        })
        $(document).on('click', '.delete-role', function (e) {
            const slug = $(e.currentTarget).data('slug');
            if (confirm('<?php _e('Are you sure you want to delete it?', 'G3'); ?>')) {
                $.post(ajaxurl, {
                    action: 'g3_delete_manager_role',
                    slug: slug
                }, (res) => {
                    if (res.success) {
                        toast.success(res.data.message);
                        setTimeout(() => {
                            location.reload();
                        }, 800);
                    } else {
                        toast.error(res.data.message);
                    }
                })
            }
        })
        $(document).on('click', '.edit-role', function (e) {
            const name = $(e.currentTarget).data('name');
            const slug = $(e.currentTarget).data('slug');
            editor.setFields([
                {
                    label: '<?php _e('Name'); ?>',
                    name: 'name',
                    type: 'text',
                    required: true,
                    value: name
                },
                {
                    label: '<?php _e('Slug'); ?>',
                    name: 'slug',
                    type: 'text',
                    required: true,
                    value: slug
                }
            ]);
            editor.show();
        })
    });
</script>