<?php
use JEALER\G3\Components\Orders\Includes\OrdersListTable;
$table = new OrdersListTable();
$table->display();
?>

<script>
    jQuery(document).ready(($) => {
        const { Toast, Modal } = jui
        const resAction = (res, time = 800) => {
            if (res.success) {
                Toast.success(res.data.message)
            } else {
                Toast.error(res.data.message)
            }
            setTimeout(() => {
                location.reload()
            }, time)
        }
        $(document).on('click', '.close-order', (e) => {
            if (confirm('<?php _e('Are you sure you want to close it?', 'G3'); ?>')) {
                $.post(ajaxurl, {
                    action: 'g3_close_order',
                    order_id: $(e.currentTarget).data('id')
                }, (res) => {
                    resAction(res)
                })
            }
        });
        $(document).on('click', '.delete-order', function (e) {
            if (confirm('<?php _e('Are you sure you want to delete it?', 'G3'); ?>')) {
                $.post(ajaxurl, {
                    action: 'g3_delete_order',
                    order_id: $(this).data('id')
                }, (res) => {
                    resAction(res)
                })
            }
        });
        $(document).on('click', '.ship-order', (e) => {
            const editor = new Modal({
                title: '<?php _e('Deliver', 'G3'); ?>',
                cancelText: '<?php _e('Cancel'); ?>',
                confirmText: '<?php _e('Submit'); ?>',
                fields: [
                    {
                        label: '<?php _e('Deliver Tracking Number', 'G3'); ?>',
                        type: 'text',
                        name: 'number',
                    }
                ],
                onSubmit: (data) => {
                    $.post(ajaxurl, {
                        action: 'g3_ship_order',
                        order_id: $(e.currentTarget).data('id'),
                        number: data.number,
                    }, (res) => {
                        resAction(res)
                    })
                }
            });
            editor.show();
        })
    })
</script>