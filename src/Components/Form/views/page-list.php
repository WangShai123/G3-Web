<?php
use JEALER\G3\Components\Form\Includes\FormListTable;
use JEALER\G3\Utilities\Message;

$table = new FormListTable();
$table->display();
?>

<script>
    jQuery(document).ready(function ($) {
        const { Toast, createModal } = jui;
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
            confirm('<?php Message::changeConfirm(); ?>', {
                onConfirm: () => {
                    $.post(ajaxurl, {
                        action: 'g3_change_field_status',
                        id,
                        status
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
        $(document).on('click', '.view-content', function () {
            const content = jsx`<div>${$(this).data('content')}</div>`;
            const m = createModal({
                text: {
                    title: '<?php _e('View') ?>',
                    confirm: '<?php _e('Confirm', 'G3'); ?>',
                },
                content,
                showCancel: false,
                bgClose: true,
                onHidden: () => m.destroy(),
                onConfirm: () => m.hide(),
            }).build();
            m.show();
        })
    })
</script>