<?php
use JEALER\G3\Components\Orders\Includes\OrdersListTable;
use JEALER\G3\Utilities\Message;
$table = new OrdersListTable();
$table->display();
?>

<script>
    jQuery(document).ready(($) => {
        const { Toast, createModal, createForm } = jui
        const { success, error, confirm } = Toast

        const resAction = (res, time = 800) => {
            if (res.success) {
                success(res.data.message)
                setTimeout(() => {
                    location.reload()
                }, time)
            }
        }
        $(document).on('click', '.close-order', (e) => {
            const order_id = $(e.currentTarget).data('id')
            confirm('<?php _e('Are you sure you want to close it?', 'G3'); ?>', {
                onConfirm: () => {
                    $.post(ajaxurl, {
                        action: 'g3_close_order',
                        order_id
                    }, (res) => {
                        resAction(res)
                    }).fail((res) => {
                        error(res.responseJSON.data.message)
                    })
                }
            })
        });
        $(document).on('click', '.delete-order', function (e) {
            const order_id = $(this).data('id')
            confirm('<?php echo Message::deleteConfirm(); ?>', {
                onConfirm: () => {
                    $.post(ajaxurl, {
                        action: 'g3_delete_order',
                        order_id
                    }, (res) => {
                        resAction(res)
                    }).fail((res) => {
                        error(res.responseJSON.data.message)
                    })
                }
            })
        });

        $(document).on('click', '.ship-order', (e) => {
            const order_id = $(e.currentTarget).data('id');
            const form = createForm({
                buttons: 'reverse',
                buttonsPosition: 'end',
                fields: [
                    {
                        type: 'text',
                        payload: {
                            label: '<?php _e('Deliver Tracking Number', 'G3'); ?>',
                            name: 'number',
                        }
                    }
                ],
                onSubmit: (data) => {
                    $.post(ajaxurl, {
                        action: 'g3_ship_order',
                        order_id,
                        number: data.number,
                    }, (res) => {
                        resAction(res)
                    }).fail((res) => {
                        error(res.responseJSON.data.message)
                    })
                }
            }).build()
            const editor = createModal({
                text: {
                    title: '<?php _e('Deliver', 'G3'); ?>',
                },
                content: form.element,
                bgClose: true,
                footer: false
            }).build();
            editor.show();
        })
    })
</script>