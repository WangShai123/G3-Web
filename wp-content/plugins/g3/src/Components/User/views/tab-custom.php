<?php
use JEALER\G3\Components\User\Includes\CustomListTable;
use JEALER\G3\Utilities\Message;

$table = new CustomListTable();
$table->display();
?>

<script>
    jQuery(document).ready(function ($) {
        const { Toast, Modal } = jui;
        const { success, error } = Toast
        const editor = new Modal({
            text: {
                title: '<?php _e('Edit'); ?>',
                cancel: '<?php _e('Cancel'); ?>',
                confirm: '<?php _e('Submit'); ?>',
            },
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
                $.post(ajaxurl, {
                    action: 'g3_edit_custom_role',
                    data: data
                }, (res) => {
                    if (res.success) {
                        success(res.data.message);
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        error(res.data.message);
                    }
                });
            },
            onHidden: () => {
                editor.reset()
            }
        });
        $(document).on('click', '.add-role', (e) => {
            e.preventDefault();
            editor.show();
        });
        $(document).on('click', '.edit-role', (e) => {
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
        });
        $(document).on('click', '.delete-role', (e) => {
            const slug = $(e.currentTarget).data('slug');
            if (confirm('<?php Message::deleteConfirm(); ?>')) {
                $.post(ajaxurl, {
                    action: 'g3_delete_custom_role',
                    slug: slug
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
    });

</script>