<?php
use JEALER\G3\Components\User\Includes\ProjectListTable;
use JEALER\G3\Services\UserService;
use JEALER\G3\Utilities\Message;
use JEALER\G3\Utilities\Option;

$table = new ProjectListTable();
$table->display();

$groups    = get_option(UserService::GROUP_OPTION_KEY, []);
$durations = get_option(UserService::DURATION_OPTION_KEY, []);
?>

<style>
    .j-tip .tip-content {
        display: flex;
        gap: 4px;
    }
</style>
<script>
    jQuery(document).ready(function ($) {
        const { Toast, Modal } = jui;
        const { success, error } = Toast;
        const editor = new Modal({
            text: {
                title: '<?php _e('Edit'); ?>',
                cancel: '<?php _e('Cancel'); ?>',
                confirm: '<?php _e('Submit'); ?>'
            },
            fields: [
                {
                    label: '<?php _e('Name'); ?>',
                    name: 'name',
                    type: 'select',
                    options: [
                        <?php foreach ($groups as $slug => $group) :
                            echo "{
                                text: '{$group['name']}',
                                value: '{$slug}'
                            },";
                        endforeach; ?>
                    ]
                },
                {
                    label: '<?php _e('Membership Duration', 'G3'); ?>',
                    name: 'duration',
                    type: 'select',
                    options: [
                        <?php foreach ($durations as $slug => $duration) :
                            echo "{
                                text: '{$duration['name']}',
                                value: '{$slug}'
                            },";
                        endforeach; ?>
                    ]
                },
                {
                    label: '<?php _e('Price', 'G3'); ?>',
                    name: 'price',
                    type: 'number'
                }
            ],
            onSubmit: (data) => {
                editor.state.loading = true;
                $.post(ajaxurl, {
                    action: 'g3_edit_membership_project',
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
                }).done(() => {
                    editor.state.loading = false
                });
            },
            onHidden: () => {
                editor.reset()
            }
        })
        $(document).on('click', '.add-project', (e) => {
            editor.show()
        })
        $(document).on('click', '.edit-project', (e) => {
            const t = $(e.currentTarget)
            editor.setFields([
                {
                    label: '<?php _e('Name'); ?>',
                    name: 'slug',
                    type: 'select',
                    value: t.data('name'),
                    options: [
                        <?php foreach ($groups as $slug => $group) :
                            echo "{
                                text: '{$group['name']}',
                                value: '{$slug}'
                            },";
                        endforeach; ?>
                    ]
                },
                {
                    label: '<?php _e('Membership Duration', 'G3'); ?>',
                    name: 'duration',
                    type: 'select',
                    value: t.data('duration'),
                    options: [
                        <?php foreach ($durations as $slug => $duration) :
                            echo "{
                                text: '{$duration['name']}',
                                value: '{$slug}'
                            },";
                        endforeach; ?>
                    ]
                },
                {
                    label: '<?php _e('Price', 'G3'); ?>',
                    name: 'price',
                    type: 'number',
                    value: t.data('price')
                }
            ])
            editor.show()
        })
        $(document).on('click', '.delete-project', (e) => {
            if (confirm('<?php Message::deleteConfirm(); ?>')) {
                const id = $(e.currentTarget).data('id')
                $.post(ajaxurl, {
                    action: 'g3_delete_membership_project',
                    data: { id }
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
        $(document).on('click', '.copy-payLink', (e) => {
            Toast.info('todo...')
        })
    })
</script>
<?php
