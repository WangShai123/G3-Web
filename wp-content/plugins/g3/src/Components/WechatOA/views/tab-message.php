<?php
use JEALER\G3\Components\Components;
use JEALER\G3\Services\WechatOAService;
use JEALER\G3\Utilities\Context;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Components\WechatOA\Includes\WechatOAMessageListTable;
use JEALER\G3\Utilities\Message;

$table = new WechatOAMessageListTable();

$option = Context::get(WechatOAService::OPTION_KEY);
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
        const { Toast, Modal } = jui
        const { success, error } = Toast
        if ($('.view-message').length) {
            $(document).on('click', '.view-message', function () {
                const id = $(this).data('id')
                $.post(ajaxurl, {
                    action: 'g3_get_wechatOA_message_content',
                    id: id,
                    nonce: '<?php echo wp_create_nonce('g3_get_wechatOA_message_content'); ?>'
                }, function (res) {
                    const viewModal = new Modal({
                        text: {
                            title: '<?php _e("View"); ?>',
                            confirm: '<?php _e("Confirm", 'G3'); ?>',
                        },
                        content: res.data.message,
                        showCancel: false
                    })
                    viewModal.show()
                })
            })
        }

        if ($('.delete-message').length) {
            $(document).on('click', '.delete-message', function () {
                const id = $(this).data('id')
                if (confirm('<?php Message::deleteConfirm(); ?>')) {
                    $.post(ajaxurl, {
                        action: 'g3_delete_wechatOA_message',
                        id: id,
                        nonce: '<?php echo wp_create_nonce('g3_delete_wechatOA_message'); ?>'
                    }, function (res) {
                        res.success ? success(res.data.message) : error(res.data.message)
                        setTimeout(function () {
                            location.reload()
                        }, 1000)
                    })
                }
            })
        }


        if (!disabled) {
            $(document).on('click', '#flush-messages', function () {
                const m = new Modal({
                    text: {
                        title: '<?php _e("Delete History Data", "G3"); ?>',
                        confirm: '<?php _e("Delete"); ?>',
                        cancel: '<?php _e("Cancel"); ?>',
                    },
                    formData: [
                        {
                            label: '<?php _e("How many days ago you want to delete?", "G3"); ?>',
                            type: 'number',
                            name: 'days',
                            id: 'days',
                            placeholder: '<?php _e("Default"); ?> 7',
                            value: 7,
                            required: true
                        }
                    ],
                    onSubmit: function (data) {
                        m.state.loading = true;
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
                                    location.reload()
                                }, 800)
                            } else {
                                error(res.data.message)
                                m.state.loading = false;
                            }
                        }).done(function (res) {
                            m.state.loading = false;
                            m.hide();
                        })
                    }
                })
                m.show();
            })
        }
    });
</script>