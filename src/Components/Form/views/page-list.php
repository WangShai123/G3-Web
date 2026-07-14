<?php
use JEALER\G3\Components\Form\Includes\FormListTable;
use JEALER\G3\Utilities\Message;

$table = new FormListTable();
$table->display();
?>

<script>
    jQuery(document).ready(function ($) {
        const { Toast, Modal } = jui;
        const { success, error } = Toast;
        $(document).on('click', '.delete-field', function (e) {
            e.preventDefault();
            const id = $(this).data('id');
            if (confirm('<?php Message::deleteConfirm(); ?>')) {
                $.post(ajaxurl, {
                    action: 'g3_delete_field',
                    id
                }, function (res) {
                    if (res.success) {
                        success(res.data.message, 1000);
                        setTimeout(() => { location.reload() }, 800);
                    } else {
                        error(res.data.message, 1000);
                    }
                });
            }
        })
        $(document).on('click', '.change-field-status', function (e) {
            e.preventDefault();
            const id = $(this).data('id');
            const status = $(this).data('status');
            if (confirm('<?php Message::changeConfirm(); ?>')) {
                $.post(ajaxurl, {
                    action: 'g3_change_field_status',
                    id,
                    status
                }, function (res) {
                    if (res.success) {
                        success(res.data.message, 1000);
                        setTimeout(() => { location.reload() }, 800);
                    } else {
                        error(res.data.message, 1000);
                    }
                });
            }
        })
        $(document).on('click', '.view-content', function (e) {
            e.preventDefault();
            const content = $(this).data('content').toString();
            const m = new Modal({
                text: {
                    title: '<?php _e('View') ?>',
                    confirm: '<?php _e('Confirm', 'G3'); ?>',
                },
                content,
                onHidden: () => m.destroy(),
                showCancel: false,
            });
            m.show();
        })
    })
</script>
