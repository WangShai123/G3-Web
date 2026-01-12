<?php
use JEALER\G3\Components;
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Utilities\Container;
use JEALER\G3\Includes\WechatOAMessageListTable;

Frontend::loadStyle('jui');
Frontend::loadScript('jui');
$table = new WechatOAMessageListTable();

$option = Components::getProperty('WechatOA', 'option');
$enable = $option['storeMessages'] ?? false;

if (!$enable) :
    echo Container::tip(
        __('Message is unavailable. Because the WechatOA message storage function has been disabled.', 'G3'),
        'danger',
        'mt-4'
    );
else :
    $table->display();
endif;
?>

<style>

</style>
<script>
    const $ = jQuery;
    $(document).ready(function () {
        $('html').addClass('j-theme-indigo j-font-sm j-radius-sm j-shadow-none')
        const viewMsg = $('.view-message')
        const deleteMsg = $('.delete-message')
        const flushMsg = $('#flush-messages')
        viewMsg.on('click', function () {
            const id = $(this).data('id')
            $.post(ajaxurl, {
                action: 'g3_get_wechatOA_message_content',
                id: id,
                nonce: '<?php echo wp_create_nonce('g3_get_wechatOA_message_content'); ?>'
            }, function (res) {
                const viewModal = new jui.modal({
                    title: '<?php _e("View"); ?>',
                    content: res.data.message,
                    confirmText: '<?php _e("Confirm", 'G3'); ?>',
                    showCancel: false
                })
                viewModal.show()
            })
        })
        deleteMsg.on('click', function () {
            const id = $(this).data('id')
            if (confirm('<?php _e('Are you sure you want to delete it?', 'G3'); ?>')) {
                $.post(ajaxurl, {
                    action: 'g3_delete_wechatOA_message',
                    id: id,
                    nonce: '<?php echo wp_create_nonce('g3_delete_wechatOA_message'); ?>'
                }, function (res) {
                    res.success ? jui.toast.success(res.data.message) : jui.toast.error(res.data.message)
                    setTimeout(function () {
                        location.reload()
                    }, 1000)
                })
            }
        })
        flushMsg.on('click', function () {
            const modal = new jui.modal({
                title: '<?php _e("Delete History Data", "G3"); ?>',
                confirmText: '<?php _e("Delete"); ?>',
                cancelText: '<?php _e("Cancel"); ?>',
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
                    modal.showLoading();
                    if (data.days < 1) {
                        jui.toast.error('<?php _e("Days must be greater than 0", "G3"); ?>');
                        modal.hideLoading();
                        return;
                    }
                    $.post(ajaxurl, {
                        action: 'g3_flush_old_wechatOA_messages',
                        nonce: '<?php echo wp_create_nonce('g3_flush_old_wechatOA_messages'); ?>',
                        days: data.days
                    }, function (res) {
                        if (res.success) {
                            jui.toast.success(res.data.message)
                            setTimeout(function () {
                                location.reload()
                            }, 800)
                        } else {
                            jui.toast.error(res.data.message)
                            modal.hideLoading();
                        }
                    }).done(function (res) {
                        modal.hideLoading();
                        modal.hide();
                    })
                }
            })
            modal.show();
        })
    });
</script>