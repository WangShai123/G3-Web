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
        const { Toast, createModal, createForm } = jui;
        const { success, error, confirm } = Toast;
        const f = createForm({
            fields: [
                {
                    type: 'select',
                    payload: {
                        label: '<?php _e('Name'); ?>',
                        name: 'name',
                        options: [
                            <?php foreach ($groups as $slug => $group) :
                                echo "{
                                text: '{$group['name']}',
                                value: '{$slug}'
                            },";
                            endforeach; ?>
                        ]
                    }
                },
                {
                    type: 'select',
                    payload: {
                        label: '<?php _e('Membership Duration', 'G3'); ?>',
                        name: 'duration',
                        options: [
                            <?php foreach ($durations as $slug => $duration) :
                                echo "{
                                text: '{$duration['name']}',
                                value: '{$slug}'
                            },";
                            endforeach; ?>
                        ]
                    }
                },
                {
                    type: 'number',
                    payload: {
                        label: '<?php _e('Price', 'G3'); ?>',
                        name: 'price',
                    }
                }
            ],
            buttons: "reverse",
            buttonsPosition: "end",
            onSubmit: (data) => {
                $.post(ajaxurl, {
                    action: 'g3_edit_membership_project',
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
        }).build();
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
        $(document).on('click', '.add-project', () => {
            editor.show()
        })
        $(document).on('click', '.edit-project', (e) => {
            const t = $(e.currentTarget)
            f.setFields([
                {
                    type: 'select',
                    payload: {
                        label: '<?php _e('Name'); ?>',
                        name: 'slug',
                        value: t.data('name'),
                        options: [
                            <?php foreach ($groups as $slug => $group) :
                                echo "{
                                text: '{$group['name']}',
                                value: '{$slug}'
                            },";
                            endforeach; ?>
                        ]
                    }
                },
                {
                    type: 'select',
                    payload: {
                        label: '<?php _e('Membership Duration', 'G3'); ?>',
                        name: 'duration',
                        value: t.data('duration'),
                        options: [
                            <?php foreach ($durations as $slug => $duration) :
                                echo "{
                                text: '{$duration['name']}',
                                value: '{$slug}'
                            },";
                            endforeach; ?>
                        ]
                    }
                },
                {
                    type: 'number',
                    payload: {
                        label: '<?php _e('Price', 'G3'); ?>',
                        name: 'price',
                        value: t.data('price')
                    }
                }
            ])
            editor.show()
        })
        $(document).on('click', '.delete-project', (e) => {
            const id = $(e.currentTarget).data('id')
            confirm('<?php echo Message::deleteConfirm(); ?>', {
                onConfirm: () => {
                    $.post(ajaxurl, {
                        action: 'g3_delete_membership_project',
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
        })
        $(document).on('click', '.copy-payLink', (e) => {
            Toast.info('todo...')
        })
    })
</script>
<?php
