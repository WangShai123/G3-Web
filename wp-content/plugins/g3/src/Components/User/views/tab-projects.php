<?php
use JEALER\G3\Components\User\Includes\ProjectListTable;
use JEALER\G3\Services\UserService;
use JEALER\G3\Utilities\Message;
use JEALER\G3\Utilities\Option;

$table = new ProjectListTable();
$table->display();

$groups    = Option::get(UserService::GROUP_OPTION_KEY, [], false);
$durations = Option::get(UserService::DURATION_OPTION_KEY, [], false);
?>

<script>
    jQuery(document).ready(function ($) {
        const { Toast, Modal } = jui;
        const { success, error } = Toast;
        const editor = new Modal({
            fields: [
                {
                    label: '<?php _e('Name'); ?>',
                    name: 'name',
                    type: 'select',
                    options: [
                        <?php foreach ($groups as $slug => $group) : ?>
                                        {
                                text: '<?php echo $group['name']; ?>',
                                value: '<?php echo $slug; ?>'
                            },
                        <?php endforeach; ?>
                    ]
                },
                {
                    label: '<?php _e('Membership Duration', 'G3'); ?>',
                    name: 'duration',
                    type: 'select',
                    options: [
                        <?php foreach ($durations as $slug => $duration) : ?>
                                        {
                                text: '<?php echo $duration['name']; ?>',
                                value: '<?php echo $slug; ?>'
                            },
                        <?php endforeach; ?>
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
                        <?php foreach ($groups as $slug => $group) : ?>
                                        {
                                text: '<?php echo $group['name']; ?>',
                                value: '<?php echo $slug; ?>'
                            },
                        <?php endforeach; ?>
                    ]
                },
                {
                    label: '<?php _e('Membership Duration', 'G3'); ?>',
                    name: 'duration',
                    type: 'select',
                    value: t.data('duration'),
                    options: [
                        <?php foreach ($durations as $slug => $duration) : ?>
                                        {
                                text: '<?php echo $duration['name']; ?>',
                                value: '<?php echo $slug; ?>'
                            },
                        <?php endforeach; ?>
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
                const t = $(e.currentTarget)
                $.post(ajaxurl, {
                    action: 'g3_delete_membership_project',
                    data: {
                        id: t.data('id')
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