<?php
use JEALER\G3\Utilities\Frontend;
use JEALER\G3\Utilities\Image;
use JEALER\G3\Includes\WechatMenuListTable;
use JEALER\G3\Services\WechatOAService;
Frontend::loadStyle('jui');
Frontend::loadScript('jui');
$table = new WechatMenuListTable();
?>

<div class="j-tip is-default mt-4">
    <div class="tip-title"><?php _e('Tip', 'G3'); ?></div>
    <div class="tip-content">
        <?php
        // 在微信客户端中，公众号菜单每5分钟自动刷新一次。若您想即时手动刷新菜单，请重新关注公众号后访问。
        _e('In the WeChat client, the menu of the official account is refreshed every 5 minutes. If you want to instantly manually refresh the menu, please re-follow the official account and visit.', 'G3');
        ?>
    </div>
</div>
<div class="mt-4 flex gap-2 justify-between">
    <div>
        <a href="<?php echo admin_url('admin.php?page=wechat-oa-menu-edit'); ?>" class="button button-primary">
            <?php _e('Add New Menu', 'G3'); ?>
        </a>
    </div>
    <div class="flex gap-1 flex-wrap justify-end">
        <button type="button" class="button" id="create-wechat-oa-menu">
            <?php _e('Create Menu for WeChat OA', 'G3'); ?>
        </button>
        <button type="button" class="button button-error" id="flush-wechat-oa-menu">
            <?php _e('Flush Menus Online', 'G3'); ?>
        </button>
    </div>
</div>
<?php $table->display(); ?>

<script>
    const $ = jQuery;
    $(document).ready(function () {
        $('.action-delete').on('click', function (e) {
            e.preventDefault();
            let id = $(this).attr('data-id');
            if (confirm('<?php _e('Are you sure you want to delete this menu?', 'G3'); ?>')) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'g3_delete_wechatOA_menu',
                        id
                    },
                    success: function (res) {
                        if (res.success) {
                            JUI.Toast.success(res.data.message, 1000);
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            console.log(res.data)
                            JUI.Toast.error(res.data.message, 2000);
                        }
                    },
                    error: function (res) {
                        JUI.Toast.error(res.data.message, 2000);
                    }
                });
            }
        });
        $('#create-wechat-oa-menu').on('click', function (e) {
            const oldText = $(this).text();
            e.preventDefault();
            if (confirm('<?php _e('Are you sure you want to create this menu for WeChat Official Account?', 'G3'); ?>')) {
                $(this).attr('disabled', true);
                $(this).html('<div class="animate-spin" style="width:24px"><?php echo Image::icon('loader'); ?></div>');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'g3_create_wechatOA_menus',
                        nonce: '<?php echo wp_create_nonce('g3_create_wechatOA_menus'); ?>'
                    },
                    success: function (res) {
                        if (res.success) {
                            JUI.Toast.success(res.data.message, 2000);
                        } else {
                            JUI.Toast.error(res.data.message, 2000);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.log(xhr, status, error)
                    },
                    complete: function () {
                        setTimeout(function () {
                            $('#create-wechat-oa-menu').removeAttr('disabled');
                            $('#create-wechat-oa-menu').text(oldText)
                        }, 1000)
                    }
                })
            }
        });
        $('#flush-wechat-oa-menu').on('click', function (e) {
            const oldText = $(this).text();
            e.preventDefault();
            if (confirm('<?php _e('Are you sure you want to flush the menus online?', 'G3'); ?>')) {
                $(this).attr('disabled', true);
                $(this).html('<div class="animate-spin" style="width:24px"><?php echo Image::icon('loader'); ?></div>');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'g3_flush_wechatOA_menus',
                        nonce: '<?php echo wp_create_nonce('g3_flush_wechatOA_menus'); ?>'
                    },
                    success: function (res) {
                        if (res.success) {
                            JUI.Toast.success(res.data.message, 2000);
                        } else {
                            JUI.Toast.error(res.data.message, 2000);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.log(xhr, status, error)
                    },
                    complete: function () {
                        setTimeout(function () {
                            $('#flush-wechat-oa-menu').removeAttr('disabled');
                            $('#flush-wechat-oa-menu').text(oldText)
                        }, 1000)
                    }
                })
            }
        });
    })
</script>