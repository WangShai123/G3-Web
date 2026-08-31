<?php
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Utilities\Element;
use JEALER\G3\Utilities\Image;
use JEALER\G3\Components\WechatOA\Includes\WechatOAMenuListTable;
use JEALER\G3\Utilities\Message;

$table = new WechatOAMenuListTable();
echo Element::tip(
    __('In the WeChat client, the menu of the official account is refreshed every 5 minutes. If you want to instantly manually refresh the menu, please re-subscribe the official account and visit.', 'G3'),
    '',
    'default',
    'mt-4'
);
?>
<div class="mt-4 flex gap-2 justify-between">
    <div>
        <a href="<?php echo admin_url('admin.php?page=wechat-oa-menu-edit'); ?>" class="button button-primary">
            <?php _e('Add New Menu', 'G3'); ?>
        </a>
    </div>
    <div class="flex gap-1 flex-wrap justify-end">
        <button type="button" class="button" id="create-wechat-oa-menu">
            <?php _e('Update Menus Online', 'G3'); ?>
        </button>
        <button type="button" class="button button-error" id="flush-wechat-oa-menu">
            <?php _e('Flush Menus Online', 'G3'); ?>
        </button>
    </div>
</div>
<?php $table->display(); ?>

<script>
    jQuery(document).ready(function ($) {
        const { Toast } = jui
        const { success, error, confirm } = Toast;
        $('.action-delete').on('click', function (e) {
            e.preventDefault()
            let id = $(this).attr('data-id')

            confirm('<?php echo Message::deleteConfirm(); ?>', {
                onConfirm: () => {
                    $.post(ajaxurl, {
                        action: 'g3_delete_wechatOA_menu',
                        id
                    }, function (res) {
                        if (res.success) {
                            success(res.data.message, 1000)
                            setTimeout(() => {
                                location.reload()
                            }, 800)
                        }
                    }).fail((res) => {
                        error(res.responseJSON.data.message)
                    })
                }
            })
        })

        $('#create-wechat-oa-menu').on('click', function (e) {
            const oldText = $(this).text()
            e.preventDefault()
            const that = $(this)
            confirm('<?php _e('Are you sure you want to create this menu for WeChat Official Account?', 'G3'); ?>', {
                onConfirm: () => {
                    that.attr('disabled', true);
                    that.html('<div class="animate-spin" style="width:24px"><?php echo Image::icon('loader'); ?></div>')
                    $.post(ajaxurl, {
                        action: 'g3_create_wechatOA_menus',
                        nonce: '<?php echo wp_create_nonce('g3_create_wechatOA_menus'); ?>'
                    }, function (res) {
                        if (res.success) {
                            success(res.data.message)
                        }
                    }).fail((res) => {
                        error(res.responseJSON.data.message)
                    }).always(() => {
                        setTimeout(function () {
                            $('#create-wechat-oa-menu').removeAttr('disabled')
                            $('#create-wechat-oa-menu').text(oldText)
                        }, 800)
                    })
                }
            })
        })

        $('#flush-wechat-oa-menu').on('click', function (e) {
            const oldText = $(this).text()
            e.preventDefault()
            const that = $(this);
            confirm('<?php _e('Are you sure you want to flush the menus online?', 'G3'); ?>', {
                onConfirm: () => {
                    that.attr('disabled', true)
                    that.html('<div class="animate-spin" style="width:24px"><?php echo Image::icon('loader'); ?></div>')
                    $.post(ajaxurl, {
                        action: 'g3_flush_wechatOA_menus',
                        nonce: '<?php echo wp_create_nonce('g3_flush_wechatOA_menus'); ?>'
                    }, function (res) {
                        if (res.success) {
                            success(res.data.message)
                        }
                    }).fail((res) => {
                        error(res.responseJSON.data.message)
                    }).always(() => {
                        setTimeout(function () {
                            $('#flush-wechat-oa-menu').removeAttr('disabled')
                            $('#flush-wechat-oa-menu').text(oldText)
                        }, 800)
                    })
                }
            })
        })
    })
</script>