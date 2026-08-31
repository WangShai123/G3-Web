<?php
use JEALER\G3\Components\Components;
use JEALER\G3\Services\WechatOAService;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Components\WechatOA\Includes\WechatOAMessageListTable;
use JEALER\G3\Utilities\Message;

$table = new WechatOAMessageListTable();

$option = get_option(WechatOAService::OPTION_KEY, []);
$option = is_array($option) ? $option : [];
$enable = $option['storeMessages'] ?? false;

if (!$enable) :
    echo Element::tip(
        __('Message is unavailable. Because the WechatOA message storage function has been disabled.', 'G3'),
        '',
        'danger',
        'mt-4'
    );
else :
    $table->display();
endif;
?>


<script>
    const noItem = jQuery('tr.no-items')
    let disabled = noItem.length ? true : false
    jQuery('#flush-messages').prop('disabled', disabled);

    jQuery(document).ready(function ($) {
        const { Toast, createModal, createForm } = jui
        const { success, error, confirm } = Toast
        if ($('.view-message').length) {
            $(document).on('click', '.view-message', function () {
                const id = $(this).data('id')
                $.post(ajaxurl, {
                    action: 'g3_get_wechatOA_message_content',
                    id: id,
                    nonce: '<?php echo wp_create_nonce('g3_get_wechatOA_message_content'); ?>'
                }, function (res) {
                    const viewModal = createModal({
                        text: {
                            title: '<?php echo __("Content"); ?>',
                            confirm: '<?php _e("Confirm", 'G3'); ?>',
                        },
                        content: () => res.data.message,
                        showCancel: false,
                        bgClose: true,
                        onConfirm: () => { viewModal.hide() },
                        onHidden: () => { viewModal.destroy() }
                    }).build();
                    viewModal.show()
                })
            })
        }

        if ($('.delete-message').length) {
            $(document).on('click', '.delete-message', function () {
                const id = $(this).data('id')
                confirm('<?php echo Message::deleteConfirm(); ?>', {
                    onConfirm: () => {
                        $.post(ajaxurl, {
                            action: 'g3_delete_wechatOA_message',
                            id,
                            nonce: '<?php echo wp_create_nonce('g3_delete_wechatOA_message'); ?>'
                        }, (res) => {
                            if (res.success) {
                                success(res.data.message)
                                setTimeout(function () {
                                    location.reload()
                                }, 800)
                            }
                        }).fail((res) => {
                            error(res.responseJSON.data.message)
                        })
                    }
                })
            })
        }


        if (!disabled) {
            $(document).on('click', '#flush-messages', function () {
                const f = createForm({
                    fields: [
                        {
                            type: 'number',
                            payload: {
                                label: '<?php _e("How many days ago you want to delete?", "G3"); ?>',
                                name: 'days',
                                id: 'days',
                                placeholder: '<?php _e("Default"); ?> 7',
                                value: 7,
                                required: true
                            }
                        }
                    ],
                    buttons: "reverse",
                    buttonsPosition: "end",
                    onSubmit: (data) => {
                        if (data.days < 1) {
                            error('<?php _e("Days must be greater than 0", "G3"); ?>');
                            m.state.loading = false;
                            return;
                        }
                        $.post(ajaxurl, {
                            action: 'g3_flush_old_wechatOA_messages',
                            nonce: '<?php echo wp_create_nonce('g3_flush_old_wechatOA_messages'); ?>',
                            days: data.days
                        }, function (res) {
                            if (res.success) {
                                success(res.data.message)
                                setTimeout(function () {
                                    m.hide();
                                    location.reload()
                                }, 800)
                            }
                        }).fail((res) => {
                            error(res.responseJSON.data.message)
                        })
                    }
                }).build();
                const m = createModal({
                    text: {
                        title: '<?php _e("Delete History Data", "G3"); ?>',
                        confirm: '<?php _e("Delete"); ?>',
                        cancel: '<?php _e("Cancel"); ?>',
                    },
                    content: f.element,
                    footer: false
                }).build();
                m.show();
            })
        }
    });
</script>