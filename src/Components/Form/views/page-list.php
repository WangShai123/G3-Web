<?php
use JEALER\G3\Components\Form\Includes\FormListTable;
use JEALER\G3\Utilities\Message;

$table = new FormListTable();
$table->display();
?>

<script>
    jQuery(document).ready(function ($) {
        const { Toast, createModal, createForm } = jui;
        const { jsx } = vanillaSignal;
        const { success, error, confirm } = Toast;
        $(document).on('click', '.delete-field', function () {
            const id = $(this).data('id');
            confirm('<?php Message::deleteConfirm(); ?>', {
                onConfirm: () => {
                    $.post(ajaxurl, {
                        action: 'g3_delete_field',
                        id
                    }, function (res) {
                        if (res.success) {
                            success(res.data.message);
                            setTimeout(() => { location.reload() }, 800);
                        }
                    }).fail((res) => {
                        error(res.responseJSON.data.message);
                    });
                }
            })
        })
        $(document).on('click', '.change-field-status', function () {
            const id = $(this).data('id');
            const status = $(this).data('status');
            const form = createForm({
                fields: [
                    {
                        type: 'select',
                        payload: {
                            label: '<?php _e('Status', 'G3'); ?>',
                            name: 'status',
                            value: status,
                            options: [
                                {
                                    label: '<?php _e('Pending', 'G3'); ?>',
                                    value: '0',
                                },
                                {
                                    label: '<?php _e('Processed', 'G3'); ?>',
                                    value: '1',
                                },
                                {
                                    label: '<?php _e('High Intention', 'G3'); ?>',
                                    value: '2',
                                },
                                {
                                    label: '<?php _e('Low Intention', 'G3'); ?>',
                                    value: '3',
                                },
                            ],
                            required: true,
                        }
                    }
                ],
                onSubmit: (data) => {
                    $.post(ajaxurl, {
                        action: 'g3_change_field_status',
                        id,
                        status: data.status,
                    }, function (res) {
                        if (res.success) {
                            success(res.data.message);
                            setTimeout(() => { location.reload() }, 800);
                        }
                    }).fail((res) => {
                        error(res.responseJSON.data.message);
                    });
                },
                buttons: 'reverse',
                buttonsPosition: 'end'
            }).build();
            const modal = createModal({
                content: form.element,
                footer: false,
                text: {
                    title: '<?php _e('Edit'); ?>'
                },
                bgClose: true,
                escClose: true,
                onHidden: () => {
                    form.destroy();
                    modal.destroy();
                }
            }).build();
            modal.show();
        })
        $(document).on('click', '.view-content', function () {
            const content = jsx`<div>${$(this).data('content')}</div>`;
            const modal = createModal({
                text: {
                    title: '<?php _e('View') ?>',
                    confirm: '<?php _e('Confirm', 'G3'); ?>',
                },
                content,
                showCancel: false,
                escClose: true,
                bgClose: true,
                onHidden: () => modal.destroy(),
                onConfirm: () => modal.hide(),
            }).build();
            modal.show();
        })
    })
</script>