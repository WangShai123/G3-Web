<?php
use JEALER\G3\Components;
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Utilities\Container;
use JEALER\G3\Includes\WechatOAMessageListTable;
use JEALER\G3\Services\WechatOAService;

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
        const viewMessage = $('.view-message')
        const deleteMessage = $('.delete-message')
        viewMessage.on('click', function () {
            const id = $(this).data('id')
            $.post(ajaxurl, {
                action: 'g3_get_wechatOA_message_content',
                id: id,
                nonce: '<?php echo wp_create_nonce('g3_get_wechatOA_message_content'); ?>'
            }, function (response) {
                const viewModal = new JUI.Modal({
                    title: '<?php _e("View"); ?>',
                    content: response.data.message,
                    confirmText: '<?php _e("Confirm", 'G3'); ?>',
                    showCancel: false
                })
                viewModal.show()
            })
        })
        deleteMessage.on('click', function () {
            const id = $(this).data('id')
            if (confirm('<?php _e('Are you sure you want to delete it?', 'G3'); ?>')) {
                $.post(ajaxurl, {
                    action: 'g3_delete_wechatOA_message',
                    id: id,
                    nonce: '<?php echo wp_create_nonce('g3_delete_wechatOA_message'); ?>'
                }, function (response) {
                    response.success ? JUI.Toast.success(response.data.message) : JUI.Toast.error(response.data.message)
                    setTimeout(function () {
                        location.reload()
                    }, 1000)
                })
            }
        })
    });
</script>